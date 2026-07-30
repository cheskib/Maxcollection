<?php

namespace App\Services;

use App\Models\BaggingEvent;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\IngestFile;
use App\Models\User;

/**
 * The bagging station's enforced state machine (owner spec): scan the
 * ticket, get the verdict BEFORE any cards move. Good → peel, fill,
 * seal, scan again to finish (timed). Flagged → problem tone, confirm
 * set-aside by scanning the laminated SET-ASIDE card (timed), then the
 * next bin. No new bin until the current one is closed out. Three
 * flagged bags in a row raise the admin alarm.
 */
class BaggingService
{
    public const ALARM_STREAK = 3;

    /**
     * @return array{ok: bool, tone: string, message: string, alarm?: bool}
     */
    public function scan(User $user, string $raw): array
    {
        $code = strtoupper((string) preg_replace('/\s+/', '', $raw));
        $open = $this->openTicket($user);

        if ($code === 'SET-ASIDE') {
            return $this->confirmSetAside($user, $open);
        }

        if (preg_match('/^BAG-\d{6}$/', $code) !== 1) {
            return $this->result(false, 'error', 'Not a bag ticket. Scan the bin\'s BAG barcode.');
        }

        if ($open !== null) {
            return $this->closeOrRefuse($user, $open, $code);
        }

        return $this->openTicketScan($user, $code);
    }

    /**
     * The bagger's current open ticket: the latest flow event is an
     * unanswered ticket_scanned.
     */
    public function openTicket(User $user): ?BaggingEvent
    {
        $last = BaggingEvent::where('user_id', $user->id)
            ->whereIn('action', [BaggingEvent::TICKET_SCANNED, BaggingEvent::BAG_DONE, BaggingEvent::SET_ASIDE])
            ->orderByDesc('id')
            ->first();

        return $last !== null && $last->action === BaggingEvent::TICKET_SCANNED ? $last : null;
    }

    private function openTicketScan(User $user, string $code): array
    {
        $barcode = Barcode::where('code', $code)->where('type', Barcode::TYPE_BAG)->first();
        $batch = $barcode !== null ? Batch::where('barcode_id', $barcode->id)->first() : null;

        if ($batch === null) {
            // Scanned before the pipeline finished? Tell the bagger to
            // give it a moment rather than treating the bag as unknown.
            $checking = IngestFile::where('folder', $code)->whereNull('processed_at')->exists();

            if ($checking) {
                return $this->result(false, 'neutral', "Still checking {$code} — give it a few seconds and scan again.");
            }

            return $this->result(false, 'error', "Unknown bag {$code} — no captured batch. Set the bin aside and tell an admin.");
        }

        if ($batch->capture_flag !== null) {
            $event = BaggingEvent::create([
                'user_id' => $user->id,
                'batch_id' => $batch->id,
                'action' => BaggingEvent::TICKET_SCANNED,
                'verdict' => BaggingEvent::VERDICT_FLAGGED,
            ]);

            $alarm = $this->flaggedStreak($user) >= self::ALARM_STREAK;
            if ($alarm) {
                BaggingEvent::create(['user_id' => $user->id, 'batch_id' => $batch->id, 'action' => BaggingEvent::ALARM]);
            }

            $reason = str_replace('_', ' ', $batch->capture_flag);

            return $this->result(false, 'error', "FLAGGED ({$reason}) — set this bin ASIDE, then scan the SET-ASIDE card.", $alarm);
        }

        BaggingEvent::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'action' => BaggingEvent::TICKET_SCANNED,
            'verdict' => BaggingEvent::VERDICT_GOOD,
        ]);

        $count = $batch->items()->count();

        return $this->result(true, 'success', "{$code} — good ({$count} cards). Peel, fill, seal, then scan the bag again.");
    }

    private function closeOrRefuse(User $user, BaggingEvent $open, string $code): array
    {
        $openCode = $open->batch?->barcode?->code;

        if ($code !== $openCode) {
            return $this->result(false, 'error', "Finish {$openCode} first — scan it again when sealed"
                .($open->verdict === BaggingEvent::VERDICT_FLAGGED ? ', or scan SET-ASIDE.' : '.'));
        }

        if ($open->verdict === BaggingEvent::VERDICT_FLAGGED) {
            return $this->result(false, 'error', "{$openCode} is FLAGGED — it must be set aside. Scan the SET-ASIDE card.");
        }

        $seconds = max(0, now()->diffInSeconds($open->created_at, true));

        BaggingEvent::create([
            'user_id' => $user->id,
            'batch_id' => $open->batch_id,
            'action' => BaggingEvent::BAG_DONE,
            'seconds' => (int) $seconds,
        ]);

        return $this->result(true, 'success', "{$openCode} done in {$this->duration((int) $seconds)}. Next bin.");
    }

    private function confirmSetAside(User $user, ?BaggingEvent $open): array
    {
        if ($open === null) {
            return $this->result(false, 'error', 'Nothing to set aside — scan a bag ticket first.');
        }

        if ($open->verdict !== BaggingEvent::VERDICT_FLAGGED) {
            $code = $open->batch?->barcode?->code;

            return $this->result(false, 'error', "{$code} is not flagged — finish it by scanning the bag itself.");
        }

        $seconds = max(0, now()->diffInSeconds($open->created_at, true));

        BaggingEvent::create([
            'user_id' => $user->id,
            'batch_id' => $open->batch_id,
            'action' => BaggingEvent::SET_ASIDE,
            'seconds' => (int) $seconds,
        ]);

        return $this->result(true, 'neutral', 'Set-aside confirmed. Next bin.');
    }

    /**
     * Consecutive flagged verdicts, newest first, including the one just
     * recorded — three in a row rings the admin alarm (owner ruling).
     */
    private function flaggedStreak(User $user): int
    {
        $streak = 0;

        BaggingEvent::where('user_id', $user->id)
            ->where('action', BaggingEvent::TICKET_SCANNED)
            ->orderByDesc('id')
            ->limit(self::ALARM_STREAK)
            ->get()
            ->each(function (BaggingEvent $event) use (&$streak) {
                if ($event->verdict !== BaggingEvent::VERDICT_FLAGGED) {
                    return false;
                }
                $streak++;

                return true;
            });

        return $streak;
    }

    private function duration(int $seconds): string
    {
        return $seconds >= 60 ? intdiv($seconds, 60).'m '.($seconds % 60).'s' : "{$seconds}s";
    }

    private function result(bool $ok, string $tone, string $message, bool $alarm = false): array
    {
        return ['ok' => $ok, 'tone' => $tone, 'message' => $message, 'alarm' => $alarm];
    }
}
