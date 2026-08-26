<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crash_tenant_settings', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('house_edge_bp')->default(400);
            $table->unsignedBigInteger('min_bet_minor')->default(100);
            $table->unsignedBigInteger('max_bet_minor')->default(1_000_000);
            $table->unsignedBigInteger('max_win_minor_per_round')->default(500_000_00);
            $table->decimal('max_multiplier_cap', 14, 4)->default(10000);
            $table->unsignedSmallInteger('betting_duration_seconds')->default(12);
            $table->decimal('growth_per_second', 10, 6)->default(0.055);
            $table->unsignedTinyInteger('tick_hz')->default(6);
            $table->boolean('provably_fair_enabled')->default(true);
            $table->boolean('game_enabled')->default(true);
            $table->boolean('game_paused')->default(false);
            $table->boolean('engine_enabled')->default(true);
            $table->decimal('pending_operator_crash_multiplier', 14, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('crash_operator_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('action', 64);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::table('crash_rounds', function (Blueprint $table) {
            $table->decimal('crash_point_multiplier', 14, 4)->nullable()->after('phase');
            $table->string('hash_commitment', 128)->nullable()->after('crash_point_multiplier');
            $table->text('revealed_server_seed')->nullable()->after('hash_commitment');
            $table->string('pf_nonce', 64)->nullable()->after('revealed_server_seed');
            $table->string('generation_source', 24)->default('algo')->after('pf_nonce');
            $table->timestamp('betting_opens_at')->nullable()->after('generation_source');
            $table->timestamp('betting_closes_at')->nullable()->after('betting_opens_at');
            $table->timestamp('started_running_at')->nullable()->after('betting_closes_at');
            $table->decimal('max_multiplier_cap_snapshot', 14, 4)->nullable()->after('started_running_at');
        });

        Schema::table('crash_bets', function (Blueprint $table) {
            $table->decimal('auto_cashout_multiplier', 14, 4)->nullable()->after('stake_minor');
            $table->string('place_idempotency_key', 128)->nullable()->after('meta');

            $table->unique(['user_id', 'crash_round_id']);
            $table->index(['user_id', 'place_idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('crash_bets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'crash_round_id']);
            $table->dropIndex(['user_id', 'place_idempotency_key']);
            $table->dropColumn(['auto_cashout_multiplier', 'place_idempotency_key']);
        });

        Schema::table('crash_rounds', function (Blueprint $table) {
            $table->dropColumn([
                'crash_point_multiplier',
                'hash_commitment',
                'revealed_server_seed',
                'pf_nonce',
                'generation_source',
                'betting_opens_at',
                'betting_closes_at',
                'started_running_at',
                'max_multiplier_cap_snapshot',
            ]);
        });

        Schema::dropIfExists('crash_operator_actions');
        Schema::dropIfExists('crash_tenant_settings');
    }
};
