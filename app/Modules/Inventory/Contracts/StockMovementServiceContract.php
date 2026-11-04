<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\StockMovement;
use App\Modules\Inventory\DTOs\StockMovementDTO;

interface StockMovementServiceContract
{
    /**
     * Record an immutable stock movement and update stock_balances in the same database transaction.
     */
    public function recordMovement(StockMovementDTO $dto): StockMovement;

    /**
     * Batch record multiple stock movements in deterministic sorted order with deadlock retry.
     *
     * @param  array<StockMovementDTO>  $dtos
     * @return array<StockMovement>
     */
    public function recordMovements(array $dtos): array;
}
