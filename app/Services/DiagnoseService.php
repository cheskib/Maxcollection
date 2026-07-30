<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\User;
use RuntimeException;

/**
 * Resolves flagged batches (owner spec): every diagnosis ends in exactly
 * one resolution, logged with who and when. The record is never deleted
 * — a "deleted" attempt keeps its batch row as the tombstone while its
 * items and images go, so nothing double-counts when the bag rescans.
 */
class DiagnoseService
{
    public const RESOLUTION_CONFIRMED = 'confirmed';

    public const RESOLUTION_RESCAN = 'rescan';

    public const RESOLUTION_REPLACED = 'replaced';

    public function __construct(private CaptureService $capture)
    {
    }

    public function resolve(User $admin, Batch $batch, string $resolution, ?string $note): string
    {
        if ($batch->capture_flag === null || $batch->resolution !== null) {
            throw new RuntimeException('This batch is not awaiting diagnosis.');
        }

        return match ($resolution) {
            self::RESOLUTION_CONFIRMED => $this->confirm($admin, $batch, $note),
            self::RESOLUTION_RESCAN => $this->deleteFor($admin, $batch, $note, self::RESOLUTION_RESCAN),
            self::RESOLUTION_REPLACED => $this->deleteFor($admin, $batch, $note, self::RESOLUTION_REPLACED),
            default => throw new RuntimeException('Unknown resolution.'),
        };
    }

    /**
     * The capture was actually fine — clear the flag so the bag passes
     * bagging and boxing normally.
     */
    private function confirm(User $admin, Batch $batch, ?string $note): string
    {
        if ($batch->barcode_id === null) {
            throw new RuntimeException('This batch has no bag bound — it cannot be confirmed. Delete it for rescan or replacement.');
        }

        $batch->update([
            'capture_flag' => null,
            'resolution' => self::RESOLUTION_CONFIRMED,
            'resolution_note' => $note,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        return "Confirmed — bag {$batch->barcode->code} is good and back in the flow.";
    }

    /**
     * The capture is discarded: items and images go, the batch row stays
     * as the logged tombstone. Rescan releases the bag number back to the
     * scan line (the ONE number permitted to return); replacement voids
     * the sticker forever — the cards get a fresh ticket.
     */
    private function deleteFor(User $admin, Batch $batch, ?string $note, string $resolution): string
    {
        $bagCode = $batch->barcode?->code ?? $batch->label;

        foreach ($batch->items()->get() as $item) {
            $this->capture->deleteItem($item);
        }

        if ($resolution === self::RESOLUTION_REPLACED && $batch->barcode !== null) {
            $batch->barcode->update([
                'voided_at' => now(),
                'void_reason' => 'Replaced after failed capture (diagnosis)',
            ]);
        }

        $batch->update([
            'barcode_id' => null,
            'status' => Batch::STATUS_OPEN,
            'finalized_at' => null,
            'resolution' => $resolution,
            'resolution_note' => $note,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        return $resolution === self::RESOLUTION_RESCAN
            ? "Deleted — {$bagCode} is released and may return to the scan line as a batch header."
            : "Deleted — {$bagCode} is voided for good. Give the cards a fresh ticket.";
    }
}
