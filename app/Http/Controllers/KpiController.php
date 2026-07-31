<?php

namespace App\Http\Controllers;

use App\Models\BaggingEvent;
use App\Models\Barcode;
use App\Models\Batch;
use App\Models\IngestFile;
use App\Models\Station;
use App\Models\StorageBox;
use App\Models\StorageSection;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The floor at a glance (admin): alarms, per-bagger and per-station
 * numbers, flag health, and the sticker ledger — every printed code in
 * exactly one state, with printed − accounted = 0.
 */
class KpiController extends Controller
{
    public function index(Request $request): Response
    {
        $week = now()->subDays(7);

        return Inertia::render('Kpi', [
            'alarms' => BaggingEvent::where('action', BaggingEvent::ALARM)
                ->with(['user', 'batch.barcode'])
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (BaggingEvent $event) => [
                    'id' => $event->id,
                    'who' => $event->user->name,
                    'bagCode' => $event->batch?->barcode?->code ?? $event->batch?->displayLabel() ?? '—',
                    'at' => $event->created_at->format('M j, g:i A'),
                ])->all(),
            'diagnoseWaiting' => Batch::whereNotNull('capture_flag')->whereNull('resolution')->count(),
            'baggers' => $this->baggers($week),
            'stations' => $this->stations(),
            'flags' => $this->flags($week),
            'ledger' => $this->ledger(),
            'stickerLookup' => $this->stickerLookup($request),
        ]);
    }

    private function baggers(\Carbon\CarbonInterface $week): array
    {
        return User::whereIn('id', BaggingEvent::where('created_at', '>=', $week)->select('user_id'))
            ->get()
            ->map(function (User $user) use ($week) {
                $events = fn () => BaggingEvent::where('user_id', $user->id);
                $today = fn () => $events()->whereDate('created_at', today());

                return [
                    'name' => $user->name,
                    'doneToday' => $today()->where('action', BaggingEvent::BAG_DONE)->count(),
                    'averageSecondsToday' => (int) round($today()->where('action', BaggingEvent::BAG_DONE)->avg('seconds') ?? 0),
                    'doneWeek' => $events()->where('created_at', '>=', $week)->where('action', BaggingEvent::BAG_DONE)->count(),
                    'setAsideWeek' => $events()->where('created_at', '>=', $week)->where('action', BaggingEvent::SET_ASIDE)->count(),
                    'alarmsWeek' => $events()->where('created_at', '>=', $week)->where('action', BaggingEvent::ALARM)->count(),
                ];
            })
            ->sortByDesc('doneWeek')
            ->values()
            ->all();
    }

    private function stations(): array
    {
        return Station::orderBy('name')->get()->map(fn (Station $station) => [
            'name' => $station->name,
            'type' => $station->type,
            'revoked' => $station->revoked_at !== null,
            'lastSeen' => $station->last_seen_at?->diffForHumans(),
            'filesToday' => IngestFile::where('station_id', $station->id)->whereDate('created_at', today())->count(),
            'batchesToday' => Batch::where('station_id', $station->id)->whereDate('created_at', today())->count(),
            'flagsWeek' => Batch::where('station_id', $station->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->whereNotNull('capture_flag')
                ->count(),
        ])->all();
    }

    private function flags(\Carbon\CarbonInterface $week): array
    {
        return Batch::whereNotNull('capture_flag')
            ->where('created_at', '>=', $week)
            ->selectRaw('capture_flag, count(*) as total, sum(case when resolution is null then 1 else 0 end) as waiting')
            ->groupBy('capture_flag')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'flag' => $row->capture_flag,
                'total' => (int) $row->total,
                'waiting' => (int) $row->waiting,
            ])->all();
    }

    /**
     * Every sticker in exactly one state. "In service" means physically
     * attached: a bag bound to a batch, a box in use, a divider placed.
     */
    private function ledger(): array
    {
        $inService = [
            Barcode::TYPE_BAG => Batch::whereNotNull('barcode_id')->select('barcode_id'),
            Barcode::TYPE_BOX => StorageBox::select('barcode_id'),
            Barcode::TYPE_DIVIDER => StorageSection::whereNotNull('divider_barcode_id')->select('divider_barcode_id'),
        ];

        return collect([Barcode::TYPE_BAG, Barcode::TYPE_BOX, Barcode::TYPE_DIVIDER])
            ->map(function (string $type) use ($inService) {
                $total = Barcode::where('type', $type)->count();
                $voided = Barcode::where('type', $type)->whereNotNull('voided_at')->count();
                $active = Barcode::where('type', $type)->whereNull('voided_at')
                    ->whereIn('id', $inService[$type])->count();

                return [
                    'type' => $type,
                    'total' => $total,
                    'inService' => $active,
                    'voided' => $voided,
                    'unused' => $total - $active - $voided,
                ];
            })->all();
    }

    /** "Where is this sticker?" — one code, its state, and what holds it. */
    private function stickerLookup(Request $request): ?array
    {
        $code = strtoupper((string) preg_replace('/\s+/', '', $request->string('sticker')->toString()));
        if ($code === '') {
            return null;
        }

        $barcode = Barcode::where('code', $code)->first();
        if ($barcode === null) {
            return ['code' => $code, 'state' => 'unknown', 'detail' => 'Not in the registry — this code was never printed by the system.'];
        }

        if ($barcode->voided_at !== null) {
            return ['code' => $code, 'state' => 'voided', 'detail' => "{$barcode->void_reason} ({$barcode->voided_at->format('M j, Y')})"];
        }

        $holder = match ($barcode->type) {
            Barcode::TYPE_BAG => ($batch = Batch::where('barcode_id', $barcode->id)->first()) !== null
                ? "Bag on batch \"{$batch->displayLabel()}\"".($batch->storageSection !== null ? " — in box {$batch->storageSection->box->barcode->code}" : ' — not boxed')
                : null,
            Barcode::TYPE_BOX => ($box = StorageBox::where('barcode_id', $barcode->id)->first()) !== null
                ? "Box with {$box->sections()->count()} section(s)"
                : null,
            Barcode::TYPE_DIVIDER => ($section = StorageSection::where('divider_barcode_id', $barcode->id)->first()) !== null
                ? "Divider in box {$section->box->barcode->code}"
                : null,
            default => null,
        };

        return [
            'code' => $code,
            'state' => $holder !== null ? 'in service' : 'unused',
            'detail' => $holder ?? 'Printed, never used — should be in the unused sticker stock.',
        ];
    }
}
