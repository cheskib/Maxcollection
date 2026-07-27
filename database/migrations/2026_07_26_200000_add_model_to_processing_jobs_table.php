<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            // Which OpenAI model examined the item (standard vs premium tier).
            $table->string('model')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('processing_jobs', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
