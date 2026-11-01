<?php

namespace App\Modules\Accounting\Subledger\Services;

use App\Models\SubledgerOpenItem;
use App\Modules\Accounting\Subledger\Contracts\SubledgerContract;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;

class SubledgerService implements SubledgerContract
{
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
    ): SubledgerOpenItem {
        $scaledAmount = (string) BigDecimal::of($amount)->toScale(4, RoundingMode::HalfUp);

        /** @var SubledgerOpenItem $item */
        $item = SubledgerOpenItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'party_type' => 'customer',
            'party_id' => $customerId,
            'source_type' => 'sales_invoice',
            'source_id' => $invoiceId,
            'document_number' => $invoiceNumber,
            'document_date' => $documentDate,
            'due_date' => $dueDate,
            'direction' => 'debit',
            'gl_control_account_code' => $controlAccountCode,
            'original_amount' => $scaledAmount,
            'allocated_amount' => '0.0000',
            'remaining_amount' => $scaledAmount,
            'status' => 'open',
        ]);

        return $item;
    }

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
    ): SubledgerOpenItem {
        $scaledAmount = (string) BigDecimal::of($amount)->toScale(4, RoundingMode::HalfUp);

        /** @var SubledgerOpenItem $item */
        $item = SubledgerOpenItem::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'party_type' => 'supplier',
            'party_id' => $supplierId,
            'source_type' => 'supplier_bill',
            'source_id' => $billId,
            'document_number' => $billNumber,
            'document_date' => $documentDate,
            'due_date' => $dueDate,
            'direction' => 'credit',
            'gl_control_account_code' => $controlAccountCode,
            'original_amount' => $scaledAmount,
            'allocated_amount' => '0.0000',
            'remaining_amount' => $scaledAmount,
            'status' => 'open',
        ]);

        return $item;
    }
}
