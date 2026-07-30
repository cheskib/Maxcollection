<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The bagging station's enforced scan-in / scan-out flow: every ticket
// scan, completion, set-aside, and alarm is recorded with timing — the
// per-bagger KPI source.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bagging_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // ticket_scanned | bag_done | set_aside | alarm
            $table->string('verdict')->nullable(); // good | flagged
            $table->unsignedInteger('seconds')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bagging_events');
    }
};
