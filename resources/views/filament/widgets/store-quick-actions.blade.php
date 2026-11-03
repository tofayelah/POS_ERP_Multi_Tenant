<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5 text-sky-500" />
                <span>Store Quick Launchpad & Daily Cashier Tasks</span>
            </div>
        </x-slot>
        <x-slot name="description">
            Quickly jump into cashier POS sales, statutory tax reports, real-time stock balances, and financial ledgers.
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
            <!-- Launch POS Cashier -->
            <a href="{{ url('/pos/app') }}" target="_blank"
               class="group relative flex flex-col justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-sky-500 dark:hover:border-sky-500 hover:shadow-lg transition duration-200">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="p-2.5 rounded-lg bg-sky-500/10 text-sky-500 group-hover:bg-sky-500 group-hover:text-white transition">
                            <x-filament::icon icon="heroicon-o-computer-desktop" class="h-6 w-6" />
                        </span>
                        <x-filament::badge color="info">Offline PWA</x-filament::badge>
                    </div>
                    <h3 class="font-semibold text-base text-gray-900 dark:text-white group-hover:text-sky-500 transition">
                        POS Cashier Terminal
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        High-speed barcode checkout, printer bridge & offline sale buffering.
                    </p>
                </div>
                <div class="mt-4 flex items-center text-xs font-medium text-sky-600 dark:text-sky-400 group-hover:translate-x-0.5 transition">
                    <span>Open POS Terminal</span>
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5 ml-1" />
                </div>
            </a>

            <!-- Mushak 6.3 Tax Invoices -->
            <a href="{{ url()->current() . '/mushak-reports' }}"
               class="group relative flex flex-col justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-amber-500 dark:hover:border-amber-500 hover:shadow-lg transition duration-200">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="p-2.5 rounded-lg bg-amber-500/10 text-amber-500 group-hover:bg-amber-500 group-hover:text-white transition">
                            <x-filament::icon icon="heroicon-o-document-chart-bar" class="h-6 w-6" />
                        </span>
                        <x-filament::badge color="warning">NBR VAT</x-filament::badge>
                    </div>
                    <h3 class="font-semibold text-base text-gray-900 dark:text-white group-hover:text-amber-500 transition">
                        Mushak 6.3 & 6.2 Registers
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Generate official NBR tax invoices, credit notes, and sales registers.
                    </p>
                </div>
                <div class="mt-4 flex items-center text-xs font-medium text-amber-600 dark:text-amber-400 group-hover:translate-x-0.5 transition">
                    <span>View VAT Reports</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 ml-1" />
                </div>
            </a>

            <!-- Stock Balances & Locations -->
            <a href="{{ url()->current() . '/stock-balances' }}"
               class="group relative flex flex-col justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-emerald-500 dark:hover:border-emerald-500 hover:shadow-lg transition duration-200">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="p-2.5 rounded-lg bg-emerald-500/10 text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition">
                            <x-filament::icon icon="heroicon-o-archive-box" class="h-6 w-6" />
                        </span>
                        <x-filament::badge color="success">Inventory</x-filament::badge>
                    </div>
                    <h3 class="font-semibold text-base text-gray-900 dark:text-white group-hover:text-emerald-500 transition">
                        Stock Balances & Move
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Inspect multi-warehouse on-hand balances, batch numbers & transfers.
                    </p>
                </div>
                <div class="mt-4 flex items-center text-xs font-medium text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition">
                    <span>Manage Inventory</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 ml-1" />
                </div>
            </a>

            <!-- Trial Balance & Financials -->
            <a href="{{ url()->current() . '/trial-balance' }}"
               class="group relative flex flex-col justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-purple-500 dark:hover:border-purple-500 hover:shadow-lg transition duration-200">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="p-2.5 rounded-lg bg-purple-500/10 text-purple-500 group-hover:bg-purple-500 group-hover:text-white transition">
                            <x-filament::icon icon="heroicon-o-scale" class="h-6 w-6" />
                        </span>
                        <x-filament::badge color="primary">Accounting</x-filament::badge>
                    </div>
                    <h3 class="font-semibold text-base text-gray-900 dark:text-white group-hover:text-purple-500 transition">
                        General Ledger & Trial Balance
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Double-entry ledger balances, posted journals, and aging receivables.
                    </p>
                </div>
                <div class="mt-4 flex items-center text-xs font-medium text-purple-600 dark:text-purple-400 group-hover:translate-x-0.5 transition">
                    <span>View Financials</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 ml-1" />
                </div>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
