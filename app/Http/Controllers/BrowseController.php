<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Drill-down browse for sports cards: Sport -> Year -> Team -> Players.
 * Cards missing a level's value group under "Unknown".
 */
class BrowseController extends Controller
{
    private const UNKNOWN = 'Unknown';

    public function index(Request $request): Response
    {
        $sport = $request->string('sport')->toString() ?: null;
        $year = $request->string('year')->toString() ?: null;
        $team = $request->string('team')->toString() ?: null;

        $base = Item::where('status', Item::STATUS_PROCESSED)
            ->whereHas('metadata', fn (Builder $query) => $query->where('category', 'sports_card'));

        $level = 'sport';
        $groups = [];
        $items = [];

        if ($sport === null) {
            $groups = $this->groupBy($base, 'sport');
        } elseif ($year === null) {
            $level = 'year';
            $groups = $this->groupBy($this->narrow($base, 'sport', $sport), 'year', descending: true);
        } elseif ($team === null) {
            $level = 'team';
            $groups = $this->groupBy($this->narrow($this->narrow($base, 'sport', $sport), 'year', $year), 'team');
        } else {
            $level = 'players';
            $items = $this->narrow($this->narrow($this->narrow($base, 'sport', $sport), 'year', $year), 'team', $team)
                ->with(['metadata', 'images' => fn ($query) => $query->orderBy('id')])
                ->get()
                ->sortBy(fn (Item $item) => $item->metadata?->player_name ?? '~', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->map(fn (Item $item) => [
                    'id' => $item->id,
                    'thumbnailImageId' => $item->images->first()?->id,
                    'thumbnailRotation' => $item->images->first()?->rotation ?? 0,
                    'player' => $item->metadata?->player_name ?? self::UNKNOWN,
                    'title' => $item->metadata?->primaryTitle() ?? "Item #{$item->id}",
                    'confidence' => $item->metadata?->confidence,
                ])
                ->all();
        }

        return Inertia::render('Browse', [
            'level' => $level,
            'filters' => ['sport' => $sport, 'year' => $year, 'team' => $team],
            'groups' => $groups,
            'items' => $items,
        ]);
    }

    /**
     * Apply one drill-down filter; "Unknown" matches cards missing the value.
     */
    private function narrow(Builder $query, string $field, string $value): Builder
    {
        return (clone $query)->whereHas('metadata', function (Builder $metadata) use ($field, $value) {
            $value === self::UNKNOWN
                ? $metadata->whereNull($field)
                : $metadata->where($field, $value);
        });
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function groupBy(Builder $query, string $field, bool $descending = false): array
    {
        $counts = (clone $query)
            ->join('metadata', 'metadata.item_id', '=', 'items.id')
            ->selectRaw("COALESCE(metadata.{$field}, ?) as label, COUNT(*) as total", [self::UNKNOWN])
            ->groupBy('label')
            ->pluck('total', 'label');

        $labels = $counts->keys()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->when($descending, fn ($sorted) => $sorted->reverse())
            // Unknown always sorts last regardless of direction.
            ->partition(fn (string $label) => $label !== self::UNKNOWN);

        return $labels->flatten()
            ->map(fn (string $label) => ['label' => $label, 'count' => (int) $counts[$label]])
            ->all();
    }
}
