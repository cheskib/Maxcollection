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
    public function index(): Response
    {
        return Inertia::render('Settings', [
            'confidenceThreshold' => (int) Setting::value('confidence_threshold', '75'),
            // Informational: the models are configured on the server.
            'standardModel' => (string) config('services.openai.model'),
            'premiumModel' => (string) config('services.openai.premium_model'),
            'marketConfigured' => filled(config('services.pricecharting.token')),
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
