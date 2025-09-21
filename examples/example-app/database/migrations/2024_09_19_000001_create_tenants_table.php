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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->unique()->index();
            $table->string('name');
            $table->string('email')->unique();
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])->default('active');
            $table->enum('plan', ['basic', 'premium', 'enterprise'])->default('basic');
            $table->json('features')->nullable();
            $table->json('configuration')->nullable();
            $table->json('limits')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['plan', 'status']);
            $table->index('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};