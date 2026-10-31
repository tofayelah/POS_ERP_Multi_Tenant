<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('stock_count_sessions', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('warehouse_id');
            $table->string('session_number', 100);
            $table->string('status', 30)->default('in_progress'); // draft, in_progress, completed, reconciled, cancelled
            $table->timestampTz('snapshot_at', 6)->useCurrent();
            $table->timestampTz('completed_at')->nullable();
            $table->uuid('approved_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_stock_count_sessions_company_id');
            $table->unique(['tenant_id', 'company_id', 'session_number'], 'uq_count_session_number');

            $table->foreign(['tenant_id', 'company_id'], 'fk_count_sessions_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_count_sessions_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');
        });

        $this->createTenantTable('stock_count_lines', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('session_id');
            $table->uuid('stock_location_id')->nullable();
            $table->uuid('product_variant_id');
            $table->uuid('last_movement_id')->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->decimal('snapshot_quantity', 18, 4)->default(0.0000);
            $table->decimal('in_flight_movements', 18, 4)->default(0.0000);
            $table->decimal('counted_quantity', 18, 4)->nullable();
            $table->decimal('variance_quantity', 18, 4)->default(0.0000);
            $table->decimal('variance_value', 18, 4)->default(0.0000);
            $table->timestampsTz();

            $table->unique(['tenant_id', 'company_id', 'id'], 'uq_stock_count_lines_company_id');

            $table->foreign(['tenant_id', 'company_id'], 'fk_count_lines_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'session_id'], 'fk_count_lines_session')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('stock_count_sessions')
                ->onDelete('cascade');

            $table->foreign(['tenant_id', 'company_id', 'stock_location_id'], 'fk_count_lines_location')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('stock_locations')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_count_lines_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');
        });

        DB::connection('pgsql_owner')->unprepared('
            ALTER TABLE stock_count_lines
                ADD CONSTRAINT uq_count_lines_composite
                UNIQUE NULLS NOT DISTINCT (tenant_id, session_id, stock_location_id, product_variant_id, batch_number);
        ');
    }

    public function down(): void
    {
        $this->dropTenantTable('stock_count_lines');
        $this->dropTenantTable('stock_count_sessions');
    }
};
