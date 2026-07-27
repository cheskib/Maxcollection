<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Subset/insert designation (All-Star, Reprint, Record Breaker, ...) and,
// for reprints, the year of the card being reproduced. `year` remains the
// set/release year.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->string('card_type')->nullable()->after('set_name');
            $table->string('original_year')->nullable()->after('card_type');
        });
    }

    public function down(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->dropColumn(['card_type', 'original_year']);
        });
    }
};
