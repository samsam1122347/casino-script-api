<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crash_rounds', function (Blueprint $table) {
            $table->decimal('growth_per_second_snapshot', 10, 6)->nullable()->after('max_multiplier_cap_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('crash_rounds', function (Blueprint $table) {
            $table->dropColumn('growth_per_second_snapshot');
        });
    }
};
