<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crash_rounds', function (Blueprint $table) {
            $table->text('pf_server_seed')->nullable()->after('revealed_server_seed');
        });
    }

    public function down(): void
    {
        Schema::table('crash_rounds', function (Blueprint $table) {
            $table->dropColumn('pf_server_seed');
        });
    }
};
