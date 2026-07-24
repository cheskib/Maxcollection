<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            // The OpenAI response stored exactly as returned, before any
            // parsing, for debugging and prompt improvement (DECISIONS.md).
            $table->longText('raw_response')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->dropColumn('raw_response');
        });
    }
};
