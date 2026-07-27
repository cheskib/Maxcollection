<?php

namespace App\Services;

use App\Models\Metadata;

class AiResult
{
    public const CATEGORIES = ['sports_card', 'comic_book', 'coin', 'stamp', 'unsupported'];

    /**
     * The field that must be present for a result to be usable in listings.
     * When every primary field is blank the item needs manual review.
     *
     * @var array<string, array<int, string>>
     */
    private const PRIMARY_FIELDS = [
        'sports_card' => ['player_name'],
        'comic_book' => ['title'],
        'coin' => ['country', 'denomination'],
        'stamp' => ['country', 'issue_name'],
    ];

    /**
     * @param array<string, string|null> $fields metadata columns for the category
     * @param array<int, int> $rotations additional clockwise degrees per photo,
     *                                   in the order the photos were sent
     * @param array<int, string> $roles which side each photo shows
     *                                  (front|back|detail|unknown)
     */
    public function __construct(
        public readonly string $category,
        public readonly float $confidence,
        public readonly array $fields,
        public readonly array $rotations = [],
        public readonly array $roles = [],
    ) {
    }

    /**
     * Why this item needs manual review, or null when it does not
     * (PROJECT.md section 17).
     */
    public function reviewReason(float $confidenceThreshold): ?string
    {
        if ($this->category === 'unsupported') {
            return 'unsupported_category';
        }

        if ($this->confidence < $confidenceThreshold) {
            return 'low_confidence';
        }

        // A checklist card legitimately has no player name, so the primary
        // field requirement does not apply to it.
        if ($this->category === 'sports_card' && strtolower((string) ($this->fields['card_type'] ?? '')) === 'checklist') {
            return null;
        }

        $primary = self::PRIMARY_FIELDS[$this->category] ?? [];
        $hasPrimary = collect($primary)->contains(fn (string $field) => filled($this->fields[$field] ?? null));

        if ($primary !== [] && ! $hasPrimary) {
            return 'missing_metadata';
        }

        return null;
    }

    /**
     * Keep only fields that belong to the identified category, normalizing
     * blanks to null so the AI never invents values (PROJECT.md section 12).
     *
     * @param array<string, mixed> $rawFields
     * @return array<string, string|null>
     */
    public static function filterFields(string $category, array $rawFields): array
    {
        $allowed = Metadata::CATEGORY_FIELDS[$category] ?? [];

        $fields = [];
        foreach ($allowed as $field) {
            $value = $rawFields[$field] ?? null;
            $value = is_scalar($value) ? trim((string) $value) : null;
            $fields[$field] = $value === '' ? null : $value;
        }

        return $fields;
    }
}
