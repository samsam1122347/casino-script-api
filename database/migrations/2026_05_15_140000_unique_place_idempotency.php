<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crash_bets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'place_idempotency_key']);
            $table->unique(['user_id', 'place_idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('crash_bets', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'place_idempotency_key']);
            $table->index(['user_id', 'place_idempotency_key']);
        });
    }
};
