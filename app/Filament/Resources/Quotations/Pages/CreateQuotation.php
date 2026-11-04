<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\DTOs\CreateQuotationDTO;
use App\Modules\Sales\DTOs\CreateQuotationLineDTO;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = new CreateQuotationLineDTO(
                productId: $line['product_id'],
                variantId: $line['variant_id'],
                quantity: (string) ($line['quantity'] ?? '1.0000'),
                unitPrice: (string) ($line['unit_price'] ?? '0.0000'),
                taxAmount: (string) ($line['tax_amount'] ?? '0.0000'),
                notes: $line['notes'] ?? null
            );
        }

        $dto = new CreateQuotationDTO(
            companyId: $data['company_id'],
            branchId: $data['branch_id'],
            customerId: $data['customer_id'],
            quotationDate: $data['quotation_date'],
            validUntil: $data['valid_until'],
            createdBy: (string) (Auth::id() ?? Str::uuid()),
            lines: $lines
        );

        /** @var SalesDocumentChainContract $service */
        $service = app(SalesDocumentChainContract::class);

        return $service->createQuotation($dto);
    }
}
