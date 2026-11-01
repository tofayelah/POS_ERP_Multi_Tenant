<?php

namespace App\Modules\Accounting\Subledger\Contracts;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Modules\Accounting\Subledger\DTOs\AllocationItemDTO;
use App\Modules\Accounting\Subledger\DTOs\RecordPaymentDTO;

interface PaymentServiceContract
{
    /**
     * Record a payment (inbound customer receipt or outbound supplier disbursement)
     * and allocate it across open items in the same database transaction.
     */
    public function recordPayment(RecordPaymentDTO $dto): Payment;

    /**
     * Allocate an existing unallocated payment across open subledger items.
     *
     * @param  array<int, AllocationItemDTO>  $allocations
     * @return array<int, PaymentAllocation>
     */
    public function allocatePayment(string $paymentId, array $allocations): array;
}
