<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Station extends Model
{
    public const TYPE_CARDS = 'cards';

    public const TYPE_COMICS = 'comics';

    protected $fillable = ['name', 'type', 'token', 'token_hash', 'token_last4', 'last_seen_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function ingestFiles(): HasMany
    {
        return $this->hasMany(IngestFile::class);
    }

    /**
     * Create a station with a fresh token. The plaintext token lives only
     * in the encrypted column; lookups go through the hash.
     */
    public static function issue(string $name, string $type): self
    {
        $token = 'mxc_'.Str::random(48);

        return self::create([
            'name' => $name,
            'type' => $type,
            'token' => $token,
            'token_hash' => hash('sha256', $token),
            'token_last4' => substr($token, -4),
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }

        return self::where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();
    }
}
