<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Owner correction: the fi-8170 was never ordered. It becomes a Need
// with real prices and links (≈$999–1,070 new at the time of writing).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('equipment_items')
            ->where('name', 'like', 'Fujitsu fi-8170%')
            ->update([
                'status' => 'need',
                'price' => '$999–1,070',
                'links' => json_encode([
                    ['label' => 'Adorama', 'url' => 'https://www.adorama.com/fudupa381b55.html'],
                    ['label' => 'B&H', 'url' => 'https://www.bhphotovideo.com/c/product/1697370-REG/fujitsu_pa03810_b055_fi_8170_color_duplex_document.html'],
                ]),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data correction; nothing sensible to restore.
    }
};
