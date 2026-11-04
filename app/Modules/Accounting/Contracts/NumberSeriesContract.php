<?php

namespace App\Modules\Accounting\Contracts;

interface NumberSeriesContract
{
    /**
     * Get the next gapless number in the series using pessimistic locking (SELECT FOR UPDATE).
     */
    public function getNextNumber(string $companyId, ?string $branchId, string $documentType, string $fiscalYear): string;
}
