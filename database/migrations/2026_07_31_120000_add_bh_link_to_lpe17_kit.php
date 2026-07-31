<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Owner asked for the LP-E17 power kit on B&H: the Bescor LPE17AC kit
// is the direct fit; keep the Amazon search as the alternative.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('equipment_items')
            ->where('name', 'like', 'LP-E17 dummy-battery%')
            ->update([
                'note' => 'Bescor LPE17AC kit — dummy battery + wall adapter; USB-C variant also exists',
                'links' => json_encode([
                    ['label' => 'B&H (Bescor kit)', 'url' => 'https://www.bhphotovideo.com/c/product/1566802-REG/bescor_lpe17ac_lpe17_style_coupler_dummy_and.html'],
                    ['label' => 'B&H (USB-C variant)', 'url' => 'https://www.bhphotovideo.com/c/product/1717629-REG/bescor_lpe17usbcd_lpe17usbc_lpe17_dummy_with.html'],
                    ['label' => 'Amazon search', 'url' => 'https://www.amazon.com/s?k=LP-E17+dummy+battery+AC+adapter+EOS+R50'],
                ]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data enrichment; nothing sensible to restore.
    }
};
