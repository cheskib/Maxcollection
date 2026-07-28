<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Physical storage: boxes hold sections (one per category divider), and
// finalized batches (bags) sit inside sections. Completely independent of
// metadata — a card's data never changes because its location changes.
// storage_events is the audit trail for finding physical mistakes later.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_boxes', function (Blueprint $table) {
            $table->id();
            // The user packing the box; "one open box" is per user, not global.
            $table->foreignId('user_id')->constrained();
            $table->foreignId('barcode_id')->unique()->constrained('barcodes');
            $table->string('status', 16)->default('open'); // open | closed
            $table->timestamp('closed_at')->nullable();
            // Snapshots recorded at close; always recalculable from relations.
            $table->unsignedInteger('bag_count')->nullable();
            $table->unsignedInteger('section_count')->nullable();
            $table->unsignedInteger('card_count')->nullable();
            $table->timestamps();
        });

        Schema::create('storage_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_box_id')->constrained()->cascadeOnDelete();
            // Null while the section is pending (divider not yet scanned) —
            // and permanently null for a "No Divider Assigned" section kept
            // at box completion. A physical divider exists in exactly one
            // place, so the reference is unique.
            $table->foreignId('category_barcode_id')->nullable()->unique()->constrained('barcodes');
            $table->unsignedInteger('position');
            $table->timestamps();
        });

        Schema::create('storage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('action', 32); // bag_assigned, box_opened, bag_added, divider_scanned, scan_undone, box_completed
            $table->foreignId('barcode_id')->nullable()->constrained('barcodes');
            $table->foreignId('storage_box_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('storage_section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_events');
        Schema::dropIfExists('storage_sections');
        Schema::dropIfExists('storage_boxes');
    }
};
