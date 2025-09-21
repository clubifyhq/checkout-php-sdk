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
        Schema::create('super_admin_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('super_admin_id')->constrained()->onDelete('cascade');
            $table->string('session_id')->index();
            $table->text('token');
            $table->json('permissions')->nullable();
            $table->string('current_tenant_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->enum('status', ['active', 'expired', 'terminated', 'revoked'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['super_admin_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index('last_activity_at');
            $table->index('current_tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_sessions');
    }
};