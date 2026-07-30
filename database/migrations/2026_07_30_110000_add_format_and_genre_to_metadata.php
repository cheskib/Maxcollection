<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Comic book categories (owner-approved): Format (regular issue, annual,
// TPB, ...) and Genre (superhero, horror, ...). Age is not a column — it
// derives from the year, so the year stays the single source of truth.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->string('format')->nullable();
            $table->string('genre')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->dropColumn(['format', 'genre']);
        });
    }
};
