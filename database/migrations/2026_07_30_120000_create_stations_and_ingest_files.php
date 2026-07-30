<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Scan stations push files to the server with a per-station token
// (typed by line: cards or comics). Every received file is recorded so
// retries are idempotent and the ingestion pipeline has a work queue.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // cards | comics
            $table->text('token'); // encrypted; needed to re-download config
            $table->string('token_hash', 64)->unique(); // sha256, for lookup
            $table->string('token_last4', 4);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ingest_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained();
            $table->string('folder');
            $table->string('filename');
            $table->string('checksum', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->string('path');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // The same physical file re-sent (agent retry) must be a no-op.
            $table->unique(['station_id', 'checksum']);
            $table->index(['station_id', 'folder']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingest_files');
        Schema::dropIfExists('stations');
    }
};
