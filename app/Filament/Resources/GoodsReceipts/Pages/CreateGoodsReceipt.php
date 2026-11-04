<?php

namespace App\Filament\Resources\GoodsReceipts\Pages;

use App\Filament\Resources\GoodsReceipts\GoodsReceiptResource;
use App\Modules\Procurement\Contracts\ProcurementDocumentChainContract;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptDTO;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptLineDTO;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateGoodsReceipt extends CreateRecord
{
    protected static string $resource = GoodsReceiptResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = [];
        foreach ($data['lines'] ?? [] as $line) {
            $lines[] = new CreateGoodsReceiptLineDTO(
                purchaseOrderLineId: $line['purchase_order_line_id'] ?? null,
                productId: $line['product_id'],
                variantId: $line['variant_id'],
                receivedQuantity: (string) ($line['received_quantity'] ?? '1.0000'),
                unitCost: (string) ($line['unit_cost'] ?? '0.0000'),
                batchNumber: $line['batch_number'] ?? null,
                expiryDate: $line['expiry_date'] ?? null
            );
        }

        $dto = new CreateGoodsReceiptDTO(
            companyId: $data['company_id'],
            branchId: $data['branch_id'],
            warehouseId: $data['warehouse_id'],
            supplierId: $data['supplier_id'],
            purchaseOrderId: $data['purchase_order_id'] ?? null,
            receiptDate: $data['receipt_date'],
            createdBy: Auth::id() ?? (string) Str::uuid(),
            challanNumber: $data['challan_number'] ?? null,
            lines: $lines
        );

        /** @var ProcurementDocumentChainContract $service */
        $service = app(ProcurementDocumentChainContract::class);

        return $service->createGoodsReceipt($dto);
    }
}
