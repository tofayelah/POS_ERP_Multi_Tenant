<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Modules\Inventory\DTOs\InventoryReconciliationDTO;

interface InventoryGlReconciliationContract
{
    /**
     * Compute real-time stock valuation and reconcile against GL Account 1040 (Merchandise Inventory).
     */
    public function getReconciliationReport(string $companyId): InventoryReconciliationDTO;

    /**
     * Synchronously post balanced double-entry GL journal entry for an inventory movement.
     */
    public function postInventoryGlJournal(StockMovement $movement, ?string $userId = null): ?JournalEntry;
}
