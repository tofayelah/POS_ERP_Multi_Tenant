<?php

namespace App\Modules\Procurement\Contracts;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SupplierBill;
use App\Models\SupplierQuotation;
use App\Modules\Procurement\DTOs\CreateGoodsReceiptDTO;
use App\Modules\Procurement\DTOs\CreatePurchaseOrderDTO;
use App\Modules\Procurement\DTOs\CreateSupplierQuotationDTO;
use App\Modules\Procurement\DTOs\CreateVendorBillFromReceiptDTO;

interface ProcurementDocumentChainContract
{
    public function createPurchaseOrder(CreatePurchaseOrderDTO $dto): PurchaseOrder;

    public function confirmPurchaseOrder(string $purchaseOrderId): PurchaseOrder;

    public function createGoodsReceipt(CreateGoodsReceiptDTO $dto): GoodsReceipt;

    public function confirmGoodsReceipt(string $goodsReceiptId): GoodsReceipt;

    public function createVendorBillFromReceipt(CreateVendorBillFromReceiptDTO $dto): SupplierBill;

    public function cancelPurchaseOrder(string $purchaseOrderId): PurchaseOrder;

    public function createSupplierQuotation(CreateSupplierQuotationDTO $dto): SupplierQuotation;

    public function convertSupplierQuotationToPurchaseOrder(string $supplierQuotationId, string $userId): PurchaseOrder;
}
