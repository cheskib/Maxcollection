<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('source'); // bulk | pdf
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::table('items', function (Blueprint $table) {
            // Items captured one at a time (and pre-existing items) have no
            // batch; deleting a batch keeps its items.
            $table->foreignId('batch_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
        });
        Schema::dropIfExists('batches');
    }
};
