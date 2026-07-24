<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('status')->default('captured')->after('user_id')->index();
            $table->string('review_reason')->nullable()->after('status');
            $table->timestamp('processed_at')->nullable()->after('review_reason');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['status', 'review_reason', 'processed_at']);
        });
    }
};
