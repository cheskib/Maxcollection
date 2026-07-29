<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Item;
use App\Models\StorageEvent;
use App\Models\Withdrawal;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Built-in daily activity reports for administrators: all inbound work
 * (capture pipeline, by stage) and all outbound activity (removals, by
 * reason). Numbers are computed live, so "today" is always current and
 * yesterday's figures reflect any moves made since.
 */
class ReportsController extends Controller
{
    private const DAYS = 14;

    public function index(): Response
    {
        return Inertia::render('Reports', [
            'pipeline' => $this->pipeline(),
            'days' => $this->dailyActivity(),
        ]);
    }

    /**
     * Where every owned item stands right now, stage by stage.
     *
     * @return array<string, int|float|null>
     */
    private function pipeline(): array
    {
        $present = fn () => Item::query()->present();

        return [
            'capturing' => $present()->whereIn('status', [Item::STATUS_CAPTURED, Item::STATUS_QUEUED, Item::STATUS_PROCESSING])->count(),
            'needsReview' => $present()->where('status', Item::STATUS_NEEDS_REVIEW)->count(),
            // Processed but the batch has no bag identity yet.
            'processedUnbagged' => $present()->where('status', Item::STATUS_PROCESSED)
                ->where(fn ($query) => $query
                    ->whereNull('batch_id')
                    ->orWhereHas('batch', fn ($batch) => $batch->whereNull('barcode_id')))
                ->count(),
            // In an identified bag, waiting to be boxed.
            'bagged' => $present()->whereHas('batch', fn ($batch) => $batch->whereNotNull('barcode_id')->whereNull('storage_section_id'))->count(),
            'boxed' => $present()->whereHas('batch', fn ($batch) => $batch->whereNotNull('storage_section_id'))->count(),
            'relocated' => Item::where('disposition', Item::DISPOSITION_RELOCATED)->count(),
            'gone' => Item::where('disposition', Item::DISPOSITION_GONE)->count(),
            'soldTotal' => (float) Withdrawal::where('reason', 'sold')->whereNull('reinstated_at')->sum('sale_price'),
        ];
    }

    /**
     * Day-by-day activity for the last two weeks, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function dailyActivity(): array
    {
        $since = now()->subDays(self::DAYS - 1)->startOfDay();

        $byDate = function ($query, string $column) use ($since): array {
            return $query->where($column, '>=', $since)
                ->selectRaw("date({$column}) as day, count(*) as total")
                ->groupBy('day')
                ->pluck('total', 'day')
                ->all();
        };

        $captured = $byDate(Item::query(), 'created_at');
        $processed = $byDate(Item::query()->whereNotNull('processed_at'), 'processed_at');
        $bagsFinalized = $byDate(Batch::query()->whereNotNull('finalized_at'), 'finalized_at');
        $bagsBoxed = $byDate(StorageEvent::where('action', StorageEvent::BAG_ADDED), 'created_at');
        $boxesCompleted = $byDate(StorageEvent::where('action', StorageEvent::BOX_COMPLETED), 'created_at');
        $reinstated = $byDate(Withdrawal::query()->whereNotNull('reinstated_at'), 'reinstated_at');

        // Outbound: removals per day, split by reason, with sold dollars.
        $removals = Withdrawal::where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as day, reason, count(*) as total, sum(sale_price) as sold_total')
            ->groupBy('day', 'reason')
            ->get()
            ->groupBy('day');

        $days = [];
        for ($offset = 0; $offset < self::DAYS; $offset++) {
            $date = now()->subDays($offset)->toDateString();
            $dayRemovals = $removals->get($date, collect());

            $days[] = [
                'date' => $date,
                'label' => now()->subDays($offset)->format('D, M j'),
                'captured' => $captured[$date] ?? 0,
                'processed' => $processed[$date] ?? 0,
                'bagsFinalized' => $bagsFinalized[$date] ?? 0,
                'bagsBoxed' => $bagsBoxed[$date] ?? 0,
                'boxesCompleted' => $boxesCompleted[$date] ?? 0,
                'reinstated' => $reinstated[$date] ?? 0,
                'removals' => $dayRemovals->map(fn ($row) => [
                    'reason' => Withdrawal::REASON_LABELS[$row->reason] ?? $row->reason,
                    'count' => (int) $row->total,
                    'soldTotal' => $row->sold_total !== null ? (float) $row->sold_total : null,
                ])->values()->all(),
            ];
        }

        return $days;
    }
}
