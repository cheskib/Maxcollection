<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Sets are now defined by sport + manufacturer + year only (owner
// decision): merge profiles that differed only by set_name, keeping one
// per group — preferably one that already has a written description.
return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('card_sets')
            ->get()
            ->groupBy(fn ($set) => implode('|', [$set->sport, $set->manufacturer, $set->year]));

        foreach ($groups as $sets) {
            $keeper = $sets->sortBy([
                fn ($a, $b) => ($b->description !== null) <=> ($a->description !== null),
                fn ($a, $b) => $a->id <=> $b->id,
            ])->first();

            DB::table('card_sets')->where('id', $keeper->id)->update(['set_name' => '']);

            $others = $sets->pluck('id')->reject(fn ($id) => $id === $keeper->id);

            if ($others->isNotEmpty()) {
                DB::table('card_sets')->whereIn('id', $others->all())->delete();
            }
        }
    }

    public function down(): void
    {
        // The merge is not reversible.
    }
};
