<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Owner correction: there is no such thing as a "category card" — the
// physical object is a DIVIDER card. Rename the section column and
// convert any labels already registered under the old naming
// (type "category", CAT- prefix) before any were printed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_sections', function (Blueprint $table) {
            $table->renameColumn('category_barcode_id', 'divider_barcode_id');
        });

        DB::table('barcodes')->where('type', 'category')->update(['type' => 'divider']);

        foreach (DB::table('barcodes')->where('code', 'like', 'CAT-%')->get() as $barcode) {
            DB::table('barcodes')->where('id', $barcode->id)
                ->update(['code' => 'DIV-'.substr($barcode->code, 4)]);
        }
    }

    public function down(): void
    {
        Schema::table('storage_sections', function (Blueprint $table) {
            $table->renameColumn('divider_barcode_id', 'category_barcode_id');
        });

        DB::table('barcodes')->where('type', 'divider')->update(['type' => 'category']);

        foreach (DB::table('barcodes')->where('code', 'like', 'DIV-%')->get() as $barcode) {
            DB::table('barcodes')->where('id', $barcode->id)
                ->update(['code' => 'CAT-'.substr($barcode->code, 4)]);
        }
    }
};
