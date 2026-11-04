<?php

namespace App\Filament\Resources\SupplierBills\Pages;

use App\Core\Tenancy\TenantContext;
use App\Filament\Resources\SupplierBills\SupplierBillResource;
use App\Models\SupplierBill;
use App\Models\SupplierBillLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateSupplierBill extends CreateRecord
{
    protected static string $resource = SupplierBillResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $tenantId = TenantContext::id();

            $fiscalYear = (string) Carbon::parse($data['bill_date'])->year;
            $billNumber = 'BILL-'.$fiscalYear.'-'.strtoupper(Str::random(6));

            $subtotalSum = BigDecimal::zero();
            $taxSum = BigDecimal::zero();
            $lineData = [];

            foreach ($data['lines'] ?? [] as $line) {
                $qty = BigDecimal::of($line['quantity'] ?? '1.0000');
                $price = BigDecimal::of($line['unit_price'] ?? '0.0000');
                $lineSub = $qty->multipliedBy($price);
                $lineTax = BigDecimal::of($line['tax_amount'] ?? '0.0000');
                $lineTot = $lineSub->plus($lineTax);

                $subtotalSum = $subtotalSum->plus($lineSub);
                $taxSum = $taxSum->plus($lineTax);

                $lineData[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'company_id' => $data['company_id'],
                    'goods_receipt_line_id' => $line['goods_receipt_line_id'] ?? null,
                    'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                    'product_id' => $line['product_id'],
                    'variant_id' => $line['variant_id'],
                    'quantity' => (string) $qty->toScale(4, RoundingMode::HalfUp),
                    'unit_price' => (string) $price->toScale(4, RoundingMode::HalfUp),
                    'subtotal' => (string) $lineSub->toScale(4, RoundingMode::HalfUp),
                    'tax_amount' => (string) $lineTax->toScale(4, RoundingMode::HalfUp),
                    'total_amount' => (string) $lineTot->toScale(4, RoundingMode::HalfUp),
                ];
            }

            $totalAmount = $subtotalSum->plus($taxSum);

            $bill = SupplierBill::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'company_id' => $data['company_id'],
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'bill_number' => $billNumber,
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'],
                'subtotal' => (string) $subtotalSum->toScale(4, RoundingMode::HalfUp),
                'tax_amount' => (string) $taxSum->toScale(4, RoundingMode::HalfUp),
                'total_amount' => (string) $totalAmount->toScale(4, RoundingMode::HalfUp),
                'status' => 'draft',
                'created_by' => Auth::id() ?? (string) Str::uuid(),
            ]);

            foreach ($lineData as $ld) {
                $ld['supplier_bill_id'] = $bill->id;
                SupplierBillLine::create($ld);
            }

            return $bill->load('lines');
        });
    }
}
