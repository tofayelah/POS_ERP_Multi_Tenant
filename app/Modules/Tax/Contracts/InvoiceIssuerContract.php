<?php

namespace App\Modules\Tax\Contracts;

use App\Models\CreditNote;
use App\Models\SalesInvoice;
use App\Modules\Tax\DTOs\IssueInvoiceRequest;
use App\Modules\Tax\DTOs\ReturnInvoiceRequest;

interface InvoiceIssuerContract
{
    /**
     * Issue a statutory VAT sales invoice.
     */
    public function issue(IssueInvoiceRequest $request): SalesInvoice;

    /**
     * Create a Mushak 6.7 Credit Note for returns.
     */
    public function createReturn(ReturnInvoiceRequest $request): CreditNote;
}
