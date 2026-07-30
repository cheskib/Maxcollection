<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The ingestion pipeline: batches remember which station captured them
// (the per-station KPI leg) and carry a capture-health flag — silent by
// design, surfaced to admins, physically caught at bagging later.
// Stations remember the admin who registered them so scan-line batches
// have an owning account.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('capture_flag')->nullable();
        });

        Schema::table('stations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
            $table->dropColumn('capture_flag');
        });

        Schema::table('stations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
