<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed application configuration. PROJECT.md section 11 fixes the MVP
     * confidence threshold at 75 percent.
     */
    public function run(): void
    {
        Setting::updateOrCreate(
            ['key' => 'confidence_threshold'],
            ['value' => '75']
        );
    }
}
