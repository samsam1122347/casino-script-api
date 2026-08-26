<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crash_rounds', function (Blueprint $table) {
            $table->timestamp('last_tick_broadcast_at')->nullable()->after('max_multiplier_cap_snapshot');
        });

        Schema::table('crash_bets', function (Blueprint $table) {
            $table->string('cashout_idempotency_key', 128)->nullable()->after('place_idempotency_key');
            $table->index(['user_id', 'cashout_idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('crash_rounds', function (Blueprint $table) {
            $table->dropColumn('last_tick_broadcast_at');
        });

        Schema::table('crash_bets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'cashout_idempotency_key']);
            $table->dropColumn('cashout_idempotency_key');
        });
    }
};
