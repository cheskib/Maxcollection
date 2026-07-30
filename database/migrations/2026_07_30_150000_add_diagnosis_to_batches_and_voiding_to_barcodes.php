<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Diagnosis: every flagged batch ends in a mandatory resolution — who,
// when, what (confirmed | rescan | replaced) — and the record is never
// deleted, only marked. Voided barcodes are the sticker-ledger state for
// physically retired labels (a replaced bag's number never returns).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('resolution')->nullable(); // confirmed | rescan | replaced
            $table->string('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('superseded_by_batch_id')->nullable()->constrained('batches')->nullOnDelete();
        });

        Schema::table('barcodes', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropConstrainedForeignId('superseded_by_batch_id');
            $table->dropColumn(['resolution', 'resolution_note', 'resolved_at']);
        });

        Schema::table('barcodes', function (Blueprint $table) {
            $table->dropColumn(['voided_at', 'void_reason']);
        });
    }
};
