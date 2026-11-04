<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\StockMovement;
use App\Models\StockOversellException;

interface OfflineOversellServiceContract
{
    /**
     * Record an offline POS sale synchronization.
     * Guaranteed to commit even under inventory deficit, opening a supervisor exception if oversold.
     */
    public function recordOfflineSale(
        string $companyId,
        string $warehouseId,
        string $productVariantId,
        string $quantity,
        string $unitCost,
        string $salesInvoiceId,
        ?string $stockLocationId = null,
        ?string $batchNumber = null,
        ?string $branchId = null
    ): StockMovement;

    /**
     * Resolve an open supervisor oversell exception.
     */
    public function resolveException(
        string $exceptionId,
        string $resolutionType,
        string $resolvedByDocumentId,
        ?string $resolvedByDocumentType = null,
        ?string $notes = null
    ): StockOversellException;
}
