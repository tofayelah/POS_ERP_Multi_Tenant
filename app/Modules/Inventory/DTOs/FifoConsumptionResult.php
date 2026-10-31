<?php

namespace App\Modules\Inventory\DTOs;

readonly class FifoConsumptionResult
{
    /**
     * @param  array<array{layer_id: string, quantity_consumed: string, unit_cost: string, total_cost: string}>  $layersConsumed
     */
    public function __construct(
        public string $totalCogs,
        public string $consumedQuantity,
        public string $remainingShortfall,
        public array $layersConsumed,
    ) {}
}
