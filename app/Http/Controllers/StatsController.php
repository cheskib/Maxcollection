<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Metadata;
use App\Models\ProcessingJob;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Collection statistics: composition of the processed collection and
 * how cleanly processing is going as volume grows.
 */
class StatsController extends Controller
{
    public function index(): Response
    {
        $processed = Item::where('status', Item::STATUS_PROCESSED)->count();
        $needsReview = Item::where('status', Item::STATUS_NEEDS_REVIEW)->count();
        $finished = $processed + $needsReview;

        $countBy = function (string $field, int $limit = 15): array {
            return Metadata::whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED))
                ->whereNotNull($field)
                ->selectRaw("{$field} as value, count(*) as total")
                ->groupBy($field)
                ->orderByDesc('total')
                ->limit($limit)
                ->get()
                ->map(fn ($row) => ['value' => (string) $row->value, 'count' => (int) $row->total])
                ->all();
        };

        $reasons = Item::where('status', Item::STATUS_NEEDS_REVIEW)
            ->whereNotNull('review_reason')
            ->selectRaw('review_reason, count(*) as total')
            ->groupBy('review_reason')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'reason' => match ($row->review_reason) {
                    'low_confidence' => 'Low confidence',
                    'unsupported_category' => 'Unsupported category',
                    'ai_failure' => 'AI failure',
                    'missing_metadata' => 'Missing metadata',
                    default => $row->review_reason,
                },
                'count' => (int) $row->total,
            ])
            ->all();

        $avgConfidence = Metadata::whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED))
            ->whereNotNull('confidence')
            ->avg('confidence');

        return Inertia::render('Stats', [
            'overview' => [
                'totalItems' => Item::count(),
                'processed' => $processed,
                'needsReview' => $needsReview,
                // Of items that finished a processing attempt, how many
                // came out clean (no review needed).
                'cleanRate' => $finished > 0 ? round($processed / $finished * 100) : null,
                'avgConfidence' => $avgConfidence !== null ? round((float) $avgConfidence) : null,
                'failedJobs' => ProcessingJob::where('status', ProcessingJob::STATUS_FAILED)->count(),
            ],
            'byYear' => $countBy('year'),
            'byManufacturer' => $countBy('manufacturer'),
            'bySport' => $countBy('sport'),
            'reviewReasons' => $reasons,
        ]);
    }
}
