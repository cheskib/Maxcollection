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
            // Lengths kept short so the four-column unique index stays
            // under MySQL's 3072-byte key limit (full-length strings
            // exceed it and fail the migration in production).
            $table->string('sport', 64)->default('');
            $table->string('manufacturer', 128);
            $table->string('year', 16);
            $table->string('set_name', 128)->default('');
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
