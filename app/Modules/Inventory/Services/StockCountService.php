<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockCountLine;
use App\Models\StockCountSession;
use App\Models\StockMovement;
use App\Modules\Inventory\Contracts\StockCountServiceContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockCountService implements StockCountServiceContract
{
    public function __construct(
        protected StockMovementServiceContract $movementService
    ) {}

    public function startSession(
        string $companyId,
        string $warehouseId,
        ?string $notes = null
    ): StockCountSession {
        $tenantId = TenantContext::id();

        return DB::transaction(function () use ($tenantId, $companyId, $warehouseId, $notes) {
            $sessionNumber = 'COUNT-'.date('Ymd').'-'.Str::upper(Str::random(6));

            $session = StockCountSession::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'session_number' => $sessionNumber,
                'status' => 'in_progress',
                'snapshot_at' => now(),
                'notes' => $notes,
            ]);

            $balances = StockBalance::where('company_id', $companyId)
                ->where('warehouse_id', $warehouseId)
                ->get();

            foreach ($balances as $balance) {
                $lastMovementQuery = StockMovement::where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_variant_id', $balance->product_variant_id);

                if ($balance->stock_location_id !== null) {
                    $lastMovementQuery->where('stock_location_id', $balance->stock_location_id);
                } else {
                    $lastMovementQuery->whereNull('stock_location_id');
                }

                if ($balance->batch_number !== null) {
                    $lastMovementQuery->where('batch_number', $balance->batch_number);
                } else {
                    $lastMovementQuery->whereNull('batch_number');
                }

                $lastMovement = $lastMovementQuery->latest('created_at')->first();

                StockCountLine::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'session_id' => $session->id,
                    'stock_location_id' => $balance->stock_location_id,
                    'product_variant_id' => $balance->product_variant_id,
                    'last_movement_id' => $lastMovement?->id,
                    'batch_number' => $balance->batch_number,
                    'snapshot_quantity' => $balance->quantity_on_hand,
                    'in_flight_movements' => '0.0000',
                    'counted_quantity' => null,
                    'variance_quantity' => '0.0000',
                    'variance_value' => '0.0000',
                ]);
            }

            return $session->load('lines');
        });
    }

    public function recordCount(
        string $sessionId,
        string $productVariantId,
        string $countedQuantity,
        ?string $stockLocationId = null,
        ?string $batchNumber = null
    ): StockCountLine {
        $session = StockCountSession::findOrFail($sessionId);

        $lineQuery = StockCountLine::where('session_id', $sessionId)
            ->where('product_variant_id', $productVariantId);

        if ($stockLocationId !== null) {
            $lineQuery->where('stock_location_id', $stockLocationId);
        } else {
            $lineQuery->whereNull('stock_location_id');
        }

        if ($batchNumber !== null) {
            $lineQuery->where('batch_number', $batchNumber);
        } else {
            $lineQuery->whereNull('batch_number');
        }

        $line = $lineQuery->first();

        if (! $line) {
            $line = StockCountLine::create([
                'tenant_id' => $session->tenant_id,
                'company_id' => $session->company_id,
                'session_id' => $session->id,
                'stock_location_id' => $stockLocationId,
                'product_variant_id' => $productVariantId,
                'last_movement_id' => null,
                'batch_number' => $batchNumber,
                'snapshot_quantity' => '0.0000',
                'in_flight_movements' => '0.0000',
                'counted_quantity' => $countedQuantity,
                'variance_quantity' => '0.0000',
                'variance_value' => '0.0000',
            ]);
        } else {
            $line->counted_quantity = $countedQuantity;
            $line->save();
        }

        return $line;
    }

    public function reconcileAndApprove(
        string $sessionId,
        ?string $approvedByUserId = null
    ): StockCountSession {
        return DB::transaction(function () use ($sessionId, $approvedByUserId) {
            /** @var StockCountSession $session */
            $session = StockCountSession::where('id', $sessionId)->lockForUpdate()->firstOrFail();

            if ($session->status === 'reconciled') {
                return $session;
            }

            $lines = StockCountLine::where('session_id', $session->id)->get();

            foreach ($lines as $line) {
                $movementsQuery = StockMovement::where('company_id', $session->company_id)
                    ->where('warehouse_id', $session->warehouse_id)
                    ->where('product_variant_id', $line->product_variant_id)
                    ->where('reference_type', '!=', 'stock_count_session')
                    ->where('created_at', '>=', $session->snapshot_at);

                if ($line->last_movement_id !== null) {
                    $movementsQuery->where('id', '!=', $line->last_movement_id);
                }

                if ($line->stock_location_id !== null) {
                    $movementsQuery->where('stock_location_id', $line->stock_location_id);
                } else {
                    $movementsQuery->whereNull('stock_location_id');
                }

                if ($line->batch_number !== null) {
                    $movementsQuery->where('batch_number', $line->batch_number);
                } else {
                    $movementsQuery->whereNull('batch_number');
                }

                $movements = $movementsQuery->get();

                $inFlightSum = BigDecimal::of('0.0000');
                foreach ($movements as $movement) {
                    $inFlightSum = $inFlightSum->plus(BigDecimal::of($movement->quantity));
                }

                $line->in_flight_movements = (string) $inFlightSum;

                $snapshot = BigDecimal::of($line->snapshot_quantity);
                $expected = $snapshot->plus($inFlightSum);

                if ($line->counted_quantity !== null) {
                    $counted = BigDecimal::of($line->counted_quantity);
                    $variance = $counted->minus($expected);
                    $line->variance_quantity = (string) $variance;

                    $variant = ProductVariant::find($line->product_variant_id);
                    $unitCost = $variant ? $variant->cost_price : '0.0000';
                    $line->variance_value = (string) $variance->multipliedBy(BigDecimal::of($unitCost))->toScale(4, RoundingMode::HalfUp);

                    if (! $variance->isZero()) {
                        $this->movementService->recordMovement(new StockMovementDTO(
                            companyId: $session->company_id,
                            warehouseId: $session->warehouse_id,
                            stockLocationId: $line->stock_location_id,
                            productVariantId: $line->product_variant_id,
                            movementType: 'STOCK_COUNT_ADJUSTMENT',
                            quantity: (string) $variance,
                            unitCost: $unitCost,
                            referenceType: 'stock_count_session',
                            referenceId: $session->id,
                            batchNumber: $line->batch_number
                        ));
                    }
                }

                $line->save();
            }

            $session->status = 'reconciled';
            $session->completed_at = now();
            $session->approved_by_user_id = $approvedByUserId;
            $session->save();

            return $session->load('lines');
        });
    }
}
