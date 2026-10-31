<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Implements Section 9.2 Trigger 6: Stock Movements Append-Only Immutability Guard (Resolving INV-01, NEW-23).
     */
    public function up(): void
    {
        DB::connection('pgsql_owner')->unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_enforce_stock_movements_immutability()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Inventory Audit Violation: Stock movements are an append-only ledger. Edits and deletions are prohibited (Movement ID: %).', OLD.id;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_stock_movements_immutability ON stock_movements;
            CREATE TRIGGER trg_stock_movements_immutability
            BEFORE UPDATE OR DELETE ON stock_movements
            FOR EACH ROW
            EXECUTE FUNCTION trg_fn_enforce_stock_movements_immutability();

            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'app_rw') THEN
                    REVOKE TRUNCATE ON stock_movements FROM app_rw;
                END IF;
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'reporting_ro') THEN
                    REVOKE TRUNCATE ON stock_movements FROM reporting_ro;
                END IF;
            END
            $$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection('pgsql_owner')->unprepared('
            DROP TRIGGER IF EXISTS trg_stock_movements_immutability ON stock_movements;
            DROP FUNCTION IF EXISTS trg_fn_enforce_stock_movements_immutability();
        ');
    }
};
