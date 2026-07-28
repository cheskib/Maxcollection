<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metadata extends Model
{
    // "metadata" does not pluralize, so name the table explicitly.
    protected $table = 'metadata';

    /**
     * Every editable field from PROJECT.md section 12, by category.
     *
     * @var array<string, array<int, string>>
     */
    public const CATEGORY_FIELDS = [
        // Ordered as displayed: card type first (right below the category
        // it refines), the identifying fields through condition notes,
        // then the misc data at the very bottom.
        'sports_card' => [
            'card_type',
            'player_name', 'team', 'sport', 'year', 'manufacturer',
            'card_number', 'condition_notes',
            'rookie_card', 'autograph', 'original_year',
            'parallel', 'serial_number',
        ],
        'comic_book' => ['title', 'issue_number', 'publisher', 'year', 'variant', 'condition_notes'],
        'coin' => ['country', 'denomination', 'year', 'mint_mark', 'composition', 'condition_notes'],
        'stamp' => ['country', 'issue_name', 'year', 'color', 'denomination', 'condition_notes'],
    ];

    /**
     * Display labels for every editable field.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'player_name' => 'Player Name',
        'team' => 'Team',
        'sport' => 'Sport',
        'year' => 'Year',
        'manufacturer' => 'Manufacturer',
        'set_name' => 'Set Name',
        'card_type' => 'Card Type',
        'original_year' => 'Original Card Year',
        'card_number' => 'Card Number',
        'rookie_card' => 'Rookie Card',
        'parallel' => 'Parallel',
        'serial_number' => 'Serial Number',
        'autograph' => 'Autograph',
        'title' => 'Title',
        'issue_number' => 'Issue Number',
        'publisher' => 'Publisher',
        'variant' => 'Variant',
        'country' => 'Country',
        'denomination' => 'Denomination',
        'mint_mark' => 'Mint Mark',
        'composition' => 'Composition',
        'issue_name' => 'Issue Name',
        'color' => 'Color',
        'condition_notes' => 'Condition Notes',
    ];

    /**
     * The owner's manual value range; applies to every category and is
     * never written by the AI.
     *
     * @var array<int, string>
     */
    public const VALUE_FIELDS = ['value_from', 'value_to'];

    protected $fillable = [
        'item_id', 'category', 'confidence',
        'value_from', 'value_to', 'ai_value_from', 'ai_value_to',
        'market_value', 'market_match', 'market_checked_at',
        'player_name', 'team', 'sport', 'year', 'manufacturer', 'set_name',
        'card_type', 'original_year',
        'card_number', 'rookie_card', 'parallel', 'serial_number', 'autograph',
        'title', 'issue_number', 'publisher', 'variant',
        'country', 'denomination', 'mint_mark', 'composition', 'issue_name', 'color',
        'condition_notes',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'value_from' => 'float',
            'value_to' => 'float',
            'ai_value_from' => 'float',
            'ai_value_to' => 'float',
            'market_value' => 'float',
            'market_checked_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Normalize AI-written card types so variants land in one bucket:
     * "{Team} Leaders" is the Team Leaders type (the team itself belongs
     * in the team field), league-level leader cards are League Leaders,
     * and "All Star" spellings collapse to "All-Star".
     */
    public static function normalizeCardType(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        $trimmed = trim($value);
        $lower = strtolower($trimmed);

        if (preg_match('/leaders?$/', $lower)) {
            $isLeague = (bool) preg_match('/^(league|nl|al|n\.l\.|a\.l\.|national league|american league)\s+leaders?$/', $lower);

            return $isLeague ? 'League Leaders' : 'Team Leaders';
        }

        if (in_array($lower, ['all star', 'all-star', 'allstar'], true)) {
            return 'All-Star';
        }

        return $trimmed;
    }

    /**
     * Human-readable category name for display.
     */
    public function categoryLabel(): string
    {
        return match ($this->category) {
            'sports_card' => 'Sports Card',
            'comic_book' => 'Comic Book',
            'coin' => 'Coin',
            'stamp' => 'Stamp',
            'unsupported' => 'Unsupported',
            default => 'Unknown',
        };
    }

    /**
     * The primary display title for listings, built from the fields that
     * identify an item within its category.
     */
    public function primaryTitle(): string
    {
        $title = match ($this->category) {
            // Subset cards read like collectors say them: "1987 Topps
            // All-Star Don Mattingly". Base cards omit the type.
            'sports_card' => collect([
                $this->year,
                $this->manufacturer,
                $this->card_type && strtolower($this->card_type) !== 'base' ? $this->card_type : null,
                $this->player_name,
            ])->filter()->implode(' '),
            'comic_book' => collect([$this->title, $this->issue_number ? "#{$this->issue_number}" : null])->filter()->implode(' '),
            'coin' => collect([$this->year, $this->country, $this->denomination])->filter()->implode(' '),
            'stamp' => collect([$this->country, $this->issue_name, $this->year])->filter()->implode(' '),
            default => '',
        };

        return $title !== '' ? $title : "Item #{$this->item_id}";
    }
}
