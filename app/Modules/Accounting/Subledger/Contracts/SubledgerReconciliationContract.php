<?php

namespace App\Modules\Accounting\Subledger\Contracts;

use App\Modules\Accounting\Subledger\DTOs\SubledgerReconciliationResult;

interface SubledgerReconciliationContract
{
    /**
     * Reconcile Accounts Receivable (AR) Subledger against GL Control Account 1030.
     */
    public function reconcileReceivables(string $companyId): SubledgerReconciliationResult;

    /**
     * Reconcile Accounts Payable (AP) Subledger against GL Control Account 2010.
     */
    public function reconcilePayables(string $companyId): SubledgerReconciliationResult;
}
