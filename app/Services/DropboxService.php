<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Dropbox connection for the off-site archive of original images.
 *
 * Connected once via OAuth (token_access_type=offline): the stored
 * refresh token is exchanged for short-lived access tokens automatically,
 * so the connection never expires unless the owner revokes it.
 */
class DropboxService
{
    /** Whether the server has app credentials configured. */
    public function configured(): bool
    {
        return filled(config('services.dropbox.key')) && filled(config('services.dropbox.secret'));
    }

    /** Whether the owner has approved the connection. */
    public function connected(): bool
    {
        return $this->configured() && filled(Setting::value('dropbox_refresh_token'));
    }

    public function connectedAt(): ?string
    {
        return Setting::value('dropbox_connected_at');
    }

    public function authorizeUrl(): string
    {
        return 'https://www.dropbox.com/oauth2/authorize?'.http_build_query([
            'client_id' => config('services.dropbox.key'),
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'redirect_uri' => route('settings.dropbox.callback'),
        ]);
    }

    /**
     * Exchange the one-time authorization code for the permanent
     * refresh token.
     */
    public function connect(string $code): void
    {
        $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => config('services.dropbox.key'),
            'client_secret' => config('services.dropbox.secret'),
            'redirect_uri' => route('settings.dropbox.callback'),
        ]);

        $refreshToken = $response->json('refresh_token');

        if (! $response->successful() || blank($refreshToken)) {
            throw new RuntimeException('Dropbox did not accept the connection: '.($response->json('error_description') ?? $response->status()));
        }

        Setting::updateOrCreate(['key' => 'dropbox_refresh_token'], ['value' => $refreshToken]);
        Setting::updateOrCreate(['key' => 'dropbox_connected_at'], ['value' => now()->toDateTimeString()]);
        Cache::forget('dropbox_access_token');
    }

    public function disconnect(): void
    {
        Setting::where('key', 'dropbox_refresh_token')->delete();
        Setting::where('key', 'dropbox_connected_at')->delete();
        Cache::forget('dropbox_access_token');
    }

    /**
     * Upload one file into the app folder, overwriting any earlier copy so
     * retries are always safe.
     */
    public function upload(string $path, string $contents): void
    {
        $response = Http::withToken($this->accessToken())
            ->withHeaders([
                'Dropbox-API-Arg' => json_encode(['path' => $path, 'mode' => 'overwrite', 'mute' => true]),
                'Content-Type' => 'application/octet-stream',
            ])
            ->withBody($contents, 'application/octet-stream')
            ->post('https://content.dropboxapi.com/2/files/upload');

        if (! $response->successful()) {
            throw new RuntimeException("Dropbox upload failed for {$path}: ".$response->body());
        }
    }

    /**
     * A short-lived access token, cached until shortly before it expires.
     */
    private function accessToken(): string
    {
        $cached = Cache::get('dropbox_access_token');
        if ($cached !== null) {
            return $cached;
        }

        $refreshToken = Setting::value('dropbox_refresh_token');
        if (blank($refreshToken)) {
            throw new RuntimeException('Dropbox is not connected.');
        }

        $response = Http::asForm()->post('https://api.dropboxapi.com/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => config('services.dropbox.key'),
            'client_secret' => config('services.dropbox.secret'),
        ]);

        $token = $response->json('access_token');

        if (! $response->successful() || blank($token)) {
            throw new RuntimeException('Dropbox token refresh failed: '.($response->json('error_description') ?? $response->status()));
        }

        Cache::put('dropbox_access_token', $token, max(60, (int) $response->json('expires_in', 14400) - 300));

        return $token;
    }
}
