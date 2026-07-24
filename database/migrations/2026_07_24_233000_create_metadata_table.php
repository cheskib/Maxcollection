<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per item holding the current editable values. Columns cover
        // every field listed in PROJECT.md section 12; fields shared between
        // categories (year, country, denomination, condition notes) are stored once.
        Schema::create('metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('category')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();

            // Sports cards
            $table->string('player_name')->nullable();
            $table->string('team')->nullable();
            $table->string('sport')->nullable();
            $table->string('year')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('set_name')->nullable();
            $table->string('card_number')->nullable();
            $table->string('rookie_card')->nullable();
            $table->string('parallel')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('autograph')->nullable();

            // Comic books
            $table->string('title')->nullable();
            $table->string('issue_number')->nullable();
            $table->string('publisher')->nullable();
            $table->string('variant')->nullable();

            // Coins and stamps
            $table->string('country')->nullable();
            $table->string('denomination')->nullable();
            $table->string('mint_mark')->nullable();
            $table->string('composition')->nullable();
            $table->string('issue_name')->nullable();
            $table->string('color')->nullable();

            $table->text('condition_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metadata');
    }
};
