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
}
