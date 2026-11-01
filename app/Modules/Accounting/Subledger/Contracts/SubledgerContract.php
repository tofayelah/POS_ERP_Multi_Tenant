<?php

namespace App\Modules\Accounting\Subledger\Contracts;

use App\Models\SubledgerOpenItem;

interface SubledgerContract
{
    /**
     * Create an AR receivable open item from an issued sales invoice.
     */
    public function createReceivable(
        string $tenantId,
        string $companyId,
        string $customerId,
        string $invoiceId,
        string $invoiceNumber,
        string $documentDate,
        string $dueDate,
        string $amount,
        string $controlAccountCode = '1030',
    ): SubledgerOpenItem;

    /**
     * Create an AP payable open item from an approved vendor bill.
     */
    public function createPayable(
        string $tenantId,
        string $companyId,
        string $supplierId,
        string $billId,
        string $billNumber,
        string $documentDate,
        string $dueDate,
        string $amount,
        string $controlAccountCode = '2010',
    ): SubledgerOpenItem;
}
