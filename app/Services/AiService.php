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
            $binary = Storage::disk('local')->get($image->path);

            if ($binary === null) {
                continue;
            }

            // Send a downscaled derived copy to keep image token cost low
            // (ARCHITECTURE.md 7: derived images for AI optimization).
            // Originals are never modified.
            [$binary, $mime] = $this->optimizeForAi($binary, $image->mime_type);

            $content[] = [
                'type' => 'input_image',
                'image_url' => 'data:'.$mime.';base64,'.base64_encode($binary),
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

    /**
     * Downscale an image for the AI request. Card text stays perfectly
     * legible at this size while image token cost drops sharply.
     *
     * @return array{0: string, 1: string} [binary, mime type]
     */
    private function optimizeForAi(string $binary, string $mime): array
    {
        $maxEdge = 1024;

        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return [$binary, $mime];
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $longest = max($width, $height);

        if ($longest > $maxEdge) {
            $scale = $maxEdge / $longest;
            $resized = imagescale($source, (int) round($width * $scale), (int) round($height * $scale));

            if ($resized !== false) {
                imagedestroy($source);
                $source = $resized;
            }
        }

        ob_start();
        imagejpeg($source, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($source);

        return $jpeg === false || $jpeg === '' ? [$binary, $mime] : [$jpeg, 'image/jpeg'];
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
            ],
            'required' => ['category', 'confidence', 'fields'],
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

        return new AiResult($category, $confidence, $fields);
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
