<?php

namespace App\Modules\Inventory\Contracts;

use App\Models\StockReservation;

interface StockReservationServiceContract
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
    ): StockReservation;

    /**
     * Release an active reservation, decrementing stock_balances.quantity_reserved.
     */
    public function releaseReservation(string $reservationId): void;

    /**
     * Consume an active reservation into a finalized sale.
     */
    public function consumeReservation(string $reservationId): void;

    /**
     * Release all expired active reservations.
     * Returns count of released reservations.
     */
    public function releaseExpiredReservations(): int;
}
