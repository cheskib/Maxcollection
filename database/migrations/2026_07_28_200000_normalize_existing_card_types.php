<?php

use App\Models\Metadata;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// One-time cleanup: collapse card-type variants already in the database
// ("Phillies Leaders" -> "Team Leaders", "NL Leaders" -> "League
// Leaders", "All Star" -> "All-Star") using the same normalizer new AI
// results go through.
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('metadata')->whereNotNull('card_type')->get(['id', 'card_type']) as $row) {
            $normalized = Metadata::normalizeCardType($row->card_type);

            if ($normalized !== $row->card_type) {
                DB::table('metadata')->where('id', $row->id)->update(['card_type' => $normalized]);
            }
        }
    }

    public function down(): void
    {
        // Normalization is not reversible.
    }
};
