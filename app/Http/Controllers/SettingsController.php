<?php

namespace App\Http\Controllers;

use App\Models\KeyName;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(\App\Services\DropboxService $dropbox): Response
    {
        return Inertia::render('Settings', [
            'confidenceThreshold' => (int) Setting::value('confidence_threshold', '75'),
            // Informational: the models are configured on the server.
            'standardModel' => (string) config('services.openai.model'),
            'premiumModel' => (string) config('services.openai.premium_model'),
            'marketConfigured' => filled(config('services.pricecharting.token')),
            'dropbox' => [
                'configured' => $dropbox->configured(),
                'connected' => $dropbox->connected(),
                'connectedAt' => $dropbox->connectedAt(),
                'archivedCount' => \App\Models\Batch::whereNotNull('archived_at')->count(),
                'pendingCount' => \App\Models\Batch::whereNotNull('barcode_id')->whereNull('archived_at')->count(),
            ],
            'users' => \App\Models\User::orderBy('name')->get(['id', 'name', 'email', 'role'])->all(),
            'keyNames' => KeyName::orderBy('sport')->orderBy('name')
                ->get(['id', 'sport', 'name'])
                ->groupBy('sport')
                ->map(fn ($group) => $group->map(fn (KeyName $entry) => ['id' => $entry->id, 'name' => $entry->name])->values())
                ->all(),
        ]);
    }

    /**
     * Add a name to the watchlist and flag any existing matching cards.
     */
    public function addKeyName(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sport' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:128'],
        ]);

        KeyName::firstOrCreate(['sport' => trim($validated['sport']), 'name' => trim($validated['name'])]);
        KeyName::forgetCache();

        DB::table('metadata')
            ->whereRaw('lower(player_name) like ?', ['%'.mb_strtolower(trim($validated['name'])).'%'])
            ->update(['key_card' => true]);

        return back()->with('status', 'Name added to the watchlist.');
    }

    /**
     * Remove a name and re-evaluate cards it had flagged.
     */
    public function removeKeyName(KeyName $keyName): RedirectResponse
    {
        $name = $keyName->name;
        $keyName->delete();
        KeyName::forgetCache();

        $affected = DB::table('metadata')
            ->where('key_card', true)
            ->whereRaw('lower(player_name) like ?', ['%'.mb_strtolower($name).'%'])
            ->get(['id', 'player_name']);

        foreach ($affected as $row) {
            if (! KeyName::matches($row->player_name)) {
                DB::table('metadata')->where('id', $row->id)->update(['key_card' => false]);
            }
        }

        return back()->with('status', 'Name removed from the watchlist.');
    }

    /**
     * Create a login: admins manage the collection; scanners digitize
     * and pack only.
     */
    public function addUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'role' => ['required', \Illuminate\Validation\Rule::in([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SCANNER])],
        ]);

        \App\Models\User::create($validated);

        return back()->with('status', 'Account created.');
    }

    public function updateUserRole(Request $request, \App\Models\User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', \Illuminate\Validation\Rule::in([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_SCANNER])],
        ]);

        // Never demote yourself into a lockout: someone must stay admin.
        if ($user->id === $request->user()->id && $validated['role'] !== \App\Models\User::ROLE_ADMIN) {
            return back()->with('status', 'You cannot remove your own admin access.');
        }

        $user->update(['role' => $validated['role']]);

        return back()->with('status', "{$user->name} is now a {$validated['role']}.");
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confidence_threshold' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'confidence_threshold'],
            ['value' => (string) $validated['confidence_threshold']],
        );

        return back()->with('status', 'Settings saved.');
    }
}
