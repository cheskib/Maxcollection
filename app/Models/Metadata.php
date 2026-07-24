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
        'sports_card' => [
            'player_name', 'team', 'sport', 'year', 'manufacturer', 'set_name',
            'card_number', 'rookie_card', 'parallel', 'serial_number', 'autograph',
            'condition_notes',
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

    protected $fillable = [
        'item_id', 'category', 'confidence',
        'player_name', 'team', 'sport', 'year', 'manufacturer', 'set_name',
        'card_number', 'rookie_card', 'parallel', 'serial_number', 'autograph',
        'title', 'issue_number', 'publisher', 'variant',
        'country', 'denomination', 'mint_mark', 'composition', 'issue_name', 'color',
        'condition_notes',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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
            'sports_card' => collect([$this->year, $this->manufacturer, $this->player_name])->filter()->implode(' '),
            'comic_book' => collect([$this->title, $this->issue_number ? "#{$this->issue_number}" : null])->filter()->implode(' '),
            'coin' => collect([$this->year, $this->country, $this->denomination])->filter()->implode(' '),
            'stamp' => collect([$this->country, $this->issue_name, $this->year])->filter()->implode(' '),
            default => '',
        };

        return $title !== '' ? $title : "Item #{$this->item_id}";
    }
}
