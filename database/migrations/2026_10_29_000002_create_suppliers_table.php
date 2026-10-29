<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->uuid('company_id');
            $table->uuid('id');
            
            // Basic & Contact Details
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('alternate_phone', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('country_code', 2)->default('BD');

            // Tax & Compliance (Requested in Suppliers & Vendors.txt)
            $table->string('tax_number', 64)->nullable(); // National Tax / TIN
            $table->string('bin_number', 32)->nullable();  // 13-digit NBR VAT Registration / BIN
            $table->string('vat_reg_number', 64)->nullable(); // VAT Registration Number

            // Banking & Financial Info (Requested in Suppliers & Vendors.txt)
            $table->string('bank_name', 128)->nullable();
            $table->string('bank_account_name', 128)->nullable();
            $table->string('bank_account_number', 64)->nullable();
            $table->string('bank_branch_name', 128)->nullable();
            $table->string('bank_routing_number', 32)->nullable();
            $table->string('mfs_provider', 32)->nullable(); // bKash / Nagad / Rocket Merchant
            $table->string('mfs_account_number', 32)->nullable();

            // Notes & Status (Requested in Suppliers & Vendors.txt)
            $table->text('internal_notes')->nullable();
            $table->text('additional_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_suppliers_composite');
            $table->foreign(['tenant_id', 'company_id'])->references(['tenant_id', 'id'])->on('companies')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
