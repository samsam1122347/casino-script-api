<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_inquiries', function (Blueprint $table) {
            $table->string('subscribe_token', 128)->nullable()->unique()->after('request_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_inquiries', function (Blueprint $table) {
            $table->dropUnique(['subscribe_token']);
            $table->dropColumn('subscribe_token');
        });
    }
};
