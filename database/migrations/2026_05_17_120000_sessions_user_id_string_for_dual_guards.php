<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions are shared across guards (`web` = User UUID id, Filament `admin` = bigint id).
 * A UUID-typed FK to `users.id` rejects admin id `1` on PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection($this->connection);
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('sessions')) {
            return;
        }

        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $connection->statement('ALTER TABLE sessions DROP CONSTRAINT IF EXISTS sessions_user_id_foreign');
            $connection->statement(
                'ALTER TABLE sessions ALTER COLUMN user_id TYPE varchar(255) USING CASE WHEN user_id IS NULL THEN NULL ELSE user_id::text END',
            );

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                $schema->table('sessions', static function (Blueprint $table): void {
                    $table->dropForeign(['user_id']);
                });
            } catch (Throwable) {
                // FK name may vary
            }
            $connection->statement('ALTER TABLE sessions MODIFY user_id VARCHAR(255) NULL');

            return;
        }

        // SQLite etc.: drop FK so admin integer ids don’t violate `users.uuid` FK.
        try {
            $schema->table('sessions', static function (Blueprint $table): void {
                $table->dropForeign(['user_id']);
            });
        } catch (Throwable) {
            //
        }
    }

    /**
     * Re-adding FK is unsafe once admin sessions mixed string ids into column.
     */
    public function down(): void {}
};
