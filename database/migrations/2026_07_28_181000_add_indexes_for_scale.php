<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The collection is expected to grow past 300,000 cards. Every column the
// summaries group by and the list filters on gets an index so those
// queries stay fast at that size.
return new class extends Migration
{
    public function up(): void
    {
        // items.status is already indexed by the original schema.
        Schema::table('metadata', function (Blueprint $table) {
            $table->index('category');
            $table->index('sport');
            $table->index('year');
            $table->index('team');
            $table->index('manufacturer');
            $table->index('card_type');
            $table->index('publisher');
            $table->index('country');
            $table->index('player_name');
        });
    }

    public function down(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            foreach (['category', 'sport', 'year', 'team', 'manufacturer', 'card_type', 'publisher', 'country', 'player_name'] as $column) {
                $table->dropIndex([$column]);
            }
        });
    }
};
