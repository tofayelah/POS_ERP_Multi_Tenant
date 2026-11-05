@php
    $data = $this->getComparisonData();
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-filament::icon
                        icon="heroicon-o-presentation-chart-bar"
                        class="h-6 w-6 text-primary-500"
                    />
                    <span class="text-base font-semibold text-gray-950 dark:text-white">
                        Multi-Vendor RFQ Comparison Matrix / দরপত্র তুলনামূলক মূল্যায়ন ছক ({{ $data['rfq_number'] }})
                    </span>
                </div>
                <div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-100 text-primary-800 dark:bg-primary-950 dark:text-primary-300">
                        {{ count($data['bids']) }} Vendor Bids Submitted
                    </span>
                </div>
            </div>
        </x-slot>

        @if ($data['has_bids'])
            {{-- Vendor Bid Summary Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-{{ max(1, min(4, count($data['bids']))) }} gap-4 mb-6">
                @foreach ($data['bids'] as $bid)
                    <div class="p-4 rounded-xl border {{ $bid['is_current'] ? 'border-primary-500 bg-primary-50/20 dark:bg-primary-950/20' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                {{ $bid['supplier_name'] }}
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $bid['status'] === 'awarded' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                {{ ucfirst($bid['status']) }}
                            </span>
                        </div>
                        <p class="text-xl font-bold text-gray-900 dark:text-white mt-2">
                            ৳ {{ number_format((float) $bid['grand_total'], 2) }}
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            Ref: {{ $bid['quote_reference'] }} | {{ $bid['quote_date'] }}
                        </p>
                    </div>
                @endforeach
            </div>

            {{-- Line Item Comparative Matrix --}}
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs font-medium uppercase bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="py-3 px-4">Item & SKU</th>
                            @foreach ($data['bids'] as $bid)
                                <th class="py-3 px-4 text-right">
                                    {{ $bid['supplier_name'] }}
                                </th>
                            @endforeach
                            <th class="py-3 px-4 text-center">Best Offer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($data['variant_comparison'] as $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <td class="py-3 px-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $item['variant_name'] }}</div>
                                    <div class="text-xs text-gray-400">SKU: {{ $item['sku'] }}</div>
                                </td>
                                @foreach ($data['bids'] as $bid)
                                    @php
                                        $quote = $item['quotes'][$bid['id']] ?? null;
                                        $isLowest = $item['lowest_supplier_id'] === $bid['id'];
                                    @endphp
                                    <td class="py-3 px-4 text-right">
                                        @if ($quote)
                                            <div class="font-semibold {{ $isLowest ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-gray-900 dark:text-white' }}">
                                                ৳ {{ number_format((float) $quote['price'], 2) }}
                                            </div>
                                            <div class="text-xs text-gray-400">
                                                {{ $quote['lead_time_days'] }} days lead
                                            </div>
                                        @else
                                            <span class="text-gray-400 italic">No quote</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-3 px-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                        ৳ {{ number_format((float) $item['lowest_price'], 2) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                <p>No vendor bids submitted for this RFQ yet.</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
