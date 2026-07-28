<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Fingerprint of the uploaded PDF so the same batch cannot be uploaded
// twice by accident (reprocessing is done on the existing batch instead).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('label')->index();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropColumn('content_hash');
        });
    }
};
