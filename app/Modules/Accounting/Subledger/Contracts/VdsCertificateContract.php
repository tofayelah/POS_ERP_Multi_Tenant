<?php

namespace App\Modules\Accounting\Subledger\Contracts;

use App\Models\VdsCertificate;
use App\Modules\Accounting\Subledger\DTOs\CustomerVdsDeductionDTO;
use App\Modules\Accounting\Subledger\DTOs\SupplierVdsWithholdingDTO;

interface VdsCertificateContract
{
    /**
     * Record a Mushak 6.6 VDS certificate received from a corporate buyer / customer.
     * Settles the invoice open item, creates an inbound payment, and posts GL journal.
     */
    public function recordCustomerVdsDeduction(CustomerVdsDeductionDTO $dto): VdsCertificate;

    /**
     * Issue a Mushak 6.6 VDS certificate to a supplier when paying a bill with VAT withheld.
     * Settles the bill open item, creates an outbound payment, and posts GL journal.
     */
    public function issueSupplierVdsCertificate(SupplierVdsWithholdingDTO $dto): VdsCertificate;
}
