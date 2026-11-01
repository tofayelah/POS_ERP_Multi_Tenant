<?php

namespace App\Modules\Accounting\Subledger\Services;

use App\Core\Tenancy\TenantContext;
use App\Core\ValueObjects\Money;
use App\Models\Customer;
use App\Models\SubledgerOpenItem;
use App\Models\Supplier;
use App\Modules\Accounting\Subledger\Contracts\SubledgerAgingContract;
use App\Modules\Accounting\Subledger\DTOs\AgingBucketDTO;
use App\Modules\Accounting\Subledger\DTOs\AgingReportDTO;
use App\Modules\Accounting\Subledger\DTOs\PartyAgingItemDTO;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class SubledgerAgingService implements SubledgerAgingContract
{
    /**
     * Compute Accounts Receivable (AR) customer aging register into buckets.
     */
    public function getReceivablesAging(string $companyId, CarbonInterface|string|null $asOfDate = null): AgingReportDTO
    {
        return $this->computeAging($companyId, 'customer', $asOfDate);
    }

    /**
     * Compute Accounts Payable (AP) supplier aging register into buckets.
     */
    public function getPayablesAging(string $companyId, CarbonInterface|string|null $asOfDate = null): AgingReportDTO
    {
        return $this->computeAging($companyId, 'supplier', $asOfDate);
    }

    protected function computeAging(string $companyId, string $partyType, CarbonInterface|string|null $asOfDate): AgingReportDTO
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot compute aging without active TenantContext.');
        }

        $cutoff = $asOfDate instanceof CarbonInterface
            ? Carbon::parse($asOfDate->toDateString())
            : ($asOfDate ? Carbon::parse($asOfDate) : Carbon::today());

        $openItems = SubledgerOpenItem::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('party_type', $partyType)
            ->whereIn('status', ['open', 'partially_allocated'])
            ->get();

        // Load party names
        $partyNames = [];
        if ($partyType === 'customer') {
            $partyNames = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->pluck('name', 'id')
                ->toArray();
        } else {
            $partyNames = Supplier::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->pluck('name', 'id')
                ->toArray();
        }

        /** @var array<string, array{0_30: Money, 31_60: Money, 61_90: Money, 91_120: Money, over_120: Money, total: Money}> $partyBuckets */
        $partyBuckets = [];

        $summary0To30 = Money::zero('BDT');
        $summary31To60 = Money::zero('BDT');
        $summary61To90 = Money::zero('BDT');
        $summary91To120 = Money::zero('BDT');
        $summaryOver120 = Money::zero('BDT');
        $summaryTotal = Money::zero('BDT');

        foreach ($openItems as $item) {
            $rem = Money::of((string) $item->remaining_amount);
            if ($rem->isZero()) {
                continue;
            }

            // Normal direction check: debit for customer AR, credit for supplier AP
            $isNormal = ($partyType === 'customer' && $item->direction === 'debit')
                || ($partyType === 'supplier' && $item->direction === 'credit');

            $amount = $isNormal ? $rem : Money::zero('BDT')->subtract($rem);

            $dueDate = Carbon::parse($item->due_date);
            // Overdue days = cutoff - dueDate
            $daysOverdue = $cutoff->diffInDays($dueDate, false) * -1;

            $partyId = $item->party_id;
            if (! isset($partyBuckets[$partyId])) {
                $partyBuckets[$partyId] = [
                    '0_30' => Money::zero('BDT'),
                    '31_60' => Money::zero('BDT'),
                    '61_90' => Money::zero('BDT'),
                    '91_120' => Money::zero('BDT'),
                    'over_120' => Money::zero('BDT'),
                    'total' => Money::zero('BDT'),
                ];
            }

            if ($daysOverdue <= 30) {
                $partyBuckets[$partyId]['0_30'] = $partyBuckets[$partyId]['0_30']->add($amount);
                $summary0To30 = $summary0To30->add($amount);
            } elseif ($daysOverdue <= 60) {
                $partyBuckets[$partyId]['31_60'] = $partyBuckets[$partyId]['31_60']->add($amount);
                $summary31To60 = $summary31To60->add($amount);
            } elseif ($daysOverdue <= 90) {
                $partyBuckets[$partyId]['61_90'] = $partyBuckets[$partyId]['61_90']->add($amount);
                $summary61To90 = $summary61To90->add($amount);
            } elseif ($daysOverdue <= 120) {
                $partyBuckets[$partyId]['91_120'] = $partyBuckets[$partyId]['91_120']->add($amount);
                $summary91To120 = $summary91To120->add($amount);
            } else {
                $partyBuckets[$partyId]['over_120'] = $partyBuckets[$partyId]['over_120']->add($amount);
                $summaryOver120 = $summaryOver120->add($amount);
            }

            $partyBuckets[$partyId]['total'] = $partyBuckets[$partyId]['total']->add($amount);
            $summaryTotal = $summaryTotal->add($amount);
        }

        $details = [];
        foreach ($partyBuckets as $partyId => $b) {
            $details[] = new PartyAgingItemDTO(
                partyId: $partyId,
                partyName: $partyNames[$partyId] ?? "Unknown Party {$partyId}",
                buckets: new AgingBucketDTO(
                    days0To30: $b['0_30'],
                    days31To60: $b['31_60'],
                    days61To90: $b['61_90'],
                    days91To120: $b['91_120'],
                    daysOver120: $b['over_120'],
                    total: $b['total']
                )
            );
        }

        $summary = new AgingBucketDTO(
            days0To30: $summary0To30,
            days31To60: $summary31To60,
            days61To90: $summary61To90,
            days91To120: $summary91To120,
            daysOver120: $summaryOver120,
            total: $summaryTotal
        );

        return new AgingReportDTO(
            companyId: $companyId,
            partyType: $partyType,
            asOfDate: $cutoff->toDateString(),
            summary: $summary,
            details: $details
        );
    }
}
