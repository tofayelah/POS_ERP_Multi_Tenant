<x-filament-panels::page>
    <div class="space-y-6">
        @if($report)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Debit Amount</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">৳ {{ $report->totalDebit->getAmount() }}</p>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Credit Amount</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">৳ {{ $report->totalCredit->getAmount() }}</p>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Ledger Balance Status</p>
                    <div class="mt-2 flex items-center gap-2">
                        @if($report->isBalanced)
                            <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/30 dark:text-green-400">
                                Strictly Balanced (Debits = Credits)
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-900/30 dark:text-red-400">
                                Unbalanced Ledger
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">General Ledger Accounts</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Statutory trial balance as of {{ now()->toFormattedDateString() }}</p>
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Account Code</th>
                            <th scope="col" class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Account Name</th>
                            <th scope="col" class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Type</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Net Debit (BDT)</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Net Credit (BDT)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($report->accounts as $row)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/50">
                                <td class="whitespace-nowrap px-6 py-4 font-mono font-medium text-gray-900 dark:text-white">{{ $row->accountCode }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-gray-700 dark:text-gray-300">{{ $row->accountName }}</td>
                                <td class="whitespace-nowrap px-6 py-4 capitalize text-gray-500 dark:text-gray-400">{{ $row->accountType }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-900 dark:text-white">৳ {{ $row->netDebit->getAmount() }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-900 dark:text-white">৳ {{ $row->netCredit->getAmount() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No ledger activity posted in this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="border-t-2 border-gray-300 bg-gray-50 font-bold dark:border-gray-600 dark:bg-gray-900/50">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-gray-900 dark:text-white">Total</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-900 dark:text-white">৳ {{ $report->totalDebit->getAmount() }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-900 dark:text-white">৳ {{ $report->totalCredit->getAmount() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm text-gray-500">No active company profile found for the current tenant.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
