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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Event Classification
            $table->string('audit_id', 64)->unique();
            $table->string('event', 100)->index();
            $table->string('category', 50)->index(); // security, authentication, authorization, data_access, configuration, system
            $table->string('event_type', 100)->index(); // login_attempt, permission_check, data_modification, etc.
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->enum('sensitivity', ['public', 'internal', 'confidential', 'restricted'])->default('internal');

            // User Context
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('super_admin_id')->nullable()->index();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('impersonated_user_id', 64)->nullable();

            // Request Context
            $table->ipAddress('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->string('endpoint', 255)->nullable()->index();
            $table->string('method', 10)->nullable();
            $table->json('headers')->nullable();
            $table->text('request_id')->nullable();

            // Event Data
            $table->json('event_data')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();

            // Security & Integrity
            $table->string('integrity_hash', 64)->nullable();
            $table->json('threat_indicators')->nullable();
            $table->boolean('anomaly_detected')->default(false)->index();

            // Result & Status
            $table->boolean('success')->default(true)->index();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->string('result_status', 50)->nullable();

            // Compliance & Retention
            $table->enum('compliance_category', ['gdpr', 'sox', 'hipaa', 'pci', 'general'])->default('general');
            $table->timestamp('retention_until')->nullable();
            $table->boolean('archived')->default(false)->index();

            // Performance
            $table->integer('duration_ms')->nullable();
            $table->integer('memory_usage_kb')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes for performance
            $table->index(['category', 'created_at']);
            $table->index(['ip_address', 'category', 'created_at']);
            $table->index(['user_id', 'category', 'created_at']);
            $table->index(['super_admin_id', 'category', 'created_at']);
            $table->index(['severity', 'created_at']);
            $table->index(['anomaly_detected', 'created_at']);
            $table->index(['success', 'created_at']);
            $table->index(['compliance_category', 'retention_until']);

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('super_admin_id')->references('id')->on('super_admins')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};