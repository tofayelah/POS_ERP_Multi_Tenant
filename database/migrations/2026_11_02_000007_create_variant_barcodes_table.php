<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('variant_barcodes', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('variant_id');
            $table->string('barcode', 100);
            $table->string('uom', 50);
            $table->decimal('multiplier', 18, 4)->default(1.0000);
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'barcode'], 'uq_variant_barcode_per_company');

            $table->foreign(['tenant_id', 'company_id', 'variant_id'], 'fk_variant_barcodes_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('variant_barcodes');
    }
};
