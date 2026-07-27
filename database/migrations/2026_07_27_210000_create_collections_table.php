<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('items', function (Blueprint $table) {
            // Unassigned items simply have no collection; deleting a
            // collection keeps its items.
            $table->foreignId('collection_id')->nullable()->after('batch_id')
                ->constrained()->nullOnDelete();
        });

        // One-time setup approved by the owner: create the two starting
        // collections and move every pre-existing item into Cheski's.
        $userId = DB::table('users')->where('email', 'cheskib@gmail.com')->value('id');

        if ($userId !== null) {
            $now = now();
            DB::table('collections')->insert([
                ['user_id' => $userId, 'name' => "Cheski's", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => $userId, 'name' => "Sruli's", 'created_at' => $now, 'updated_at' => $now],
            ]);

            $cheskis = DB::table('collections')->where('name', "Cheski's")->value('id');
            DB::table('items')->whereNull('collection_id')->update(['collection_id' => $cheskis]);
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collection_id');
        });
        Schema::dropIfExists('collections');
    }
};
