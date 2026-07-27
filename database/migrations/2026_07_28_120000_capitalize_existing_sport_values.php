<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// One-time cleanup: earlier AI runs saved sports in lowercase ("football");
// display and Browse grouping expect "Football".
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('metadata')->whereNotNull('sport')->get(['id', 'sport']) as $row) {
            $title = Str::title($row->sport);

            if ($title !== $row->sport) {
                DB::table('metadata')->where('id', $row->id)->update(['sport' => $title]);
            }
        }
    }

    public function down(): void
    {
        // Capitalization cleanup is not reversible.
    }
};
