<?php

namespace App\Modules\Accounting\Contracts;

use App\Models\JournalEntry;
use App\Modules\Accounting\DTOs\JournalEntryDTO;
use App\Modules\Accounting\Exceptions\PostingWithoutTransactionException;
use App\Modules\Accounting\Exceptions\UnbalancedJournalException;

interface AccountingPostingServiceContract
{
    /**
     * Post a balanced journal entry synchronously within caller's active database transaction.
     *
     * @param JournalEntryDTO $entryDto
     * @return JournalEntry
     *
     * @throws PostingWithoutTransactionException if DB::transactionLevel() === 0
     * @throws UnbalancedJournalException if debits != credits or line count < 2
     */
    public function post(JournalEntryDTO $entryDto): JournalEntry;
}
