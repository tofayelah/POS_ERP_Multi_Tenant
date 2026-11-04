<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\LandedCost;
use App\Modules\Inventory\DTOs\LandedCostAllocationDTO;

interface LandedCostServiceContract
{
    /**
     * Create a draft landed cost record with line allocations.
     *
     * @param  array<int, LandedCostAllocationDTO>  $allocations
     */
    public function createLandedCost(
        string $companyId,
        string $costType,
        string $amount,
        string $allocationMethod = 'by_quantity',
        array $allocations = [],
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null
    ): LandedCost;

    /**
     * Post landed cost allocations.
     * Capitalizes cost into on-hand stock if available; expenses directly to COGS if already sold out.
     */
    public function postLandedCost(
        string $landedCostId,
        ?string $periodId = null,
        ?string $cogsAccountId = null,
        ?string $apAccrualAccountId = null,
        ?string $postedBy = null
    ): LandedCost;
}
