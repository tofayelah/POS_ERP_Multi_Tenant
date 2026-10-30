<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared("
            CREATE OR REPLACE FUNCTION trg_check_price_list_item_mrp()
            RETURNS TRIGGER AS $$
            DECLARE
                v_mrp NUMERIC;
            BEGIN
                SELECT mrp INTO v_mrp FROM product_variants WHERE id = NEW.variant_id;
                IF v_mrp IS NOT NULL AND NEW.unit_price > v_mrp THEN
                    RAISE EXCEPTION 'Unit price % exceeds variant MRP % for variant %', NEW.unit_price, v_mrp, NEW.variant_id
                        USING ERRCODE = '23514'; -- check_violation
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_price_list_item_mrp ON price_list_items;
            CREATE TRIGGER trg_price_list_item_mrp
                BEFORE INSERT OR UPDATE OF unit_price, variant_id ON price_list_items
                FOR EACH ROW
                EXECUTE FUNCTION trg_check_price_list_item_mrp();

            CREATE OR REPLACE FUNCTION trg_check_product_variant_mrp_reduction()
            RETURNS TRIGGER AS $$
            DECLARE
                v_max_price NUMERIC;
            BEGIN
                IF NEW.mrp < OLD.mrp THEN
                    SELECT MAX(unit_price) INTO v_max_price FROM price_list_items WHERE variant_id = NEW.id;
                    IF v_max_price IS NOT NULL AND v_max_price > NEW.mrp THEN
                        RAISE EXCEPTION 'Cannot reduce variant MRP to % below existing price list unit price %', NEW.mrp, v_max_price
                            USING ERRCODE = '23514'; -- check_violation
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_product_variant_mrp_reduction ON product_variants;
            CREATE TRIGGER trg_product_variant_mrp_reduction
                BEFORE UPDATE OF mrp ON product_variants
                FOR EACH ROW
                EXECUTE FUNCTION trg_check_product_variant_mrp_reduction();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_price_list_item_mrp ON price_list_items;
            DROP FUNCTION IF EXISTS trg_check_price_list_item_mrp();
            DROP TRIGGER IF EXISTS trg_product_variant_mrp_reduction ON product_variants;
            DROP FUNCTION IF EXISTS trg_check_product_variant_mrp_reduction();
        ");
    }
};
