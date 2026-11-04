<?php

namespace App\Modules\Sales\Contracts;

use App\Models\DeliveryChallan;
use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Modules\Sales\DTOs\CreateDeliveryChallanDTO;
use App\Modules\Sales\DTOs\CreateInvoiceFromDeliveryDTO;
use App\Modules\Sales\DTOs\CreateQuotationDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderDTO;

interface SalesDocumentChainContract
{
    public function createQuotation(CreateQuotationDTO $dto): Quotation;

    public function convertQuotationToSalesOrder(string $quotationId, CreateSalesOrderDTO $dto): SalesOrder;

    public function createSalesOrder(CreateSalesOrderDTO $dto): SalesOrder;

    public function confirmSalesOrder(string $salesOrderId): SalesOrder;

    public function createDeliveryChallan(CreateDeliveryChallanDTO $dto): DeliveryChallan;

    public function confirmDeliveryChallan(string $challanId): DeliveryChallan;

    public function createInvoiceFromDelivery(CreateInvoiceFromDeliveryDTO $dto): SalesInvoice;

    public function cancelSalesOrder(string $salesOrderId): SalesOrder;
}
