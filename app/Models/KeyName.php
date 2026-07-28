<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The key-names watchlist: star players per sport whose cards deserve a
 * human look the moment they enter inventory — flagged by name match,
 * independent of what the AI thinks the card is worth.
 */
class KeyName extends Model
{
    protected $fillable = ['sport', 'name'];

    /** @var array<int, string>|null request-lifetime cache */
    protected static ?array $cachedNames = null;

    public static function forgetCache(): void
    {
        static::$cachedNames = null;
    }

    /**
     * Does this player name (or multi-player text) contain a key name?
     */
    public static function matches(?string $playerName): bool
    {
        if ($playerName === null || trim($playerName) === '') {
            return false;
        }

        static::$cachedNames ??= static::query()->pluck('name')
            ->map(fn (string $name) => Str::lower($name))
            ->all();

        $haystack = Str::lower($playerName);

        foreach (static::$cachedNames as $name) {
            if (str_contains($haystack, $name)) {
                return true;
            }
        }

        return false;
    }
}
