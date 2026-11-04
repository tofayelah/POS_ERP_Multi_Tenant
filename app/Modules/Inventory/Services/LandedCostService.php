<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\LandedCost;
use App\Models\LandedCostAllocation;
use App\Models\StockBalance;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Inventory\Contracts\LandedCostServiceContract;
use App\Modules\Inventory\DTOs\LandedCostAllocationDTO;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LandedCostService implements LandedCostServiceContract
{
    public function __construct(
        protected ?AccountingPostingServiceContract $accountingService = null
    ) {}

    /**
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
    ): LandedCost {
        $tenantId = TenantContext::id();

        return DB::transaction(function () use (
            $tenantId,
            $companyId,
            $costType,
            $amount,
            $allocationMethod,
            $allocations,
            $referenceType,
            $referenceId,
            $notes
        ) {
            $landedCost = LandedCost::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'cost_type' => $costType,
                'amount' => $amount,
                'allocation_method' => $allocationMethod,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'draft',
                'notes' => $notes,
            ]);

            foreach ($allocations as $alloc) {
                LandedCostAllocation::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'landed_cost_id' => $landedCost->id,
                    'product_variant_id' => $alloc->productVariantId,
                    'warehouse_id' => $alloc->warehouseId,
                    'stock_location_id' => $alloc->stockLocationId,
                    'batch_number' => $alloc->batchNumber,
                    'allocated_amount' => $alloc->allocatedAmount,
                    'adjustment_to_inventory' => '0.0000',
                    'adjustment_to_cogs' => '0.0000',
                    'is_posted' => false,
                ]);
            }

            return $landedCost->load('allocations');
        });
    }

    public function postLandedCost(
        string $landedCostId,
        ?string $periodId = null,
        ?string $cogsAccountId = null,
        ?string $apAccrualAccountId = null,
        ?string $postedBy = null
    ): LandedCost {
        return DB::transaction(function () use (
            $landedCostId,
            $periodId,
            $cogsAccountId,
            $apAccrualAccountId,
            $postedBy
        ) {
            /** @var LandedCost $landedCost */
            $landedCost = LandedCost::where('id', $landedCostId)->lockForUpdate()->firstOrFail();

            if ($landedCost->status === 'posted') {
                return $landedCost->load('allocations');
            }

            $totalCogsAdjustment = BigDecimal::zero();

            foreach ($landedCost->allocations as $allocation) {
                $balanceQuery = StockBalance::where('company_id', $landedCost->company_id)
                    ->where('warehouse_id', $allocation->warehouse_id)
                    ->where('product_variant_id', $allocation->product_variant_id);

                if ($allocation->stock_location_id !== null) {
                    $balanceQuery->where('stock_location_id', $allocation->stock_location_id);
                } else {
                    $balanceQuery->whereNull('stock_location_id');
                }

                if ($allocation->batch_number !== null) {
                    $balanceQuery->where('batch_number', $allocation->batch_number);
                } else {
                    $balanceQuery->whereNull('batch_number');
                }

                $balance = $balanceQuery->lockForUpdate()->first();
                $onHand = $balance ? BigDecimal::of($balance->quantity_on_hand) : BigDecimal::zero();
                $allocatedAmount = BigDecimal::of($allocation->allocated_amount);

                if ($balance && $onHand->isPositive()) {
                    // Goods on hand: capitalize landed cost into inventory asset and update WAC
                    $costPerUnit = $allocatedAmount->dividedBy($onHand, 4, RoundingMode::HalfUp);
                    $currentWac = BigDecimal::of($balance->weighted_avg_cost ?? '0.0000');
                    $newWac = $currentWac->plus($costPerUnit);

                    $balance->weighted_avg_cost = (string) $newWac;
                    $balance->save();

                    $allocation->adjustment_to_inventory = (string) $allocatedAmount;
                    $allocation->adjustment_to_cogs = '0.0000';
                } else {
                    // Late arrival / goods already sold out: expense directly to COGS
                    $allocation->adjustment_to_inventory = '0.0000';
                    $allocation->adjustment_to_cogs = (string) $allocatedAmount;
                    $totalCogsAdjustment = $totalCogsAdjustment->plus($allocatedAmount);
                }

                $allocation->is_posted = true;
                $allocation->save();
            }

            // If any portion was expensed to COGS and accounting accounts were provided, post direct GL journal
            if ($totalCogsAdjustment->isPositive() && $periodId && $cogsAccountId && $apAccrualAccountId) {
                if (! $this->accountingService) {
                    $this->accountingService = app(AccountingPostingServiceContract::class);
                }

                $cogsMoney = Money::of((string) $totalCogsAdjustment->toScale(2, RoundingMode::HalfUp));

                $this->accountingService->post(new JournalEntryDTO(
                    companyId: $landedCost->company_id,
                    periodId: $periodId,
                    documentType: 'LANDED_COST_ADJUSTMENT',
                    postingDate: now(),
                    businessDate: now(),
                    referenceType: 'landed_cost',
                    referenceId: $landedCost->id,
                    postedBy: $postedBy ?? TenantContext::id() ?? throw new RuntimeException('No poster ID provided'),
                    narration: 'Late-arrival landed cost adjustment expensed directly to COGS',
                    lines: [
                        new JournalLineDTO(
                            accountId: $cogsAccountId,
                            debitAmount: $cogsMoney,
                            creditAmount: Money::zero(),
                            lineDescription: 'Late landed cost expensed to COGS'
                        ),
                        new JournalLineDTO(
                            accountId: $apAccrualAccountId,
                            debitAmount: Money::zero(),
                            creditAmount: $cogsMoney,
                            lineDescription: 'Landed cost liability accrual'
                        ),
                    ]
                ));
            }

            $landedCost->status = 'posted';
            $landedCost->posted_at = now();
            $landedCost->save();

            return $landedCost->load('allocations');
        });
    }
}
