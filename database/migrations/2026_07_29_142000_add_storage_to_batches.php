<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A batch becomes a physical bag when its bag barcode is scanned at
// finalize. storage_section_id is the ONLY link between the digital and
// physical hierarchies. finalized_at = cards are in the identified bag;
// archived_at = originals safely copied to Dropbox (a later milestone) —
// a batch can be finalized but not yet archived.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->foreignId('barcode_id')->nullable()->unique()->after('label')->constrained('barcodes');
            $table->string('status', 16)->default('open')->after('barcode_id'); // open | closed
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('storage_section_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_section_id');
            $table->dropConstrainedForeignId('barcode_id');
            $table->dropColumn(['status', 'finalized_at', 'archived_at']);
        });
    }
};
