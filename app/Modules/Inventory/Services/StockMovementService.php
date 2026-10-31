<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockMovementService implements StockMovementServiceContract
{
    /**
     * Record an immutable stock movement and update stock_balances in the same database transaction.
     */
    public function recordMovement(StockMovementDTO $dto): StockMovement
    {
        if (! DB::connection()->isInTransaction()) {
            throw new RuntimeException('Stock movement must be recorded within an active database transaction.');
        }

        $tenantId = TenantContext::getTenantId();

        if ($dto->movementType === 'OFFLINE_OVERSELL') {
            DB::statement("SELECT set_config('app.allow_negative_stock', 'true', true)");
        }

        // Lock or initialize the balance row
        $balance = StockBalance::where('warehouse_id', $dto->warehouseId)
            ->where('product_variant_id', $dto->productVariantId)
            ->when($dto->stockLocationId, fn ($q) => $q->where('stock_location_id', $dto->stockLocationId), fn ($q) => $q->whereNull('stock_location_id'))
            ->when($dto->batchNumber, fn ($q) => $q->where('batch_number', $dto->batchNumber), fn ($q) => $q->whereNull('batch_number'))
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = new StockBalance([
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'warehouse_id' => $dto->warehouseId,
                'stock_location_id' => $dto->stockLocationId,
                'product_variant_id' => $dto->productVariantId,
                'batch_number' => $dto->batchNumber,
                'expiry_date' => $dto->expiryDate ? Carbon::parse($dto->expiryDate) : null,
                'quantity_on_hand' => '0.0000',
                'quantity_reserved' => '0.0000',
                'weighted_avg_cost' => '0.0000',
            ]);
        }

        $onHand = BigDecimal::of($balance->quantity_on_hand ?? '0.0000');
        $deltaQty = BigDecimal::of($dto->quantity);
        $newOnHand = $onHand->plus($deltaQty);

        if ($dto->movementType !== 'OFFLINE_OVERSELL' && $newOnHand->isNegative()) {
            throw new InsufficientStockException("Product variant {$dto->productVariantId} has insufficient stock in warehouse {$dto->warehouseId}.");
        }

        $unitCost = BigDecimal::of($dto->unitCost);
        $currentWac = BigDecimal::of($balance->weighted_avg_cost ?? '0.0000');

        if ($deltaQty->isPositive() && $unitCost->isPositive()) {
            if ($onHand->isLessThanOrEqualTo(BigDecimal::zero())) {
                $newWac = $unitCost;
            } else {
                $totalCurrentVal = $onHand->multipliedBy($currentWac);
                $totalInboundVal = $deltaQty->multipliedBy($unitCost);
                $newWac = $totalCurrentVal->plus($totalInboundVal)->dividedBy($newOnHand, 4, RoundingMode::HalfUp);
            }
        } else {
            $newWac = $currentWac;
        }

        $totalCost = (string) $deltaQty->abs()->multipliedBy($unitCost)->toScale(4, RoundingMode::HalfUp);

        $movement = StockMovement::create([
            'tenant_id' => $tenantId,
            'company_id' => $dto->companyId,
            'warehouse_id' => $dto->warehouseId,
            'stock_location_id' => $dto->stockLocationId,
            'product_variant_id' => $dto->productVariantId,
            'batch_number' => $dto->batchNumber,
            'expiry_date' => $dto->expiryDate ? Carbon::parse($dto->expiryDate) : null,
            'movement_type' => $dto->movementType,
            'quantity' => (string) $deltaQty->toScale(4, RoundingMode::HalfUp),
            'unit_cost' => (string) $unitCost->toScale(4, RoundingMode::HalfUp),
            'total_cost' => $totalCost,
            'reference_type' => $dto->referenceType,
            'reference_id' => $dto->referenceId,
            'movement_date' => $dto->movementDate ? Carbon::parse($dto->movementDate) : now(),
        ]);

        $balance->quantity_on_hand = (string) $newOnHand->toScale(4, RoundingMode::HalfUp);
        $balance->weighted_avg_cost = (string) $newWac->toScale(4, RoundingMode::HalfUp);
        if ($dto->expiryDate && ! $balance->expiry_date) {
            $balance->expiry_date = Carbon::parse($dto->expiryDate);
        }
        $balance->save();

        return $movement;
    }

    /**
     * Batch record multiple stock movements in deterministic sorted order with deadlock retry.
     *
     * @param  array<StockMovementDTO>  $dtos
     * @return array<StockMovement>
     */
    public function recordMovements(array $dtos): array
    {
        // Sort DTOs by productVariantId ascending to enforce Section 13.1 deterministic lock order
        usort($dtos, fn (StockMovementDTO $a, StockMovementDTO $b) => strcmp($a->productVariantId, $b->productVariantId));

        return DB::transaction(function () use ($dtos) {
            $movements = [];
            foreach ($dtos as $dto) {
                $movements[] = $this->recordMovement($dto);
            }

            return $movements;
        }, attempts: 3);
    }
}
