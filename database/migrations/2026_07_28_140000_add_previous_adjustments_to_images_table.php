<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Snapshot of a photo's rotation/tilt/trim taken before an AI pass changes
// them, so a bad AI cleanup can be undone with one tap.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->json('previous_adjustments')->nullable()->after('crop_left');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('previous_adjustments');
        });
    }
};
