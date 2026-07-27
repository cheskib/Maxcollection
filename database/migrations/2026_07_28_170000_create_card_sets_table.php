<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A self-building catalog of card sets: one profile per sport/manufacturer/
// year (/set name) seen in the collection, with an AI-written design history.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_sets', function (Blueprint $table) {
            $table->id();
            // Empty strings (not nulls) so the unique key always applies.
            $table->string('sport')->default('');
            $table->string('manufacturer');
            $table->string('year');
            $table->string('set_name')->default('');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['sport', 'manufacturer', 'year', 'set_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_sets');
    }
};
