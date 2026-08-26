<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('external_round_id', 64);
            $table->string('phase', 32)->default('running');
            $table->decimal('last_multiplier', 12, 4)->default(1);
            $table->unsignedInteger('tick_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_round_id']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('crash_bets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('crash_round_id')->constrained('crash_rounds')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('stake_minor')->default(0);
            $table->decimal('cashout_multiplier', 12, 4)->nullable();
            $table->unsignedBigInteger('payout_minor')->nullable();
            $table->string('status', 24)->default('open');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['crash_round_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crash_bets');
        Schema::dropIfExists('crash_rounds');
    }
};
