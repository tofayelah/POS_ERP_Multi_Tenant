<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\StockBalance;
use App\Models\StockReservation;
use App\Modules\Inventory\Contracts\StockReservationServiceContract;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockReservationService implements StockReservationServiceContract
{
    /**
     * Reserve inventory for a document. Increments stock_balances.quantity_reserved.
     */
    public function reserveStock(
        string $companyId,
        string $warehouseId,
        ?string $stockLocationId,
        string $productVariantId,
        string $quantity,
        string $documentType,
        string $documentId,
        int $ttlMinutes = 15,
        ?string $batchNumber = null
    ): StockReservation {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant context must be active to create reservations.');
        }

        $qtyDecimal = BigDecimal::of($quantity);
        if ($qtyDecimal->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new RuntimeException('Reservation quantity must be strictly positive.');
        }

        return DB::transaction(function () use (
            $tenantId,
            $companyId,
            $warehouseId,
            $stockLocationId,
            $productVariantId,
            $batchNumber,
            $qtyDecimal,
            $documentType,
            $documentId,
            $ttlMinutes
        ) {
            $balance = StockBalance::where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $productVariantId)
                ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId), fn ($q) => $q->whereNull('stock_location_id'))
                ->when($batchNumber, fn ($q) => $q->where('batch_number', $batchNumber), fn ($q) => $q->whereNull('batch_number'))
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                throw new InsufficientStockException("No stock balance row found for variant {$productVariantId} in warehouse {$warehouseId}.");
            }

            $available = BigDecimal::of($balance->quantity_available);
            if ($available->isLessThan($qtyDecimal)) {
                throw new InsufficientStockException("Cannot reserve {$qtyDecimal} units. Only {$available} units available.");
            }

            $newReserved = BigDecimal::of($balance->quantity_reserved)->plus($qtyDecimal);
            $balance->quantity_reserved = (string) $newReserved->toScale(4, RoundingMode::HalfUp);
            $balance->save();

            return StockReservation::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'stock_location_id' => $stockLocationId,
                'product_variant_id' => $productVariantId,
                'batch_number' => $batchNumber,
                'quantity' => (string) $qtyDecimal->toScale(4, RoundingMode::HalfUp),
                'document_type' => $documentType,
                'document_id' => $documentId,
                'expires_at' => Carbon::now()->addMinutes($ttlMinutes),
                'status' => 'active',
            ]);
        });
    }

    /**
     * Release an active reservation, decrementing stock_balances.quantity_reserved.
     */
    public function releaseReservation(string $reservationId): void
    {
        DB::transaction(function () use ($reservationId) {
            $reservation = StockReservation::where('id', $reservationId)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'active') {
                return;
            }

            $balance = StockBalance::where('warehouse_id', $reservation->warehouse_id)
                ->where('product_variant_id', $reservation->product_variant_id)
                ->when($reservation->stock_location_id, fn ($q) => $q->where('stock_location_id', $reservation->stock_location_id), fn ($q) => $q->whereNull('stock_location_id'))
                ->when($reservation->batch_number, fn ($q) => $q->where('batch_number', $reservation->batch_number), fn ($q) => $q->whereNull('batch_number'))
                ->lockForUpdate()
                ->first();

            if ($balance) {
                $reserved = BigDecimal::of($balance->quantity_reserved)->minus(BigDecimal::of($reservation->quantity));
                if ($reserved->isNegative()) {
                    $reserved = BigDecimal::zero();
                }
                $balance->quantity_reserved = (string) $reserved->toScale(4, RoundingMode::HalfUp);
                $balance->save();
            }

            $reservation->status = 'cancelled';
            $reservation->save();
        });
    }

    /**
     * Consume an active reservation into a finalized sale.
     */
    public function consumeReservation(string $reservationId): void
    {
        DB::transaction(function () use ($reservationId) {
            $reservation = StockReservation::where('id', $reservationId)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'active') {
                return;
            }

            $balance = StockBalance::where('warehouse_id', $reservation->warehouse_id)
                ->where('product_variant_id', $reservation->product_variant_id)
                ->when($reservation->stock_location_id, fn ($q) => $q->where('stock_location_id', $reservation->stock_location_id), fn ($q) => $q->whereNull('stock_location_id'))
                ->when($reservation->batch_number, fn ($q) => $q->where('batch_number', $reservation->batch_number), fn ($q) => $q->whereNull('batch_number'))
                ->lockForUpdate()
                ->first();

            if ($balance) {
                $reserved = BigDecimal::of($balance->quantity_reserved)->minus(BigDecimal::of($reservation->quantity));
                if ($reserved->isNegative()) {
                    $reserved = BigDecimal::zero();
                }
                $balance->quantity_reserved = (string) $reserved->toScale(4, RoundingMode::HalfUp);
                $balance->save();
            }

            $reservation->status = 'consumed';
            $reservation->save();
        });
    }

    /**
     * Release all expired active reservations.
     * Returns count of released reservations.
     */
    public function releaseExpiredReservations(): int
    {
        return DB::transaction(function () {
            $expiredReservations = StockReservation::where('status', 'active')
                ->where('expires_at', '<', Carbon::now())
                ->lockForUpdate()
                ->get();

            $releasedCount = 0;
            foreach ($expiredReservations as $reservation) {
                $balance = StockBalance::where('warehouse_id', $reservation->warehouse_id)
                    ->where('product_variant_id', $reservation->product_variant_id)
                    ->when($reservation->stock_location_id, fn ($q) => $q->where('stock_location_id', $reservation->stock_location_id), fn ($q) => $q->whereNull('stock_location_id'))
                    ->when($reservation->batch_number, fn ($q) => $q->where('batch_number', $reservation->batch_number), fn ($q) => $q->whereNull('batch_number'))
                    ->lockForUpdate()
                    ->first();

                if ($balance) {
                    $reserved = BigDecimal::of($balance->quantity_reserved)->minus(BigDecimal::of($reservation->quantity));
                    if ($reserved->isNegative()) {
                        $reserved = BigDecimal::zero();
                    }
                    $balance->quantity_reserved = (string) $reserved->toScale(4, RoundingMode::HalfUp);
                    $balance->save();
                }

                $reservation->status = 'expired';
                $reservation->save();
                $releasedCount++;
            }

            return $releasedCount;
        });
    }
}
