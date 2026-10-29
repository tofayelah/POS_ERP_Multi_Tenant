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
            $table->uuid('id')->primary();
            $table->string('name', 255);
            $table->string('subdomain', 100)->unique();
            $table->string('custom_domain', 255)->nullable()->unique();
            $table->string('subscription_plan', 50)->default('starter');
            $table->string('status', 50)->default('active');
            $table->string('data_residency_region', 50)->default('sgp-1');
            $table->timestampsTz();
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
