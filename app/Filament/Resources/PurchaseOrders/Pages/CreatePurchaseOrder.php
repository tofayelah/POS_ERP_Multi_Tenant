<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderLineDTO;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = new CreatePurchaseOrderLineDTO(
                productId: $line['product_id'],
                variantId: $line['variant_id'],
                orderedQuantity: (string) ($line['ordered_quantity'] ?? '1.0000'),
                unitPrice: (string) ($line['unit_price'] ?? '0.0000'),
                taxAmount: (string) ($line['tax_amount'] ?? '0.0000')
            );
        }

        $dto = new CreatePurchaseOrderDTO(
            companyId: $data['company_id'],
            branchId: $data['branch_id'],
            supplierId: $data['supplier_id'],
            orderDate: $data['order_date'],
            expectedDeliveryDate: $data['expected_delivery_date'] ?? null,
            createdBy: (string) (Auth::id() ?? Str::uuid()),
            lines: $lines
        );

        /** @var ProcurementDocumentChainContract $service */
        $service = app(ProcurementDocumentChainContract::class);

        return $service->createPurchaseOrder($dto);
    }
}
