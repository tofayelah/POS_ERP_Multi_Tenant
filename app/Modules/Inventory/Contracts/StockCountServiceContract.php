<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\StockCountLine;
use App\Models\StockCountSession;

interface StockCountServiceContract
{
    /**
     * Start a stock count session by creating the session and snapshotting current balances.
     */
    public function startSession(
        string $companyId,
        string $warehouseId,
        ?string $notes = null
    ): StockCountSession;

    /**
     * Record a physical count for a variant/location/batch in the session.
     */
    public function recordCount(
        string $sessionId,
        string $productVariantId,
        string $countedQuantity,
        ?string $stockLocationId = null,
        ?string $batchNumber = null
    ): StockCountLine;

    /**
     * Reconcile in-flight movements, calculate physical variance, post adjustments and approve the session.
     */
    public function reconcileAndApprove(
        string $sessionId,
        ?string $approvedByUserId = null
    ): StockCountSession;
}
