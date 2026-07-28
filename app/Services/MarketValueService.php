<?php

namespace App\Services;

use App\Models\Metadata;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live market pricing via the PriceCharting API (which also powers
 * SportsCardsPro). One provider, configured by PRICECHARTING_TOKEN.
 */
class MarketValueService
{
    public function isConfigured(): bool
    {
        return filled(config('services.pricecharting.token'));
    }

    /**
     * Look up the card's market price and store it on the metadata.
     * Returns the matched product name, or null when nothing matched.
     */
    public function refresh(Metadata $metadata): ?string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Market pricing is not configured — add the PRICECHARTING_TOKEN variable.');
        }

        $query = collect([
            $metadata->year,
            $metadata->manufacturer,
            $metadata->player_name,
            filled($metadata->card_number) ? "#{$metadata->card_number}" : null,
        ])->filter()->implode(' ');

        if (trim($query) === '') {
            return null;
        }

        $response = Http::timeout(20)->get('https://www.pricecharting.com/api/product', [
            't' => config('services.pricecharting.token'),
            'q' => $query,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('The price service could not be reached (HTTP '.$response->status().').');
        }

        $body = $response->json();

        // "loose-price" is the ungraded card price, in pennies.
        $pennies = $body['loose-price'] ?? null;
        $product = $body['product-name'] ?? null;

        if (! is_numeric($pennies) || ! is_string($product)) {
            return null;
        }

        $metadata->update([
            'market_value' => round(((float) $pennies) / 100, 2),
            'market_match' => $product,
            'market_checked_at' => now(),
        ]);

        return $product;
    }
}
