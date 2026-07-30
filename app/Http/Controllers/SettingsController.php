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
            // Where every scan-line batch lands until an admin changes it.
            'collections' => \App\Models\Collection::orderBy('name')->get(['id', 'name'])->all(),
            'defaultCollectionId' => Setting::value('default_collection_id') !== null
                ? (int) Setting::value('default_collection_id')
                : null,
            'aiHold' => Setting::value('ai_hold') === '1',
            'queuedCount' => \App\Models\Item::whereIn('status', [\App\Models\Item::STATUS_QUEUED, \App\Models\Item::STATUS_PROCESSING])->count(),
            'stations' => \App\Models\Station::orderBy('name')->get()->map(fn (\App\Models\Station $station) => [
                'id' => $station->id,
                'name' => $station->name,
                'type' => $station->type,
                'tokenLast4' => $station->token_last4,
                'lastSeen' => $station->last_seen_at?->diffForHumans(),
                'revoked' => $station->revoked_at !== null,
                'fileCount' => $station->ingestFiles()->count(),
            ])->all(),
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
     * Choose the collection every scan-line batch lands in from this
     * moment forward. Forward-only: nothing already processed moves.
     */
    public function setDefaultCollection(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collection_id' => ['required', 'integer', 'exists:collections,id'],
        ]);

        Setting::updateOrCreate(
            ['key' => 'default_collection_id'],
            ['value' => (string) $validated['collection_id']],
        );

        $name = \App\Models\Collection::find($validated['collection_id'])->name;

        return back()->with('status', "New scans will go into \"{$name}\".");
    }

    /**
     * Pause or resume all AI work. Scanning and validation continue
     * either way — they follow the cards; AI follows the images and
     * can wait. Releasing the hold re-dispatches everything queued.
     */
    public function setAiHold(Request $request): RedirectResponse
    {
        $hold = $request->boolean('hold');

        Setting::updateOrCreate(['key' => 'ai_hold'], ['value' => $hold ? '1' : '0']);

        if ($hold) {
            return back()->with('status', 'AI processing is on hold. Scanning and validation continue; queued items wait.');
        }

        // Jobs consumed while held left their items queued; requeue them.
        $requeued = 0;
        \App\Models\ProcessingJob::where('status', \App\Models\ProcessingJob::STATUS_QUEUED)
            ->pluck('id')
            ->each(function (int $jobId) use (&$requeued) {
                \App\Jobs\ProcessItemJob::dispatch($jobId);
                $requeued++;
            });

        return back()->with('status', $requeued > 0
            ? "AI processing resumed — {$requeued} item(s) picking back up."
            : 'AI processing resumed.');
    }

    /**
     * Register a scan station and issue its token. The token type routes
     * arriving files into the right pipeline (cards vs comics).
     */
    public function addStation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'type' => ['required', \Illuminate\Validation\Rule::in([\App\Models\Station::TYPE_CARDS, \App\Models\Station::TYPE_COMICS])],
        ]);

        $station = \App\Models\Station::issue(trim($validated['name']), $validated['type']);

        return back()->with('status', "Station \"{$station->name}\" registered — download its config file below.");
    }

    /**
     * Kill switch for one station: its token stops working immediately;
     * nothing else is touched.
     */
    public function revokeStation(\App\Models\Station $station): RedirectResponse
    {
        $station->update(['revoked_at' => now()]);

        return back()->with('status', "Station \"{$station->name}\" revoked.");
    }

    /**
     * The uploader agent's config file, pre-filled with this station's
     * token and the server address. Placed next to the agent executable.
     */
    public function stationConfig(\App\Models\Station $station): \Symfony\Component\HttpFoundation\Response
    {
        if ($station->revoked_at !== null) {
            abort(410, 'This station has been revoked.');
        }

        $config = [
            'server' => rtrim(config('app.url'), '/'),
            'token' => $station->token,
            'watch_dir' => 'C:\\MaxCollection\\scans',
            'sent_dir' => 'C:\\MaxCollection\\sent',
        ];

        return response(json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="uploader.json"',
        ]);
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
