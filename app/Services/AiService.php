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

    public function identify(Item $item, ProcessingJob $job, string $tier = self::TIER_STANDARD): AiResult
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
            ->post(rtrim(config('services.openai.base_url'), '/').'/responses', $this->buildPayload($item, $model));

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
    private function buildPayload(Item $item, string $model): array
    {
        $content = [['type' => 'input_text', 'text' => $this->prompt()]];

        $attached = 0;

        foreach ($item->images()->orderBy('id')->get() as $image) {
            // Send the displayed rendering (rotation + trim), downscaled to
            // keep image token cost low (ARCHITECTURE.md 7). Originals are
            // never modified.
            $binary = $this->renderer->render($image, 1024);

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

Report an overall confidence score from 0 to 100 for the identification and
extracted metadata as a whole.

Also report "rotations": for each photograph, in the order provided, the
clockwise rotation in degrees (0, 90, 180, or 270) needed so the item is
correctly oriented — the front standing upright, and the back turned so its
text reads normally (card backs are often printed in landscape).

Also report "trims": for each photograph, in the same order and AFTER the
rotation above is applied, the percentage of each edge (top, right, bottom,
left; whole numbers 0-45) that is background — hands, table, scanner bed —
around the item. Trimming those edges should leave just the item with a
small margin. Be conservative: never cut into the item itself, and use 0
for every edge when the item already fills the photo or you are unsure.
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
                'trims' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'top' => ['type' => 'integer'],
                            'right' => ['type' => 'integer'],
                            'bottom' => ['type' => 'integer'],
                            'left' => ['type' => 'integer'],
                        ],
                        'required' => ['top', 'right', 'bottom', 'left'],
                    ],
                ],
            ],
            'required' => ['category', 'confidence', 'fields', 'rotations', 'trims'],
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

        $trims = collect((array) ($decoded['trims'] ?? []))
            ->map(function ($trim) {
                $clamp = fn ($value) => is_numeric($value) ? max(0, min(45, (int) $value)) : 0;

                return [
                    'top' => $clamp($trim['top'] ?? 0),
                    'right' => $clamp($trim['right'] ?? 0),
                    'bottom' => $clamp($trim['bottom'] ?? 0),
                    'left' => $clamp($trim['left'] ?? 0),
                ];
            })
            ->values()
            ->all();

        return new AiResult($category, $confidence, $fields, $rotations, $trims);
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
