<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Live market pricing (SportsCardsPro/PriceCharting): the matched
// product, its ungraded price, and when it was last checked.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->decimal('market_value', 10, 2)->nullable();
            $table->string('market_match')->nullable();
            $table->timestamp('market_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->dropColumn(['market_value', 'market_match', 'market_checked_at']);
        });
    }
};
