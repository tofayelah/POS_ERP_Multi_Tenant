<?php

namespace App\Modules\Accounting\Subledger\Contracts;

use App\Modules\Accounting\Subledger\DTOs\AgingReportDTO;
use Carbon\CarbonInterface;

interface SubledgerAgingContract
{
    /**
     * Compute Accounts Receivable (AR) customer aging register into buckets.
     */
    public function getReceivablesAging(string $companyId, CarbonInterface|string|null $asOfDate = null): AgingReportDTO;

    /**
     * Compute Accounts Payable (AP) supplier aging register into buckets.
     */
    public function getPayablesAging(string $companyId, CarbonInterface|string|null $asOfDate = null): AgingReportDTO;
}
