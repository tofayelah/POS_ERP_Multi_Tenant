<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\LandedCostVoucher;
use App\Modules\Inventory\DTOs\CreateLandedCostVoucherDTO;

interface LandedCostAllocationContract
{
    /**
     * Create a draft landed cost voucher with expense lines, receipts, and calculated allocations.
     */
    public function createVoucher(CreateLandedCostVoucherDTO $dto): LandedCostVoucher;

    /**
     * Post landed cost voucher.
     * Capitalizes cost into on-hand stock and updates AVCO; expenses to COGS if already sold out.
     * Generates a balanced double-entry GL journal entry.
     */
    public function postVoucher(
        string $voucherId,
        string $postedBy,
        ?string $periodId = null,
        ?string $inventoryValuationAccountId = null,
        ?string $landedCostClearingAccountId = null,
        ?string $cogsAccountId = null
    ): LandedCostVoucher;

    /**
     * Cancel a draft landed cost voucher.
     */
    public function cancelVoucher(string $voucherId, string $cancelledBy): LandedCostVoucher;
}
