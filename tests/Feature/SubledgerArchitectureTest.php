<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubledgerArchitectureTest extends TestCase
{
    /**
     * Asserts that no business entity outside the Subledger module stores a customer or supplier balance (ACC-06, NEW-16).
     * Balances are dynamically projected from subledger_open_items and never cached in entity columns.
     */
    public function test_no_module_or_entity_outside_subledger_stores_a_party_balance(): void
    {
        $restrictedTables = [
            'customers',
            'suppliers',
            'sales_orders',
            'sales_order_lines',
            'quotations',
            'quotation_lines',
            'delivery_challans',
            'delivery_challan_lines',
            'purchase_orders',
            'purchase_order_lines',
            'goods_receipts',
            'goods_receipt_lines',
            'sales_invoices',
            'supplier_bills',
        ];

        // 1. Database Schema Check: No balance columns on operational or master tables
        $balanceColumns = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', $restrictedTables)
            ->where('column_name', 'like', '%balance%')
            ->select(['table_name', 'column_name'])
            ->get();

        $this->assertEmpty(
            $balanceColumns->toArray(),
            'Forbidden balance column detected in operational tables: '.json_encode($balanceColumns->toArray()).
            '. Customer and supplier balances must strictly be projected from subledger_open_items (ACC-06, NEW-16).'
        );

        // 2. Eloquent Model Definition Check: None of the operational models declare a balance attribute
        $models = [
            Customer::class,
            Supplier::class,
            Quotation::class,
            SalesOrder::class,
            DeliveryChallan::class,
            PurchaseOrder::class,
            GoodsReceipt::class,
        ];

        foreach ($models as $modelClass) {
            $instance = new $modelClass;
            $fillable = $instance->getFillable();

            foreach ($fillable as $column) {
                $this->assertStringNotContainsString(
                    'balance',
                    strtolower($column),
                    "Model [{$modelClass}] has forbidden fillable balance attribute [{$column}]."
                );
            }
        }
    }

    /**
     * Architectural test: Sales and Procurement modules must not have direct database migrations creating balance tables (ACC-06).
     */
    public function test_subledger_is_sole_owner_of_receivables_and_payables_open_items(): void
    {
        $subledgerTables = DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_name', 'like', 'subledger_%')
            ->pluck('table_name')
            ->toArray();

        $this->assertContains('subledger_open_items', $subledgerTables);
    }
}
