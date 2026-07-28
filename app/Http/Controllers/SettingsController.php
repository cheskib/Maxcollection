<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
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
