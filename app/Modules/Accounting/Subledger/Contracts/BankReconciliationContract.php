<?php

namespace App\Modules\Accounting\Subledger\Contracts;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Modules\Accounting\Subledger\DTOs\BankReconciliationSummaryDTO;
use App\Modules\Accounting\Subledger\DTOs\ImportBankStatementDTO;

interface BankReconciliationContract
{
    /**
     * Import a bank/cash statement with detailed transaction lines.
     */
    public function importStatement(ImportBankStatementDTO $dto): BankStatement;

    /**
     * Automatically match unreconciled statement lines against posted payments
     * by matching reference, amount, and payment direction.
     */
    public function autoMatchStatement(string $statementId): int;

    /**
     * Manually match a statement line to a posted payment.
     */
    public function matchLine(string $statementLineId, string $paymentId): BankStatementLine;

    /**
     * Unmatch a reconciled statement line, returning the payment to posted status.
     */
    public function unmatchLine(string $statementLineId): BankStatementLine;

    /**
     * Generate bank reconciliation summary comparing statement vs GL book balance.
     */
    public function getReconciliationSummary(string $statementId): BankReconciliationSummaryDTO;
}
