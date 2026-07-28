<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CardSet extends Model
{
    protected $fillable = ['sport', 'manufacturer', 'year', 'set_name', 'description'];

    /**
     * How the set reads in listings: "1987 Topps Baseball". A set is
     * defined by manufacturer + year + sport (owner decision — set names
     * added nothing for this collection and multiplied profiles).
     */
    public function displayName(): string
    {
        return collect([$this->year, $this->manufacturer, $this->sport])->filter()->implode(' ');
    }

    /**
     * Membership is derived from metadata, never stored: editing a card's
     * manufacturer or year automatically moves it to the right set.
     */
    public function cardsQuery(): Builder
    {
        return Metadata::query()
            ->where('category', 'sports_card')
            ->where('manufacturer', $this->manufacturer)
            ->where('year', $this->year)
            ->when($this->sport !== '', fn ($query) => $query->where('sport', $this->sport))
            ->whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED));
    }
}
