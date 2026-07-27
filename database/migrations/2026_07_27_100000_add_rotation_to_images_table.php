<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Display rotation in clockwise degrees (0/90/180/270). The
            // original file is never modified (PROJECT.md rule 6).
            $table->unsignedSmallInteger('rotation')->default(0)->after('size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('rotation');
        });
    }
};
