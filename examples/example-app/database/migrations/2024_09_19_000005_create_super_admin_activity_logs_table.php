<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('super_admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('super_admin_id')->constrained()->onDelete('cascade');
            $table->string('action');
            $table->text('description');
            $table->json('context')->nullable();
            $table->json('metadata')->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->timestamp('created_at');

            // Indexes for performance
            $table->index(['super_admin_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_activity_logs');
    }
};