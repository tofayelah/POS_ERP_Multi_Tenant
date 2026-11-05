<?php

namespace App\Modules\Inventory\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\LandedCostVoucher;
use App\Models\LandedCostVoucherAllocation;
use App\Models\LandedCostVoucherLine;
use App\Models\LandedCostVoucherReceipt;
use App\Models\StockBalance;
use App\Modules\Accounting\Contracts\AccountingPostingServiceContract;
use App\Modules\Accounting\Contracts\NumberSeriesContract;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\DTOs\JournalLineDTO;
use App\Modules\Inventory\Contracts\LandedCostAllocationContract;
use App\Modules\Inventory\DTOs\CreateLandedCostVoucherDTO;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LandedCostAllocationService implements LandedCostAllocationContract
{
    public function __construct(
        protected ?AccountingPostingServiceContract $accountingService = null,
        protected ?NumberSeriesContract $numberSeries = null
    ) {}

    public function createVoucher(CreateLandedCostVoucherDTO $dto): LandedCostVoucher
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new RuntimeException('Tenant context must be active to create a landed cost voucher.');
        }

        return DB::transaction(function () use ($dto, $tenantId) {
            $voucherNumber = $dto->voucherNumber;
            if (! $voucherNumber) {
                if ($this->numberSeries) {
                    try {
                        $fiscalYear = date('Y', strtotime($dto->voucherDate));
                        $voucherNumber = $this->numberSeries->getNextNumber($dto->companyId, $dto->branchId, 'LCV', $fiscalYear);
                    } catch (\Throwable) {
                        $voucherNumber = 'LCV-'.date('Ym').'-'.strtoupper(Str::random(6));
                    }
                } else {
                    $voucherNumber = 'LCV-'.date('Ym').'-'.strtoupper(Str::random(6));
                }
            }

            // Sum up total voucher cost
            $totalCost = BigDecimal::zero();
            foreach ($dto->lines as $line) {
                $totalCost = $totalCost->plus(BigDecimal::of($line->amount));
            }

            /** @var LandedCostVoucher $voucher */
            $voucher = LandedCostVoucher::create([
                'tenant_id' => $tenantId,
                'company_id' => $dto->companyId,
                'branch_id' => $dto->branchId,
                'voucher_number' => $voucherNumber,
                'voucher_date' => $dto->voucherDate,
                'currency' => $dto->currency,
                'exchange_rate' => $dto->exchangeRate,
                'total_cost' => (string) $totalCost->toScale(4, RoundingMode::HalfUp),
                'allocation_method' => $dto->allocationMethod,
                'status' => 'draft',
                'created_by' => $dto->createdBy,
                'notes' => $dto->notes,
            ]);

            // Attach receipts
            foreach ($dto->goodsReceiptIds as $receiptId) {
                LandedCostVoucherReceipt::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'landed_cost_voucher_id' => $voucher->id,
                    'goods_receipt_id' => $receiptId,
                ]);
            }

            // Create expense lines
            foreach ($dto->lines as $line) {
                LandedCostVoucherLine::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $dto->companyId,
                    'landed_cost_voucher_id' => $voucher->id,
                    'expense_type' => $line->expenseType,
                    'description' => $line->description,
                    'amount' => (string) BigDecimal::of($line->amount)->toScale(4, RoundingMode::HalfUp),
                    'tax_amount' => (string) BigDecimal::of($line->taxAmount)->toScale(4, RoundingMode::HalfUp),
                ]);
            }

            // Retrieve all Goods Receipt Lines across linked receipts
            $grnLines = GoodsReceiptLine::whereIn('goods_receipt_id', $dto->goodsReceiptIds)
                ->with('goodsReceipt')
                ->get();

            if ($grnLines->isEmpty()) {
                throw new RuntimeException('No goods receipt lines found for landed cost allocation.');
            }

            if ($totalCost->isPositive()) {
                // Calculate base for each line
                $lineBases = [];
                $totalBase = BigDecimal::zero();

                foreach ($grnLines as $grnLine) {
                    $qty = BigDecimal::of($grnLine->received_quantity);
                    $unitCost = BigDecimal::of($grnLine->unit_cost);

                    $base = match ($dto->allocationMethod) {
                        'by_quantity', 'by_weight' => $qty,
                        default => $qty->multipliedBy($unitCost),
                    };

                    $lineBases[$grnLine->id] = $base;
                    $totalBase = $totalBase->plus($base);
                }

                if ($totalBase->isZero()) {
                    throw new RuntimeException('Cannot allocate landed costs: total allocation base is zero.');
                }

                // Compute pro-rata allocation per line
                $allocatedSum = BigDecimal::zero();
                $allocationsToCreate = [];
                $maxBaseLineId = null;
                $maxBaseVal = BigDecimal::zero();

                foreach ($grnLines as $grnLine) {
                    $base = $lineBases[$grnLine->id];
                    if ($base->isGreaterThan($maxBaseVal)) {
                        $maxBaseVal = $base;
                        $maxBaseLineId = $grnLine->id;
                    }

                    $share = $totalCost->multipliedBy($base)->dividedBy($totalBase, 4, RoundingMode::HalfUp);
                    $allocatedSum = $allocatedSum->plus($share);

                    $qty = BigDecimal::of($grnLine->received_quantity);
                    $unitAdd = $qty->isPositive()
                        ? $share->dividedBy($qty, 4, RoundingMode::HalfUp)
                        : BigDecimal::zero();

                    $allocationsToCreate[$grnLine->id] = [
                        'grn_line' => $grnLine,
                        'allocated_amount' => $share,
                        'additional_unit_cost' => $unitAdd,
                    ];
                }

                // Adjust any rounding discrepancy on the line with the largest base
                $discrepancy = $totalCost->minus($allocatedSum);
                if (! $discrepancy->isZero() && $maxBaseLineId !== null) {
                    $adjShare = $allocationsToCreate[$maxBaseLineId]['allocated_amount']->plus($discrepancy);
                    $qty = BigDecimal::of($allocationsToCreate[$maxBaseLineId]['grn_line']->received_quantity);
                    $adjUnit = $qty->isPositive()
                        ? $adjShare->dividedBy($qty, 4, RoundingMode::HalfUp)
                        : BigDecimal::zero();

                    $allocationsToCreate[$maxBaseLineId]['allocated_amount'] = $adjShare;
                    $allocationsToCreate[$maxBaseLineId]['additional_unit_cost'] = $adjUnit;
                }

                // Persist allocations
                foreach ($allocationsToCreate as $allocData) {
                    /** @var GoodsReceiptLine $grnLine */
                    $grnLine = $allocData['grn_line'];
                    LandedCostVoucherAllocation::create([
                        'tenant_id' => $tenantId,
                        'company_id' => $dto->companyId,
                        'landed_cost_voucher_id' => $voucher->id,
                        'goods_receipt_id' => $grnLine->goods_receipt_id,
                        'goods_receipt_line_id' => $grnLine->id,
                        'product_id' => $grnLine->product_id,
                        'product_variant_id' => $grnLine->variant_id,
                        'warehouse_id' => $grnLine->goodsReceipt->warehouse_id,
                        'batch_number' => $grnLine->batch_number,
                        'allocated_amount' => (string) $allocData['allocated_amount']->toScale(4, RoundingMode::HalfUp),
                        'additional_unit_cost' => (string) $allocData['additional_unit_cost']->toScale(4, RoundingMode::HalfUp),
                        'adjustment_to_inventory' => '0.0000',
                        'adjustment_to_cogs' => '0.0000',
                        'is_posted' => false,
                    ]);
                }
            }

            return $voucher->load(['lines', 'receipts', 'allocations']);
        });
    }

    public function postVoucher(
        string $voucherId,
        string $postedBy,
        ?string $periodId = null,
        ?string $inventoryValuationAccountId = null,
        ?string $landedCostClearingAccountId = null,
        ?string $cogsAccountId = null
    ): LandedCostVoucher {
        return DB::transaction(function () use (
            $voucherId,
            $postedBy,
            $periodId,
            $inventoryValuationAccountId,
            $landedCostClearingAccountId,
            $cogsAccountId
        ) {
            /** @var LandedCostVoucher $voucher */
            $voucher = LandedCostVoucher::where('id', $voucherId)->lockForUpdate()->firstOrFail();

            if ($voucher->status === 'posted') {
                return $voucher->load(['allocations', 'lines', 'receipts']);
            }

            if ($voucher->status !== 'draft') {
                throw new RuntimeException("Cannot post landed cost voucher with status {$voucher->status}.");
            }

            $totalInventoryCapitalized = BigDecimal::zero();
            $totalCogsExpensed = BigDecimal::zero();

            foreach ($voucher->allocations as $allocation) {
                // Find stock balance for warehouse and variant
                $balanceQuery = StockBalance::where('warehouse_id', $allocation->warehouse_id)
                    ->where('product_variant_id', $allocation->product_variant_id);

                if ($allocation->batch_number !== null) {
                    $balanceQuery->where('batch_number', $allocation->batch_number);
                } else {
                    $balanceQuery->whereNull('batch_number');
                }

                $balance = $balanceQuery->lockForUpdate()->first();
                $onHand = $balance ? BigDecimal::of($balance->quantity_on_hand) : BigDecimal::zero();
                $allocAmount = BigDecimal::of($allocation->allocated_amount);

                /** @var GoodsReceiptLine|null $grnLine */
                $grnLine = GoodsReceiptLine::find($allocation->goods_receipt_line_id);
                $receivedQty = $grnLine ? BigDecimal::of($grnLine->received_quantity) : $onHand;

                if ($onHand->isPositive() && $receivedQty->isPositive()) {
                    if ($onHand->isGreaterThanOrEqualTo($receivedQty)) {
                        // 100% of received units still in stock: full capitalization into inventory WAC
                        $adjInventory = $allocAmount;
                        $adjCogs = BigDecimal::zero();

                        $unitAdd = $allocAmount->dividedBy($onHand, 4, RoundingMode::HalfUp);
                        $currentWac = BigDecimal::of($balance->weighted_avg_cost ?? '0.0000');
                        $balance->weighted_avg_cost = (string) $currentWac->plus($unitAdd)->toScale(4, RoundingMode::HalfUp);
                        $balance->save();
                    } else {
                        // Partially sold out: pro-rata split between inventory asset and COGS
                        $ratio = $onHand->dividedBy($receivedQty, 6, RoundingMode::HalfUp);
                        $adjInventory = $allocAmount->multipliedBy($ratio)->toScale(4, RoundingMode::HalfUp);
                        $adjCogs = $allocAmount->minus($adjInventory);

                        $unitAdd = $adjInventory->dividedBy($onHand, 4, RoundingMode::HalfUp);
                        $currentWac = BigDecimal::of($balance->weighted_avg_cost ?? '0.0000');
                        $balance->weighted_avg_cost = (string) $currentWac->plus($unitAdd)->toScale(4, RoundingMode::HalfUp);
                        $balance->save();
                    }
                } else {
                    // 100% already sold out (late arrival): expense directly to COGS
                    $adjInventory = BigDecimal::zero();
                    $adjCogs = $allocAmount;
                }

                $allocation->adjustment_to_inventory = (string) $adjInventory->toScale(4, RoundingMode::HalfUp);
                $allocation->adjustment_to_cogs = (string) $adjCogs->toScale(4, RoundingMode::HalfUp);
                $allocation->is_posted = true;
                $allocation->save();

                $totalInventoryCapitalized = $totalInventoryCapitalized->plus($adjInventory);
                $totalCogsExpensed = $totalCogsExpensed->plus($adjCogs);
            }

            // Post GL Journal entry if accounts are present
            $invAccountId = $inventoryValuationAccountId ?: ChartOfAccount::where('company_id', $voucher->company_id)
                ->whereIn('account_code', ['1040', '1050'])
                ->value('id');

            $clrAccountId = $landedCostClearingAccountId ?: ChartOfAccount::where('company_id', $voucher->company_id)
                ->whereIn('account_code', ['2050', '2010'])
                ->value('id');

            $cogsAccId = $cogsAccountId ?: ChartOfAccount::where('company_id', $voucher->company_id)
                ->where('account_code', '5010')
                ->value('id');

            $activePeriodId = $periodId ?: AccountingPeriod::where('company_id', $voucher->company_id)
                ->where('status', 'open')
                ->value('id');

            if ($invAccountId && $clrAccountId && $activePeriodId) {
                if (! $this->accountingService) {
                    $this->accountingService = app(AccountingPostingServiceContract::class);
                }

                $journalLines = [];
                $totalCredit = BigDecimal::zero();

                if ($totalInventoryCapitalized->isPositive()) {
                    $invMoney = Money::of((string) $totalInventoryCapitalized->toScale(2, RoundingMode::HalfUp));
                    $journalLines[] = new JournalLineDTO(
                        accountId: $invAccountId,
                        debitAmount: $invMoney,
                        creditAmount: Money::zero(),
                        lineDescription: "Landed cost capitalized into inventory: {$voucher->voucher_number}"
                    );
                    $totalCredit = $totalCredit->plus($totalInventoryCapitalized);
                }

                if ($totalCogsExpensed->isPositive() && $cogsAccId) {
                    $cogsMoney = Money::of((string) $totalCogsExpensed->toScale(2, RoundingMode::HalfUp));
                    $journalLines[] = new JournalLineDTO(
                        accountId: $cogsAccId,
                        debitAmount: $cogsMoney,
                        creditAmount: Money::zero(),
                        lineDescription: "Late landed cost expensed to COGS: {$voucher->voucher_number}"
                    );
                    $totalCredit = $totalCredit->plus($totalCogsExpensed);
                }

                if (! empty($journalLines)) {
                    $clrMoney = Money::of((string) $totalCredit->toScale(2, RoundingMode::HalfUp));
                    $journalLines[] = new JournalLineDTO(
                        accountId: $clrAccountId,
                        debitAmount: Money::zero(),
                        creditAmount: $clrMoney,
                        lineDescription: "Landed cost clearing liability: {$voucher->voucher_number}"
                    );

                    $journal = $this->accountingService->post(new JournalEntryDTO(
                        companyId: $voucher->company_id,
                        periodId: $activePeriodId,
                        documentType: 'LANDED_COST_VOUCHER',
                        postingDate: Carbon::parse($voucher->voucher_date),
                        businessDate: Carbon::parse($voucher->voucher_date),
                        referenceType: 'landed_cost_voucher',
                        referenceId: $voucher->id,
                        postedBy: $postedBy,
                        branchId: $voucher->branch_id,
                        narration: "Landed Cost Allocation: {$voucher->voucher_number}",
                        lines: $journalLines
                    ));

                    $voucher->gl_journal_entry_id = $journal->id;
                }
            }

            $voucher->status = 'posted';
            $voucher->posted_by = $postedBy;
            $voucher->posted_at = now();
            $voucher->save();

            return $voucher->load(['allocations', 'lines', 'receipts']);
        });
    }

    public function cancelVoucher(string $voucherId, string $cancelledBy): LandedCostVoucher
    {
        return DB::transaction(function () use ($voucherId, $cancelledBy) {
            /** @var LandedCostVoucher $voucher */
            $voucher = LandedCostVoucher::where('id', $voucherId)->lockForUpdate()->firstOrFail();

            if ($voucher->status === 'posted') {
                throw new RuntimeException('Posted landed cost vouchers are immutable and cannot be cancelled.');
            }

            $voucher->status = 'cancelled';
            $voucher->notes = ($voucher->notes ? $voucher->notes."\n" : '')."Cancelled by user {$cancelledBy} at ".now()->toIso8601String();
            $voucher->save();

            return $voucher;
        });
    }
}
