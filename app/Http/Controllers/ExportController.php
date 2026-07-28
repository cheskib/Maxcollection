<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Full-collection CSV export (Excel-ready): the owner's backup,
 * insurance documentation, and guarantee the data can always leave.
 * Streams in chunks so it works at any collection size.
 */
class ExportController extends Controller
{
    public function csv(): StreamedResponse
    {
        $filename = 'max-collection-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens accents correctly.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Item ID', 'Status', 'Collection', 'Batch', 'Category', 'Card Type', 'Player Name', 'Team',
                'Sport', 'Year', 'Manufacturer', 'Card Number', 'Rookie Card', 'Autograph', 'Parallel',
                'Serial Number', 'Original Card Year', 'Condition Notes', 'Confidence', 'Key Card',
                'Our Value From', 'Our Value To', 'Purchase Price', 'Insurance Value',
                'AI Value From', 'AI Value To',
                'Market Value', 'Market Match', 'Market Checked', 'Photos', 'Uploaded At', 'Processed At',
            ]);

            Item::with(['metadata', 'collection', 'batch'])
                ->withCount('images')
                ->chunkById(500, function ($items) use ($out) {
                    foreach ($items as $item) {
                        $metadata = $item->metadata;

                        fputcsv($out, [
                            $item->id,
                            $item->status,
                            $item->collection?->name,
                            $item->batch?->displayLabel(),
                            $metadata?->categoryLabel(),
                            $metadata?->card_type,
                            $metadata?->player_name,
                            $metadata?->team,
                            $metadata?->sport,
                            $metadata?->year,
                            $metadata?->manufacturer,
                            $metadata?->card_number,
                            $metadata?->rookie_card,
                            $metadata?->autograph,
                            $metadata?->parallel,
                            $metadata?->serial_number,
                            $metadata?->original_year,
                            $metadata?->condition_notes,
                            $metadata?->confidence,
                            $metadata?->key_card ? 'Yes' : 'No',
                            $metadata?->value_from,
                            $metadata?->value_to,
                            $metadata?->purchase_price,
                            $metadata?->insurance_value,
                            $metadata?->ai_value_from,
                            $metadata?->ai_value_to,
                            $metadata?->market_value,
                            $metadata?->market_match,
                            $metadata?->market_checked_at?->format('Y-m-d'),
                            $item->images_count,
                            $item->created_at?->format('Y-m-d H:i'),
                            $item->processed_at?->format('Y-m-d H:i'),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
