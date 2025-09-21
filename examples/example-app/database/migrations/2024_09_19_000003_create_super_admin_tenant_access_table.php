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
        Schema::create('super_admin_tenant_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('super_admin_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->json('permissions')->nullable();
            $table->timestamp('granted_at');
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate access records
            $table->unique(['super_admin_id', 'tenant_id']);

            // Foreign key for granted_by
            $table->foreign('granted_by')->references('id')->on('super_admins')->onDelete('set null');

            // Indexes for performance
            $table->index('granted_at');
            $table->index('granted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_tenant_access');
    }
};