<?php

namespace App\Modules\Accounting\Subledger\Contracts;

use App\Models\Payment;
use App\Modules\Accounting\Subledger\DTOs\CreateSupplierVoucherDTO;

interface SupplierPaymentVoucherContract
{
    /**
     * Draft a supplier payment voucher (pending approval).
     */
    public function createVoucher(CreateSupplierVoucherDTO $dto): Payment;

    /**
     * Authorize and post a supplier payment voucher with Segregation of Duties (SoD §3.3) validation.
     * Enforces:
     * - Creator cannot approve (Rule SoD-01: Purchase & Pay)
     * - User who created the supplier cannot authorize disbursements (Rule SoD-02)
     */
    public function approveVoucher(string $paymentId, string $approverUserId): Payment;
}
