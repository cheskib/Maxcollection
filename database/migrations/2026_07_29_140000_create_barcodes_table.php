<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The barcode registry: every printable label (bag, box, category divider)
// is registered here at print time, so every code is known to the system
// before it is ever scanned. The registry is the source of truth — objects
// reference these rows by id, never by copied code text.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barcodes', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16); // bag | box | category
            $table->string('code', 16)->unique(); // BAG-000123
            $table->string('label', 64)->nullable(); // divider display name
            $table->uuid('print_run')->nullable()->index();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barcodes');
    }
};
