<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->after('name');
            $table->string('recovery_question_1', 64)->nullable()->after('password');
            $table->string('recovery_question_2', 64)->nullable();
            $table->string('recovery_question_3', 64)->nullable();
            $table->string('recovery_answer_1')->nullable();
            $table->string('recovery_answer_2')->nullable();
            $table->string('recovery_answer_3')->nullable();
        });

        DB::table('users')->whereNull('username')->orderBy('id')->chunk(50, function ($rows): void {
            foreach ($rows as $row) {
                $emailLocal = $row->email ? strstr((string) $row->email, '@', true) : false;
                $baseRaw = $emailLocal !== false ? (string) $emailLocal : 'player';
                $base = strtolower(Str::slug($baseRaw, '_')) ?: 'player';
                $base = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'player';
                $base = substr($base, 0, 20);

                $candidate = $base;
                $n = 0;
                while ($this->usernameTakenForTenant((string) $row->id, $row->tenant_id, $candidate)) {
                    $suffix = '_'.(++$n);
                    $candidate = substr($base, 0, max(1, 24 - strlen($suffix))).$suffix;
                }

                DB::table('users')->where('id', $row->id)->update(['username' => $candidate]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'username']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * @param  non-empty-string  $excludeUserId
     */
    private function usernameTakenForTenant(string $excludeUserId, mixed $tenantId, string $username): bool
    {
        return DB::table('users')
            ->where('username', $username)
            ->where('id', '!=', $excludeUserId)
            ->when(
                $tenantId !== null,
                fn ($q) => $q->where('tenant_id', $tenantId),
                fn ($q) => $q->whereNull('tenant_id'),
            )
            ->exists();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_tenant_id_username_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'recovery_question_1',
                'recovery_question_2',
                'recovery_question_3',
                'recovery_answer_1',
                'recovery_answer_2',
                'recovery_answer_3',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
