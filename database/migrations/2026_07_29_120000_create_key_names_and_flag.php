<?php

use App\Models\KeyName;
use Database\Seeders\KeyNamesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The key-names watchlist plus the per-card flag it drives, seeded with
// the starter list and backfilled over existing cards.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_names', function (Blueprint $table) {
            $table->id();
            $table->string('sport', 64);
            $table->string('name', 128);
            $table->timestamps();

            $table->unique(['sport', 'name']);
        });

        Schema::table('metadata', function (Blueprint $table) {
            $table->boolean('key_card')->default(false)->index();
        });

        (new KeyNamesSeeder)->run();
        KeyName::forgetCache();

        // Backfill: flag existing cards whose player matches the list.
        foreach (DB::table('metadata')->whereNotNull('player_name')->get(['id', 'player_name']) as $row) {
            if (KeyName::matches($row->player_name)) {
                DB::table('metadata')->where('id', $row->id)->update(['key_card' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->dropColumn('key_card');
        });
        Schema::dropIfExists('key_names');
    }
};
