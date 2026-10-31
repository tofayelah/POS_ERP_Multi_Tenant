<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\CostLayer;
use App\Modules\Inventory\Contracts\CostingServiceContract;
use App\Modules\Inventory\DTOs\FifoConsumptionResult;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use RuntimeException;

class CostingService implements CostingServiceContract
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
    ): CostLayer {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant context must be active to create cost layers.');
        }

        $qtyDecimal = (string) BigDecimal::of($quantity)->toScale(4, RoundingMode::HalfUp);
        $costDecimal = (string) BigDecimal::of($unitCost)->toScale(4, RoundingMode::HalfUp);

        return CostLayer::create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'stock_location_id' => $stockLocationId,
            'product_variant_id' => $productVariantId,
            'batch_number' => $batchNumber,
            'quantity_received' => $qtyDecimal,
            'quantity_remaining' => $qtyDecimal,
            'unit_cost' => $costDecimal,
            'receipt_date' => Carbon::parse($receiptDate)->toDateString(),
            'is_provisional' => $isProvisional,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Consume cost layers on a FIFO basis for an outbound sale or transfer.
     */
    public function consumeFifoLayers(
        string $warehouseId,
        string $productVariantId,
        string $quantityToConsume,
        ?string $batchNumber = null
    ): FifoConsumptionResult {
        $needed = BigDecimal::of($quantityToConsume);
        $totalCogs = BigDecimal::zero();
        $totalConsumed = BigDecimal::zero();
        $layersConsumed = [];

        $layers = CostLayer::where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $productVariantId)
            ->when($batchNumber, fn ($q) => $q->where('batch_number', $batchNumber), fn ($q) => $q->whereNull('batch_number'))
            ->where('quantity_remaining', '>', 0)
            ->orderBy('receipt_date', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->lockForUpdate()
            ->get();

        foreach ($layers as $layer) {
            if ($needed->isLessThanOrEqualTo(BigDecimal::zero())) {
                break;
            }

            $available = BigDecimal::of($layer->quantity_remaining);
            $take = $needed->isLessThan($available) ? $needed : $available;
            $unitCost = BigDecimal::of($layer->unit_cost);
            $layerCost = $take->multipliedBy($unitCost);

            $newRemaining = $available->minus($take);
            $layer->quantity_remaining = (string) $newRemaining->toScale(4, RoundingMode::HalfUp);
            $layer->save();

            $totalCogs = $totalCogs->plus($layerCost);
            $totalConsumed = $totalConsumed->plus($take);
            $needed = $needed->minus($take);

            $layersConsumed[] = [
                'layer_id' => $layer->id,
                'quantity_consumed' => (string) $take->toScale(4, RoundingMode::HalfUp),
                'unit_cost' => (string) $unitCost->toScale(4, RoundingMode::HalfUp),
                'total_cost' => (string) $layerCost->toScale(4, RoundingMode::HalfUp),
            ];
        }

        return new FifoConsumptionResult(
            totalCogs: (string) $totalCogs->toScale(4, RoundingMode::HalfUp),
            consumedQuantity: (string) $totalConsumed->toScale(4, RoundingMode::HalfUp),
            remainingShortfall: (string) $needed->toScale(4, RoundingMode::HalfUp),
            layersConsumed: $layersConsumed
        );
    }

    /**
     * Recompute or calculate WAC for an inbound batch.
     */
    public function calculateNewWac(
        string $currentQty,
        string $currentWac,
        string $inboundQty,
        string $inboundCost
    ): string {
        $curQty = BigDecimal::of($currentQty);
        $inQty = BigDecimal::of($inboundQty);
        $inCost = BigDecimal::of($inboundCost);

        if ($curQty->isLessThanOrEqualTo(BigDecimal::zero())) {
            return (string) $inCost->toScale(4, RoundingMode::HalfUp);
        }

        $curWac = BigDecimal::of($currentWac);
        $totalVal = $curQty->multipliedBy($curWac)->plus($inQty->multipliedBy($inCost));
        $totalQty = $curQty->plus($inQty);

        return (string) $totalVal->dividedBy($totalQty, 4, RoundingMode::HalfUp);
    }
}
