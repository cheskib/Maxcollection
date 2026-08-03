<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Item;
use App\Models\Metadata;
use App\Services\CaptureService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    /**
     * Delete an item with all of its photographs, metadata, and history.
     */
    public function destroy(Item $item, CaptureService $capture): RedirectResponse
    {
        $capture->deleteItem($item);

        return redirect()->route('items.index')->with('status', 'Item deleted.');
    }

    public function show(Item $item): Response
    {
        $metadata = $item->metadata;
        $lastJob = $item->processingJobs()->latest('id')->first();

        // Duplicate awareness: other copies of this exact card
        // (manufacturer + year + player + card number).
        $otherCopies = collect();
        if ($metadata !== null && $metadata->category === 'sports_card'
            && filled($metadata->manufacturer) && filled($metadata->year)
            && (filled($metadata->player_name) || filled($metadata->card_number))) {
            $otherCopies = Metadata::where('category', 'sports_card')
                ->where('manufacturer', $metadata->manufacturer)
                ->where('year', $metadata->year)
                ->whereRaw("coalesce(player_name, '') = ?", [$metadata->player_name ?? ''])
                ->whereRaw("coalesce(card_number, '') = ?", [$metadata->card_number ?? ''])
                ->where('item_id', '!=', $item->id)
                ->orderBy('item_id')
                ->pluck('item_id');
        }

        // Only the values that were actually pulled or entered; blanks are
        // noise here (the Edit screen still offers every field).
        $fields = [];
        if ($metadata !== null && isset(Metadata::CATEGORY_FIELDS[$metadata->category])) {
            foreach (Metadata::CATEGORY_FIELDS[$metadata->category] as $field) {
                if (filled($metadata->{$field})) {
                    $fields[] = [
                        'name' => $field,
                        'label' => Metadata::FIELD_LABELS[$field],
                        'value' => $metadata->{$field},
                    ];
                }
            }
        }

        return Inertia::render('ItemDetail', [
            'collections' => Collection::orderBy('name')->get(['id', 'name'])->all(),
            'item' => [
                'id' => $item->id,
                'collectionId' => $item->collection_id,
                'status' => $item->status,
                'reviewReason' => $item->reviewReasonLabel(),
                'title' => $metadata?->primaryTitle() ?? "Item #{$item->id}",
                'category' => $metadata?->categoryLabel() ?? 'Not processed yet',
                'confidence' => $metadata?->confidence,
                'processedAt' => $item->processed_at?->format('M j, Y g:i A'),
                'images' => $item->images()->orderBy('id')->get()->map(fn ($image) => ['id' => $image->id, 'original_filename' => $image->original_filename, 'version' => $image->versionTag(), 'adjusted' => $image->isAdjusted(), 'canUndo' => $image->previous_adjustments !== null])->all(),
                'fields' => $fields,
                'value' => [
                    'ours' => ['from' => $metadata?->value_from, 'to' => $metadata?->value_to],
                    'purchase' => $metadata?->purchase_price,
                    'insurance' => $metadata?->insurance_value,
                    'ai' => ['from' => $metadata?->ai_value_from, 'to' => $metadata?->ai_value_to],
                    'check' => $this->marketCheckLinks($metadata),
                    'market' => [
                        'value' => $metadata?->market_value,
                        'match' => $metadata?->market_match,
                        'checkedAt' => $metadata?->market_checked_at?->format('M j, Y'),
                    ],
                ],
                'keyCard' => (bool) $metadata?->key_card,
                'disposition' => $item->disposition,
                'withdrawal' => ($withdrawal = $item->activeWithdrawal()) === null ? null : [
                    'reason' => $withdrawal->reason,
                    'reasonLabel' => $withdrawal->reasonLabel(),
                    'notes' => $withdrawal->notes,
                    'salePrice' => $withdrawal->sale_price,
                    'saleDate' => $withdrawal->sale_date?->format('M j, Y'),
                    'buyer' => $withdrawal->buyer,
                    'platform' => $withdrawal->platform,
                    'destination' => $withdrawal->destination,
                    'by' => $withdrawal->user->name,
                    'at' => $withdrawal->created_at->format('M j, Y g:i A'),
                ],
                'withdrawalHistory' => $item->withdrawals()->with('user')->latest('id')->get()
                    ->map(fn ($entry) => [
                        'id' => $entry->id,
                        'reasonLabel' => $entry->reasonLabel(),
                        'destination' => $entry->destination,
                        'notes' => $entry->notes,
                        'by' => $entry->user->name,
                        'at' => $entry->created_at->format('M j, Y'),
                        'reinstatedAt' => $entry->reinstated_at?->format('M j, Y'),
                        'reinstateNotes' => $entry->reinstate_notes,
                    ])->all(),
                'copies' => [
                    'count' => $otherCopies->count() + 1,
                    'others' => $otherCopies->values()->all(),
                ],
                'processing' => $lastJob === null ? null : [
                    'status' => $lastJob->status,
                    'model' => $lastJob->model,
                    'error' => $lastJob->error_message,
                    'finishedAt' => $lastJob->finished_at?->format('M j, Y g:i A'),
                    'logs' => $lastJob->logs()->orderBy('id')->get()
                        ->map(fn ($log) => [
                            'level' => $log->level,
                            'message' => $log->message,
                            'at' => $log->created_at?->format('M j, Y g:i A'),
                        ])->all(),
                ],
            ],
        ]);
    }

    /**
     * The AI ballpark is the model's judgment, not a lookup — it has no
     * sources to cite. These pre-filled searches are how an admin audits
     * it against the live market in one tap (owner request).
     *
     * @return array<int, array{label: string, url: string}>
     */
    private function marketCheckLinks(?\App\Models\Metadata $metadata): array
    {
        if ($metadata === null) {
            return [];
        }

        $query = $metadata->primaryTitle();
        if ($query === "Item #{$metadata->item_id}") {
            return []; // Nothing identifying to search with yet.
        }

        if ($metadata->category === 'sports_card' && filled($metadata->card_number)) {
            $query .= ' #'.$metadata->card_number;
        }

        $links = [[
            'label' => 'eBay solds',
            'url' => 'https://www.ebay.com/sch/i.html?LH_Sold=1&LH_Complete=1&_nkw='.urlencode($query),
        ]];

        if ($metadata->category === 'sports_card') {
            $links[] = [
                'label' => 'SportsCardsPro',
                'url' => 'https://www.sportscardspro.com/search-products?q='.urlencode($query).'&type=prices',
            ];
        }

        return $links;
    }
}
