<?php

use App\Core\Database\TenantMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->createTenantTable('stock_balances', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('warehouse_id');
            $table->uuid('stock_location_id')->nullable();
            $table->uuid('product_variant_id');
            $table->string('batch_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity_on_hand', 18, 4)->default(0.0000);
            $table->decimal('quantity_reserved', 18, 4)->default(0.0000);
            $table->decimal('weighted_avg_cost', 18, 4)->default(0.0000);
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'company_id'], 'fk_balances_company')
                ->references(['tenant_id', 'id'])
                ->on('companies')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'warehouse_id'], 'fk_balances_warehouse')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('warehouses')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'stock_location_id'], 'fk_balances_location')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('stock_locations')
                ->onDelete('restrict');

            $table->foreign(['tenant_id', 'company_id', 'product_variant_id'], 'fk_balances_variant')
                ->references(['tenant_id', 'company_id', 'id'])
                ->on('product_variants')
                ->onDelete('restrict');
        });

        // Add composite UNIQUE NULLS NOT DISTINCT and ADR-016 trigger
        DB::connection('pgsql_owner')->unprepared("
            ALTER TABLE stock_balances
                ADD CONSTRAINT uq_stock_variant_batch
                UNIQUE NULLS NOT DISTINCT (tenant_id, warehouse_id, stock_location_id, product_variant_id, batch_number);

            CREATE OR REPLACE FUNCTION trg_fn_enforce_stock_balance_policy()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.quantity_on_hand < 0 THEN
                    IF COALESCE(NULLIF(current_setting('app.allow_negative_stock', true), ''), 'false')::boolean IS NOT TRUE THEN
                        RAISE EXCEPTION 'Inventory Balance Violation: Negative stock balance is prohibited for online transactions (Product Variant: %, Warehouse: %).', NEW.product_variant_id, NEW.warehouse_id;
                    END IF;
                END IF;

                IF NEW.quantity_reserved < 0 THEN
                    RAISE EXCEPTION 'Inventory Balance Violation: Reserved quantity cannot be negative.';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_stock_balances_policy ON stock_balances;
            CREATE TRIGGER trg_stock_balances_policy
            BEFORE INSERT OR UPDATE ON stock_balances
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_stock_balance_policy();
        ");
    }

    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared('
            DROP TRIGGER IF EXISTS trg_stock_balances_policy ON stock_balances;
            DROP FUNCTION IF EXISTS trg_fn_enforce_stock_balance_policy();
        ');

        $this->dropTenantTable('stock_balances');
    }
};
