<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What the owner paid for an item and the value it is insured for —
// manual entries, never written by the AI.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('insurance_value', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'insurance_value']);
        });
    }
};
