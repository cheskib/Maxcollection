<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Edge trims as percentages of the displayed (rotated) image.
            // The original file is never modified (PROJECT.md rule 6).
            $table->unsignedTinyInteger('crop_top')->default(0)->after('rotation');
            $table->unsignedTinyInteger('crop_right')->default(0)->after('crop_top');
            $table->unsignedTinyInteger('crop_bottom')->default(0)->after('crop_right');
            $table->unsignedTinyInteger('crop_left')->default(0)->after('crop_bottom');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn(['crop_top', 'crop_right', 'crop_bottom', 'crop_left']);
        });
    }
};
