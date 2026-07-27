<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Set when the user rotates a photo by hand; the AI may never change a
// locked photo's orientation again (user corrections always override AI).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->boolean('rotation_locked')->default(false)->after('rotation');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('rotation_locked');
        });
    }
};
