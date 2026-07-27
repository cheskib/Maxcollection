<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            // Which side of the item the photo shows: front, back, or detail.
            $table->string('role')->nullable()->after('size_bytes');
        });

        Schema::table('batches', function (Blueprint $table) {
            // When a PDF batch finished converting into items.
            $table->timestamp('converted_at')->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('images', fn (Blueprint $table) => $table->dropColumn('role'));
        Schema::table('batches', fn (Blueprint $table) => $table->dropColumn('converted_at'));
    }
};
