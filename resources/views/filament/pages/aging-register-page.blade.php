<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Receivables (AR) Aging Section --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Customer Receivables (AR) Aging</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Open subledger items categorized by due date overdue intervals</p>
                </div>
                @if($arAging)
                    <div class="rounded-lg bg-primary-50 px-4 py-2 font-mono font-semibold text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                        Total AR Due: ৳ {{ $arAging->summary->total->getAmount() }}
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Customer Name</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">0 - 30 Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">31 - 60 Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">61 - 90 Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">91 - 120 Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">120+ Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total Outstanding</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @if($arAging && count($arAging->details) > 0)
                            @foreach($arAging->details as $row)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/50">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $row->partyName }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->days0To30->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->days31To60->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->days61To90->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->days91To120->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->daysOver120->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono font-bold text-gray-900 dark:text-white">৳ {{ $row->buckets->total->getAmount() }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No outstanding customer receivables open items.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Payables (AP) Aging Section --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Supplier Payables (AP) Aging</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Open subledger items categorized by invoice due date overdue intervals</p>
                </div>
                @if($apAging)
                    <div class="rounded-lg bg-amber-50 px-4 py-2 font-mono font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                        Total AP Due: ৳ {{ $apAging->summary->total->getAmount() }}
                    </div>
                @endif
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Supplier Name</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">0 - 30 Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">31 - 60 Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">61 - 90 Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">91 - 120 Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">120+ Days</th>
                            <th scope="col" class="px-6 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">Total Outstanding</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @if($apAging && count($apAging->details) > 0)
                            @foreach($apAging->details as $row)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/50">
                                    <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $row->partyName }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->days0To30->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->days31To60->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->days61To90->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->days91To120->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono text-gray-700 dark:text-gray-300">৳ {{ $row->buckets->daysOver120->getAmount() }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right font-mono font-bold text-gray-900 dark:text-white">৳ {{ $row->buckets->total->getAmount() }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No outstanding supplier payables open items.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
