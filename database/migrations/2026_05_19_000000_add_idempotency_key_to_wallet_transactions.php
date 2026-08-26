<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 191)->nullable()->after('type');
            // Atomic guard against double-spend when the same Idempotency-Key races itself.
            $table->unique(['wallet_id', 'idempotency_key']);
            $table->index(['wallet_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique(['wallet_id', 'idempotency_key']);
            $table->dropIndex(['wallet_id', 'type']);
            $table->dropColumn('idempotency_key');
        });
    }
};
