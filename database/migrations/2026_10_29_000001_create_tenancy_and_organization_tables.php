<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('subdomain', 64)->unique();
            $table->string('status', 32)->default('active'); // active, suspended, in_arrears
            $table->string('plan', 32)->default('starter');
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('id');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('bin_number', 32)->nullable(); // 13-digit NBR BIN
            $table->string('tin_number', 32)->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->string('country_code', 2)->default('BD');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(7); // July in Bangladesh
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'id'], 'uq_companies_tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('restrict');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('id');
            $table->string('code', 32);
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('district', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'company_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_branches_composite');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->onDelete('restrict');
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('id');
            $table->uuid('branch_id')->nullable();
            $table->string('code', 32);
            $table->string('name');
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'company_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_warehouses_composite');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('tenants');
    }
};
