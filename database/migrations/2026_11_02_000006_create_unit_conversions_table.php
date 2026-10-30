<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('unit_conversions', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->string('from_uom', 50);
            $table->string('to_uom', 50);
            $table->decimal('multiplier', 18, 4);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'from_uom', 'to_uom'], 'uq_unit_conversion_pair');

            $table->foreign(['tenant_id', 'company_id'], 'fk_unit_conversions_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        $this->dropTenantTable('unit_conversions');
    }
};
