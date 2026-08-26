<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_inquiry_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('support_inquiry_id')->constrained('support_inquiries')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_from_admin')->default(false);
            $table->timestamps();

            $table->index(['support_inquiry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_inquiry_messages');
    }
};
