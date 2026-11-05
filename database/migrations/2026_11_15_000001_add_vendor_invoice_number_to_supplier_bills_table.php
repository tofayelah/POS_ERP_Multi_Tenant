<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Implements duplicate vendor invoice protection on supplier_bills (AUD-W1S1-001).
     */
    public function up(): void
    {
        Schema::connection('pgsql_owner')->table('supplier_bills', function (Blueprint $table) {
            $table->string('vendor_invoice_number', 100)->default('')->after('bill_number');
        });

        // Ensure any existing rows have a non-empty vendor_invoice_number to prevent collision
        DB::connection('pgsql_owner')->statement("
            UPDATE supplier_bills 
            SET vendor_invoice_number = COALESCE(NULLIF(vendor_invoice_number, ''), bill_number)
            WHERE vendor_invoice_number = ''
        ");

        Schema::connection('pgsql_owner')->table('supplier_bills', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'company_id', 'supplier_id', 'vendor_invoice_number'],
                'uq_supplier_bills_vendor_invoice'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql_owner')->table('supplier_bills', function (Blueprint $table) {
            $table->dropUnique('uq_supplier_bills_vendor_invoice');
            $table->dropColumn('vendor_invoice_number');
        });
    }
};
