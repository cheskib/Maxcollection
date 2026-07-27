<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CardSet extends Model
{
    protected $fillable = ['sport', 'manufacturer', 'year', 'set_name', 'description'];

    /**
     * How the set reads in listings: "1987 Topps Baseball" or
     * "1987 Topps Baseball — Traded".
     */
    public function displayName(): string
    {
        $name = collect([$this->year, $this->manufacturer, $this->sport])->filter()->implode(' ');

        return $this->set_name !== '' ? "{$name} — {$this->set_name}" : $name;
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
            ->when($this->set_name !== '', fn ($query) => $query->where('set_name', $this->set_name))
            ->whereHas('item', fn ($query) => $query->where('status', Item::STATUS_PROCESSED));
    }
}
