<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\StockTransfer;
use App\Modules\Inventory\DTOs\StockTransferItemDTO;

interface StockTransferServiceContract
{
    /**
     * Create a new draft stock transfer between two warehouses.
     *
     * @param  array<int, StockTransferItemDTO>  $items
     */
    public function createTransfer(
        string $companyId,
        string $sourceWarehouseId,
        string $destWarehouseId,
        array $items,
        ?string $vehicleDetails = null,
        ?string $driverPhone = null,
        ?string $notes = null
    ): StockTransfer;

    /**
     * Ship the transfer, decrementing stock from source warehouse and marking transfer as in_transit.
     */
    public function shipTransfer(string $transferId): StockTransfer;

    /**
     * Receive the transfer, incrementing stock in dest warehouse and logging discrepancies.
     *
     * @param  array<string, string>  $receipts  Map of line ID => quantity received
     */
    public function receiveTransfer(
        string $transferId,
        array $receipts,
        ?string $notes = null
    ): StockTransfer;
}
