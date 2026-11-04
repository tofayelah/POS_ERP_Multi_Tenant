<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\CostLayer;
use App\Modules\Inventory\DTOs\FifoConsumptionResult;

interface CostingServiceContract
{
    /**
     * Add an inbound FIFO cost layer upon receipt or positive adjustment.
     */
    public function addCostLayer(
        string $companyId,
        string $warehouseId,
        ?string $stockLocationId,
        string $productVariantId,
        string $quantity,
        string $unitCost,
        string $receiptDate,
        string $referenceType,
        string $referenceId,
        ?string $batchNumber = null,
        bool $isProvisional = false
    ): CostLayer;

    /**
     * Consume cost layers on a FIFO basis for an outbound sale or transfer.
     */
    public function consumeFifoLayers(
        string $warehouseId,
        string $productVariantId,
        string $quantityToConsume,
        ?string $batchNumber = null
    ): FifoConsumptionResult;

    /**
     * Recompute or calculate WAC for an inbound batch.
     */
    public function calculateNewWac(
        string $currentQty,
        string $currentWac,
        string $inboundQty,
        string $inboundCost
    ): string;
}
