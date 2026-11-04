@php
    $data = $this->getReconciliationData();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-scale"
                        class="h-6 w-6 text-primary-500"
                    />
                    <span class="text-base font-semibold text-gray-950 dark:text-white">
                        Three-Way Matching Reconciliation / ত্রি-পক্ষীয় মিলকরণ নিরীক্ষা
                    </span>
                </div>
                <div>
                    @if ($data['status'] === 'MATCHED')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                            MATCH VERIFIED / মিলকরণ সঠিক
                        </span>
                    @elseif ($data['status'] === 'EXCEPTION')
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                            EXCEPTION DETECTED / অসঙ্গতি শনাক্ত
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                            UNLINKED / সংযোগবিহীন
                        </span>
                    @endif
                </div>
            </div>
        </x-slot>

        {{-- KPI Summary Stats --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Billed / চালানের মোট দাবি</span>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                    ৳ {{ number_format((float) $data['total_billed'], 2) }}
                </p>
            </div>

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Expected PO Amount / অনুমোদিত কার্যাদেশ মূল্য</span>
                <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                    ৳ {{ number_format((float) $data['total_po_expected'], 2) }}
                </p>
            </div>

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Net Price Variance (PPV) / নিট দর পার্থক্য</span>
                <p class="text-xl font-bold mt-1 {{ (float) $data['net_ppv'] > 0 ? 'text-rose-600 dark:text-rose-400' : ((float) $data['net_ppv'] < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-900 dark:text-white') }}">
                    ৳ {{ (float) $data['net_ppv'] > 0 ? '+' : '' }}{{ number_format((float) $data['net_ppv'], 2) }}
                </p>
            </div>
        </div>

        {{-- Line by Line Comparison Table --}}
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-100 dark:bg-gray-800/80 text-xs uppercase font-semibold text-gray-700 dark:text-gray-200">
                    <tr>
                        <th class="px-4 py-3">Product / SKU</th>
                        <th class="px-4 py-3 text-right">PO Qty</th>
                        <th class="px-4 py-3 text-right">GRN Qty</th>
                        <th class="px-4 py-3 text-right">Bill Qty</th>
                        <th class="px-4 py-3 text-right">PO Rate</th>
                        <th class="px-4 py-3 text-right">Bill Rate</th>
                        <th class="px-4 py-3 text-right">PPV</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($data['lines'] as $line)
                        <tr class="{{ $line['status'] === 'EXCEPTION' ? 'bg-rose-50/50 dark:bg-rose-950/20' : 'hover:bg-gray-50/50 dark:hover:bg-gray-800/50' }}">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $line['product_name'] }}
                                <span class="block text-xs text-gray-500 font-mono">{{ $line['sku'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ $line['po_qty'] }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $line['grn_qty'] }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ (float) $line['bill_qty'] > (float) $line['grn_qty'] ? 'text-rose-600' : '' }}">
                                {{ $line['bill_qty'] }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">৳ {{ number_format((float) $line['po_price'], 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-semibold {{ (float) $line['bill_price'] > (float) $line['po_price'] ? 'text-rose-600' : '' }}">
                                ৳ {{ number_format((float) $line['bill_price'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                ৳ {{ number_format((float) $line['ppv'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($line['status'] === 'MATCHED')
                                    <span class="inline-block px-2 py-0.5 text-xs font-medium rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        OK
                                    </span>
                                @else
                                    <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                        FAIL
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-6 text-center text-gray-500">
                                No invoice lines available for reconciliation.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Exceptions Warning Banner --}}
        @if ($data['has_exceptions'])
            <div class="mt-4 p-4 rounded-xl border border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200">
                <div class="flex items-start gap-3">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-triangle"
                        class="h-5 w-5 text-rose-600 shrink-0 mt-0.5"
                    />
                    <div>
                        <h4 class="font-semibold text-sm">Matching Exceptions Hard-Blocking Approval / অনুমোদন অবরুদ্ধকারী অসঙ্গতি:</h4>
                        <ul class="list-disc list-inside mt-1 text-xs space-y-1">
                            @foreach ($data['exceptions'] as $ex)
                                <li>{{ $ex }}</li>
                            @endforeach
                        </ul>
                        <p class="text-xs mt-2 text-rose-700 dark:text-rose-300 font-medium">
                            Action Blocked: This bill cannot be approved or posted to General Ledger without a formal Financial Controller / Tenant Admin override.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
