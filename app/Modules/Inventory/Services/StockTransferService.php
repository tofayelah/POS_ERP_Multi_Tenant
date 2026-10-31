<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\Contracts\StockTransferServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use App\Modules\Inventory\DTOs\StockTransferItemDTO;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StockTransferService implements StockTransferServiceContract
{
    public function __construct(
        protected StockMovementServiceContract $movementService
    ) {}

    /**
     * @param  array<int, StockTransferItemDTO>  $items
     */
    public function createTransfer(
        string $companyId,
        string $sourceWarehouseId,
        string $destWarehouseId,
        array $items,
        ?string $vehicleDetails = null,
        ?string $driverPhone = null,
        ?string $notes = null
    ): StockTransfer {
        $tenantId = TenantContext::id();

        return DB::transaction(function () use (
            $tenantId,
            $companyId,
            $sourceWarehouseId,
            $destWarehouseId,
            $items,
            $vehicleDetails,
            $driverPhone,
            $notes
        ) {
            $transferNumber = 'TR-'.date('Ymd').'-'.Str::upper(Str::random(6));

            $transfer = StockTransfer::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'transfer_number' => $transferNumber,
                'source_warehouse_id' => $sourceWarehouseId,
                'dest_warehouse_id' => $destWarehouseId,
                'status' => 'draft',
                'vehicle_details' => $vehicleDetails,
                'driver_phone' => $driverPhone,
                'notes' => $notes,
            ]);

            foreach ($items as $item) {
                StockTransferLine::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'transfer_id' => $transfer->id,
                    'product_variant_id' => $item->productVariantId,
                    'stock_location_id' => $item->stockLocationId,
                    'dest_stock_location_id' => $item->destStockLocationId,
                    'batch_number' => $item->batchNumber,
                    'quantity_shipped' => $item->quantity,
                    'quantity_received' => '0.0000',
                    'discrepancy_quantity' => '0.0000',
                    'unit_cost' => $item->unitCost,
                    'notes' => $item->notes,
                ]);
            }

            return $transfer->load('lines');
        });
    }

    public function shipTransfer(string $transferId): StockTransfer
    {
        return DB::transaction(function () use ($transferId) {
            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::where('id', $transferId)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'draft') {
                throw new RuntimeException("Transfer {$transferId} cannot be shipped because status is {$transfer->status}");
            }

            foreach ($transfer->lines as $line) {
                $qtyToShip = BigDecimal::of($line->quantity_shipped);

                $this->movementService->recordMovement(new StockMovementDTO(
                    companyId: $transfer->company_id,
                    warehouseId: $transfer->source_warehouse_id,
                    stockLocationId: $line->stock_location_id,
                    productVariantId: $line->product_variant_id,
                    movementType: 'TRANSFER_OUT',
                    quantity: (string) $qtyToShip->negated(),
                    unitCost: $line->unit_cost,
                    referenceType: 'stock_transfer',
                    referenceId: $transfer->id,
                    batchNumber: $line->batch_number
                ));
            }

            $transfer->status = 'in_transit';
            $transfer->shipped_at = now();
            $transfer->save();

            return $transfer->load('lines');
        });
    }

    /**
     * @param  array<string, string>  $receipts
     */
    public function receiveTransfer(
        string $transferId,
        array $receipts,
        ?string $notes = null
    ): StockTransfer {
        return DB::transaction(function () use ($transferId, $receipts, $notes) {
            /** @var StockTransfer $transfer */
            $transfer = StockTransfer::where('id', $transferId)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== 'in_transit') {
                throw new RuntimeException("Transfer {$transferId} cannot be received because status is {$transfer->status}");
            }

            foreach ($transfer->lines as $line) {
                $receivedQtyStr = $receipts[$line->id] ?? $line->quantity_shipped;
                $shippedQty = BigDecimal::of($line->quantity_shipped);
                $receivedQty = BigDecimal::of($receivedQtyStr);
                $discrepancy = $shippedQty->minus($receivedQty);

                $line->quantity_received = (string) $receivedQty;
                $line->discrepancy_quantity = (string) $discrepancy;
                $line->save();

                if ($receivedQty->isPositive()) {
                    $this->movementService->recordMovement(new StockMovementDTO(
                        companyId: $transfer->company_id,
                        warehouseId: $transfer->dest_warehouse_id,
                        stockLocationId: $line->dest_stock_location_id,
                        productVariantId: $line->product_variant_id,
                        movementType: 'TRANSFER_IN',
                        quantity: (string) $receivedQty,
                        unitCost: $line->unit_cost,
                        referenceType: 'stock_transfer',
                        referenceId: $transfer->id,
                        batchNumber: $line->batch_number
                    ));
                }
            }

            $transfer->status = 'received';
            $transfer->received_at = now();
            if ($notes !== null) {
                $transfer->notes = $notes;
            }
            $transfer->save();

            return $transfer->load('lines');
        });
    }
}
