<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Metadata;
use App\Models\ProcessingJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The single OpenAI integration point for the application. One provider,
 * one model, structured JSON output (CLAUDE.md section 11).
 */
class AiService
{
    public function __construct(private readonly ImageRenderService $renderer)
    {
    }

    public const TIER_STANDARD = 'standard';

    public const TIER_PREMIUM = 'premium';

    public const SOURCE_CLEANED = 'cleaned';

    public const SOURCE_ORIGINAL = 'original';

    public function identify(Item $item, ProcessingJob $job, string $tier = self::TIER_STANDARD, string $source = self::SOURCE_CLEANED): AiResult
    {
        $apiKey = config('services.openai.key');

        if (blank($apiKey)) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = $tier === self::TIER_PREMIUM
            ? config('services.openai.premium_model')
            : config('services.openai.model');

        $job->update(['model' => $model]);

        $response = Http::withToken($apiKey)
            ->timeout((int) config('services.openai.timeout'))
            ->post(rtrim(config('services.openai.base_url'), '/').'/responses', $this->buildPayload($item, $model, $source));

        // Store the response exactly as returned before any parsing (DECISIONS.md).
        $job->update(['raw_response' => $response->body()]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI request failed with HTTP status '.$response->status().'.');
        }

        return $this->parseResponse($response->json());
    }

    /**
     * Write a short design history for a card set (Sets catalog). Text-only
     * call on the standard model; returns null when unavailable so set
     * creation never blocks item processing.
     */
    public function describeSet(\App\Models\CardSet $set): ?string
    {
        $apiKey = config('services.openai.key');

        if (blank($apiKey)) {
            return null;
        }

        $prompt = sprintf(
            'Write a short design history (4-6 sentences) of the "%s" sports card set for a collector\'s catalog. '
            .'Cover: what the card fronts and backs look like (borders, colors, layout), notable subsets or inserts '
            .'(All-Star, Record Breaker, rookies), a few key cards or rookies, and how to recognize a card from this set at a glance. '
            .'Only state facts you are confident about; if you are unsure about this exact set, describe what is reliably known '
            .'for this manufacturer and year instead, without inventing specifics.',
            $set->displayName(),
        );

        $response = Http::withToken($apiKey)
            ->timeout((int) config('services.openai.timeout'))
            ->post(rtrim(config('services.openai.base_url'), '/').'/responses', [
                'model' => config('services.openai.model'),
                'input' => [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $prompt]]]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'set_description',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => ['description' => ['type' => 'string']],
                            'required' => ['description'],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            return null;
        }

        $decoded = json_decode($this->outputText($response->json()) ?? '', true);
        $description = is_array($decoded) ? trim((string) ($decoded['description'] ?? '')) : '';

        return $description === '' ? null : $description;
    }

    /**
     * Classify scanned PDF pages as card fronts or backs, in order, so the
     * importer can pair them even when a back is missing. Returns one
     * 'front'/'back' per page, or null when classification is unavailable
     * or inconsistent — the importer then falls back to mechanical pairs.
     *
     * @param array<int, string> $binaries JPEG contents, in page order
     * @return array<int, string>|null
     */
    public function classifyPages(array $binaries): ?array
    {
        $apiKey = config('services.openai.key');

        if (blank($apiKey) || $binaries === []) {
            return null;
        }

        $roles = [];

        // Chunked so huge scans never exceed request limits.
        foreach (array_chunk($binaries, 20) as $chunk) {
            $content = [[
                'type' => 'input_text',
                'text' => sprintf(
                    'You are given %d scanned pages, in scanner order, from a stack of collectible cards. '
                    .'Classify each page, in the same order, as "front" (the face with the main photo or design) '
                    .'or "back" (the side with statistics, text blocks, card number, or copyright line).',
                    count($chunk),
                ),
            ]];

            foreach ($chunk as $binary) {
                $content[] = [
                    'type' => 'input_image',
                    'image_url' => 'data:image/jpeg;base64,'.base64_encode($this->downscaleJpeg($binary, 512)),
                ];
            }

            $response = Http::withToken($apiKey)
                ->timeout((int) config('services.openai.timeout'))
                ->post(rtrim(config('services.openai.base_url'), '/').'/responses', [
                    'model' => config('services.openai.model'),
                    'input' => [['role' => 'user', 'content' => $content]],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'page_classification',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'pages' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['front', 'back']]],
                                ],
                                'required' => ['pages'],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                return null;
            }

            $decoded = json_decode($this->outputText($response->json()) ?? '', true);
            $pages = is_array($decoded) ? ($decoded['pages'] ?? null) : null;

            if (! is_array($pages) || count($pages) !== count($chunk)) {
                return null;
            }

            foreach ($pages as $page) {
                if (! in_array($page, ['front', 'back'], true)) {
                    return null;
                }

                $roles[] = $page;
            }
        }

        return $roles;
    }

    /**
     * Small copy of a raw JPEG for cheap classification calls.
     */
    private function downscaleJpeg(string $binary, int $maxEdge): string
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return $binary;
        }

        $longest = max(imagesx($source), imagesy($source));

        if ($longest > $maxEdge) {
            $scale = $maxEdge / $longest;
            $resized = imagescale($source, (int) round(imagesx($source) * $scale), (int) round(imagesy($source) * $scale));

            if ($resized !== false) {
                imagedestroy($source);
                $source = $resized;
            }
        }

        ob_start();
        imagejpeg($source, null, 70);
        $jpeg = ob_get_clean();
        imagedestroy($source);

        return $jpeg === false || $jpeg === '' ? $binary : $jpeg;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Item $item, string $model, string $source): array
    {
        $content = [['type' => 'input_text', 'text' => $this->prompt()]];

        $attached = 0;
        $useAdjustments = $source !== self::SOURCE_ORIGINAL;

        foreach ($item->images()->orderBy('id')->get() as $image) {
            // Send the displayed rendering (rotation + trim) — or, when the
            // user chose to reprocess from originals, the untouched photo —
            // downscaled to keep image token cost low (ARCHITECTURE.md 7).
            // Original files are never modified.
            $binary = $this->renderer->render($image, 1024, applyCrop: $useAdjustments, applyRotation: $useAdjustments);

            if ($binary === null) {
                continue;
            }

            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:image/jpeg;base64,'.base64_encode($binary),
            ];
            $attached++;
        }

        // Asking the AI to identify an item it cannot see produces a
        // confident-looking "unsupported" instead of an obvious failure.
        if ($attached === 0) {
            throw new RuntimeException('No photograph files could be read for this item.');
        }

        return [
            'model' => $model,
            'input' => [['role' => 'user', 'content' => $content]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'collectible_identification',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ];
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
You are identifying a collectible from photographs for a collection catalog.

Classify the collectible into exactly one category: sports_card, comic_book, coin, or stamp.
If it is not clearly one of these four, use "unsupported".

Extract the metadata fields that apply to the identified category. Only report
values you can actually see or confidently determine from the photographs.
If a value cannot be determined, return null for that field. Never guess or
invent values.

For sports cards, be careful with years and special cards:
- "year" is the SET/RELEASE year of the card, not the year pictured on it.
  The most reliable source is the copyright line on the back (for example
  "(C) 1987 Topps"). A reprint or retro card may show a big older year on
  the front while belonging to a much newer set.
- "original_year" applies only to reprint/retro cards: the year of the old
  card being reproduced (the one shown large on the front). Null otherwise.
- "card_type" is the subset or insert designation when the card shows one:
  for example "All-Star", "Record Breaker", "Turn Back the Clock",
  "Reprint", "Highlights", "Checklist", "League Leaders". Use "Base" for a
  regular card from the main set.

Report an overall confidence score from 0 to 100 for the identification and
extracted metadata as a whole.

Also report "value_low" and "value_high": a conservative estimated resale
value range in United States dollars for this exact item in the condition
shown, based on what such items typically sell for. Plain numbers only.
Use null for BOTH when you cannot estimate responsibly.

Also report "rotations": for each photograph, in the order provided, the
clockwise rotation in degrees (0, 90, 180, or 270) needed so the item is
correctly oriented — the front standing upright, and the back turned so its
text reads normally (card backs are often printed in landscape).
Double-check for 180-degree mistakes before answering: after your chosen
rotation, faces and helmets must be upright (chin below eyes) and any
printed text on the item must read left to right, not upside down. If the
photograph is already correct, report 0 — never rotate an already-upright
photo.

Also report "roles": for each photograph, in the same order, which side of
the item it shows: "front", "back", "detail" (a close-up of one area), or
"unknown" when you cannot tell.
PROMPT;
    }

    /**
     * Strict JSON schema: one flat field set covering every category; fields
     * that do not apply are returned as null.
     *
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $fieldNames = collect(Metadata::CATEGORY_FIELDS)->flatten()->unique()->values();

        $fieldProperties = $fieldNames
            ->mapWithKeys(fn (string $field) => [$field => ['type' => ['string', 'null']]])
            ->all();

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'category' => ['type' => 'string', 'enum' => AiResult::CATEGORIES],
                'confidence' => ['type' => 'number'],
                'value_low' => ['type' => ['number', 'null']],
                'value_high' => ['type' => ['number', 'null']],
                'fields' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $fieldProperties,
                    'required' => $fieldNames->all(),
                ],
                'rotations' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer', 'enum' => [0, 90, 180, 270]],
                ],
                'roles' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => ['front', 'back', 'detail', 'unknown']],
                ],
            ],
            'required' => ['category', 'confidence', 'value_low', 'value_high', 'fields', 'rotations', 'roles'],
        ];
    }

    /**
     * Validate the AI response; it is never assumed correct (CLAUDE.md 11).
     *
     * @param array<string, mixed>|null $body
     */
    private function parseResponse(?array $body): AiResult
    {
        $text = $this->outputText($body);

        $decoded = json_decode($text ?? '', true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI response did not contain valid JSON output.');
        }

        $category = $decoded['category'] ?? null;

        if (! in_array($category, AiResult::CATEGORIES, true)) {
            throw new RuntimeException('OpenAI response contained an unknown category.');
        }

        $confidence = $decoded['confidence'] ?? null;

        if (! is_numeric($confidence)) {
            throw new RuntimeException('OpenAI response contained no numeric confidence score.');
        }

        $confidence = max(0.0, min(100.0, (float) $confidence));

        $fields = $category === 'unsupported'
            ? []
            : AiResult::filterFields($category, (array) ($decoded['fields'] ?? []));

        // Orientation and trim hints are optional and validated; anything odd
        // is ignored.
        $rotations = collect((array) ($decoded['rotations'] ?? []))
            ->map(fn ($value) => is_numeric($value) ? ((int) $value) % 360 : 0)
            ->map(fn (int $value) => in_array($value, [0, 90, 180, 270], true) ? $value : 0)
            ->values()
            ->all();

        $roles = collect((array) ($decoded['roles'] ?? []))
            ->map(fn ($value) => in_array($value, ['front', 'back', 'detail'], true) ? $value : 'unknown')
            ->values()
            ->all();

        // The ballpark is a range or nothing: both ends numeric, ordered.
        $valueLow = is_numeric($decoded['value_low'] ?? null) ? round(max(0, (float) $decoded['value_low']), 2) : null;
        $valueHigh = is_numeric($decoded['value_high'] ?? null) ? round(max(0, (float) $decoded['value_high']), 2) : null;

        if ($valueLow === null || $valueHigh === null) {
            $valueLow = $valueHigh = null;
        } elseif ($valueLow > $valueHigh) {
            [$valueLow, $valueHigh] = [$valueHigh, $valueLow];
        }

        return new AiResult($category, $confidence, $fields, $rotations, $roles, $valueLow, $valueHigh);
    }

    /**
     * Extract the text output from a Responses API body.
     *
     * @param array<string, mixed>|null $body
     */
    private function outputText(?array $body): ?string
    {
        foreach ((array) data_get($body, 'output', []) as $entry) {
            foreach ((array) data_get($entry, 'content', []) as $content) {
                if (data_get($content, 'type') === 'output_text') {
                    return data_get($content, 'text');
                }
            }
        }

        return null;
    }
}
