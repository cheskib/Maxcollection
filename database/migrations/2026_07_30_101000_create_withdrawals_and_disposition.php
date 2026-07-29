<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cards leave bags for documented reasons only. The digital record is
// never deleted: a withdrawal row records who/when/why (with sale or
// destination details), and items.disposition reflects what the card
// IS now — null (in its bag), 'relocated' (owned, elsewhere), or
// 'gone' (sold/gifted/lost). History lives in the withdrawal trail.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('reason', 32); // sold, moved, grading, damaged, lost, gift, other
            $table->text('notes')->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->date('sale_date')->nullable();
            $table->string('buyer', 128)->nullable();
            $table->string('platform', 64)->nullable();
            $table->string('destination', 128)->nullable();
            $table->timestamp('reinstated_at')->nullable();
            $table->foreignId('reinstated_by')->nullable()->constrained('users');
            $table->text('reinstate_notes')->nullable();
            $table->timestamps();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('disposition', 16)->nullable()->index();
            $table->timestamp('withdrawn_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['disposition', 'withdrawn_at']);
        });
        Schema::dropIfExists('withdrawals');
    }
};
