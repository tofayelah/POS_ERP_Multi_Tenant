<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOversellException;
use App\Modules\Inventory\Contracts\OfflineOversellServiceContract;
use App\Modules\Inventory\Contracts\StockMovementServiceContract;
use App\Modules\Inventory\DTOs\StockMovementDTO;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OfflineOversellService implements OfflineOversellServiceContract
{
    public function __construct(
        protected StockMovementServiceContract $movementService
    ) {}

    public function recordOfflineSale(
        string $companyId,
        string $warehouseId,
        string $productVariantId,
        string $quantity,
        string $unitCost,
        string $salesInvoiceId,
        ?string $stockLocationId = null,
        ?string $batchNumber = null,
        ?string $branchId = null
    ): StockMovement {
        return DB::transaction(function () use (
            $companyId,
            $warehouseId,
            $productVariantId,
            $quantity,
            $unitCost,
            $salesInvoiceId,
            $stockLocationId,
            $batchNumber,
            $branchId
        ) {
            $tenantId = TenantContext::id();
            if (! $tenantId) {
                throw new RuntimeException('Tenant context must be active to record offline sales.');
            }

            // Check current on-hand quantity before movement
            $balance = StockBalance::where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $productVariantId)
                ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId), fn ($q) => $q->whereNull('stock_location_id'))
                ->when($batchNumber, fn ($q) => $q->where('batch_number', $batchNumber), fn ($q) => $q->whereNull('batch_number'))
                ->lockForUpdate()
                ->first();

            $onHand = $balance ? BigDecimal::of($balance->quantity_on_hand) : BigDecimal::zero();
            $saleQty = BigDecimal::of($quantity);
            $newOnHand = $onHand->minus($saleQty);

            // If sale creates a deficit, record supervisor exception
            if ($newOnHand->isNegative()) {
                $oversoldQty = $newOnHand->abs();

                StockOversellException::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'stock_location_id' => $stockLocationId,
                    'product_variant_id' => $productVariantId,
                    'quantity_oversold' => (string) $oversoldQty,
                    'sales_invoice_id' => $salesInvoiceId,
                    'cost_basis_used' => $unitCost,
                    'status' => 'OPEN',
                ]);
            }

            // Post movement as OFFLINE_OVERSELL
            return $this->movementService->recordMovement(new StockMovementDTO(
                companyId: $companyId,
                warehouseId: $warehouseId,
                stockLocationId: $stockLocationId,
                productVariantId: $productVariantId,
                movementType: 'OFFLINE_OVERSELL',
                quantity: (string) $saleQty->negated(),
                unitCost: $unitCost,
                referenceType: 'sales_invoice',
                referenceId: $salesInvoiceId,
                batchNumber: $batchNumber
            ));
        });
    }

    public function resolveException(
        string $exceptionId,
        string $resolutionType,
        string $resolvedByDocumentId,
        ?string $resolvedByDocumentType = null,
        ?string $notes = null
    ): StockOversellException {
        return DB::transaction(function () use (
            $exceptionId,
            $resolutionType,
            $resolvedByDocumentId,
            $resolvedByDocumentType,
            $notes
        ) {
            /** @var StockOversellException $exception */
            $exception = StockOversellException::where('id', $exceptionId)->lockForUpdate()->firstOrFail();

            $exception->status = $resolutionType;
            $exception->resolved_at = now();
            $exception->resolved_by_document_type = $resolvedByDocumentType ?? strtolower($resolutionType);
            $exception->resolved_by_document_id = $resolvedByDocumentId;
            if ($notes !== null) {
                $exception->notes = $notes;
            }
            $exception->save();

            return $exception;
        });
    }
}
