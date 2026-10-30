<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('trial_ends_at')->nullable();
            $table->timestampTz('grace_period_ends_at')->nullable();
            $table->timestampTz('current_period_starts_at');
            $table->timestampTz('current_period_ends_at');
            $table->jsonb('extra_modules')->default('[]');
            $table->timestampsTz();

            $table->index(['tenant_id', 'status'], 'idx_subscriptions_tenant_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
    }
};
