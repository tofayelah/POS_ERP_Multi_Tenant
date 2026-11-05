<?php

namespace App\Filament\Resources\SupplierQuotations\Pages;

use App\Filament\Resources\SupplierQuotations\SupplierQuotationResource;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\DTOs\CreateSupplierQuotationDTO;
use App\Modules\Procurement\DTOs\CreateSupplierQuotationLineDTO;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateSupplierQuotation extends CreateRecord
{
    protected static string $resource = SupplierQuotationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = new CreateSupplierQuotationLineDTO(
                productId: $line['product_id'],
                variantId: $line['variant_id'],
                requestedQuantity: (string) ($line['requested_quantity'] ?? '1.0000'),
                quotedUnitPrice: (string) ($line['quoted_unit_price'] ?? '0.0000'),
                discountAmount: (string) ($line['discount_amount'] ?? '0.0000'),
                taxRate: (string) ($line['tax_rate'] ?? '0.0000'),
                leadTimeDays: (int) ($line['lead_time_days'] ?? 0),
                isSelected: (bool) ($line['is_selected'] ?? false)
            );
        }

        $dto = new CreateSupplierQuotationDTO(
            companyId: $data['company_id'],
            branchId: $data['branch_id'],
            supplierId: $data['supplier_id'],
            rfqNumber: $data['rfq_number'],
            supplierQuoteReference: $data['supplier_quote_reference'] ?? null,
            quoteDate: $data['quote_date'],
            validUntil: $data['valid_until'],
            createdBy: (string) (Auth::id() ?? Str::uuid()),
            currency: $data['currency'] ?? 'BDT',
            lines: $lines
        );

        /** @var ProcurementDocumentChainContract $service */
        $service = app(ProcurementDocumentChainContract::class);

        return $service->createSupplierQuotation($dto);
    }
}
