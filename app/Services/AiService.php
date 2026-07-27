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
            'required' => ['category', 'confidence', 'fields', 'rotations', 'roles'],
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

        return new AiResult($category, $confidence, $fields, $rotations, $roles);
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
