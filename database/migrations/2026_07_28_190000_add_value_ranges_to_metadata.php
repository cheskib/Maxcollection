<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Card value as two ranges: the owner's manual entry (authoritative,
// never touched by the AI) and the AI's ballpark (refreshed on each
// processing run).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->decimal('value_from', 10, 2)->nullable();
            $table->decimal('value_to', 10, 2)->nullable();
            $table->decimal('ai_value_from', 10, 2)->nullable();
            $table->decimal('ai_value_to', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('metadata', function (Blueprint $table) {
            $table->dropColumn(['value_from', 'value_to', 'ai_value_from', 'ai_value_to']);
        });
    }
};
