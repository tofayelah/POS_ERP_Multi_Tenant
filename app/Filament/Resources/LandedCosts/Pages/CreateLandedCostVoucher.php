<?php

namespace App\Filament\Resources\LandedCosts\Pages;

use App\Filament\Resources\LandedCosts\LandedCostVoucherResource;
use App\Modules\Inventory\Contracts\LandedCostAllocationContract;
use App\Modules\Inventory\DTOs\CreateLandedCostVoucherDTO;
use App\Modules\Inventory\DTOs\CreateLandedCostVoucherLineDTO;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateLandedCostVoucher extends CreateRecord
{
    protected static string $resource = LandedCostVoucherResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = new CreateLandedCostVoucherLineDTO(
                expenseType: $line['expense_type'],
                description: $line['description'],
                amount: (string) ($line['amount'] ?? '0.0000'),
                taxAmount: (string) ($line['tax_amount'] ?? '0.0000')
            );
        }

        $dto = new CreateLandedCostVoucherDTO(
            companyId: $data['company_id'],
            branchId: $data['branch_id'],
            voucherDate: $data['voucher_date'],
            allocationMethod: $data['allocation_method'] ?? 'by_value',
            currency: $data['currency'] ?? 'BDT',
            exchangeRate: (string) ($data['exchange_rate'] ?? '1.000000'),
            notes: $data['notes'] ?? null,
            goodsReceiptIds: $data['goods_receipt_ids'] ?? [],
            lines: $lines,
            createdBy: (string) (Auth::id() ?? Str::uuid()),
            voucherNumber: $data['voucher_number'] ?? null
        );

        /** @var LandedCostAllocationContract $service */
        $service = app(LandedCostAllocationContract::class);

        return $service->createVoucher($dto);
    }
}
