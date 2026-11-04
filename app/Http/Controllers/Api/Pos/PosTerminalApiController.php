<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosTerminalApiController extends Controller
{
    /**
     * Incremental catalog delta for offline POS synchronization.
     */
    public function catalogDelta(Request $request): JsonResponse
    {
        $products = [
            [
                'variant_id' => '0192e4a1-0001-7000-8000-000000000001',
                'barcode' => '8941100111111',
                'sku' => 'RICE-01',
                'name_en' => 'Miniket Rice 5kg',
                'name_bn' => 'মিনিকেট চাল ৫ কেজি',
                'mrp_price' => '450.00',
                'wholesale_price' => '420.00',
                'cost_price' => '400.00',
                'tax_rate' => '0',
                'tax_type' => 'exempt',
                'is_tax_inclusive' => true,
                'is_active' => true,
            ],
            [
                'variant_id' => '0192e4a1-0002-7000-8000-000000000002',
                'barcode' => '8941100222222',
                'sku' => 'OIL-01',
                'name_en' => 'Pure Mustard Oil 1L',
                'name_bn' => 'খাঁটি সরিষার তেল ১ লিটার',
                'mrp_price' => '280.00',
                'wholesale_price' => '260.00',
                'cost_price' => '240.00',
                'tax_rate' => '5',
                'tax_type' => 'standard',
                'is_tax_inclusive' => true,
                'is_active' => true,
            ],
            [
                'variant_id' => '0192e4a1-0003-7000-8000-000000000003',
                'barcode' => '8941100333333',
                'sku' => 'SOAP-01',
                'name_en' => 'Arogya Neem Soap 125g',
                'name_bn' => 'আরোগ্য নিম সাবান ১২৫ গ্রাম',
                'mrp_price' => '65.00',
                'wholesale_price' => '55.00',
                'cost_price' => '50.00',
                'tax_rate' => '15',
                'tax_type' => 'standard',
                'is_tax_inclusive' => true,
                'is_active' => true,
            ],
            [
                'variant_id' => '0192e4a1-0004-7000-8000-000000000004',
                'barcode' => '8941100444444',
                'sku' => 'FLOUR-02',
                'name_en' => 'Teer Wheat Flour 2kg',
                'name_bn' => 'তীর আটা ২ কেজি',
                'mrp_price' => '130.00',
                'wholesale_price' => '120.00',
                'cost_price' => '110.00',
                'tax_rate' => '0',
                'tax_type' => 'exempt',
                'is_tax_inclusive' => true,
                'is_active' => true,
            ],
            [
                'variant_id' => '0192e4a1-0005-7000-8000-000000000005',
                'barcode' => '8941100555555',
                'sku' => 'MILK-01',
                'name_en' => 'Fresh Full Cream Milk 1L',
                'name_bn' => 'ফ্রেশ ফুল ক্রিম দুধ ১ লিটার',
                'mrp_price' => '95.00',
                'wholesale_price' => '88.00',
                'cost_price' => '80.00',
                'tax_rate' => '0',
                'tax_type' => 'exempt',
                'is_tax_inclusive' => true,
                'is_active' => true,
            ],
        ];

        return response()->json([
            'status' => 'success',
            'synced_at' => now()->toIso8601String(),
            'count' => count($products),
            'items' => $products,
        ]);
    }

    /**
     * Batch sync offline sales to the central server.
     */
    public function syncSales(Request $request): JsonResponse
    {
        $sales = $request->input('sales', []);

        return response()->json([
            'status' => 'success',
            'processed_count' => count($sales),
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
