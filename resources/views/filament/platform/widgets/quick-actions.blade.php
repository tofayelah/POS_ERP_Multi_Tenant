<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5 text-amber-500" />
                <span>Platform Quick Actions & Store Launchpad</span>
            </div>
        </x-slot>
        <x-slot name="description">
            Directly access tenant store ERPs, cashier POS terminals, and multi-tenant provisioning controls.
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
            <!-- Acme Pilot ERP -->
            <a href="{{ url('/admin/acme') }}" target="_blank"
               class="group relative flex flex-col justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-amber-500 dark:hover:border-amber-500 hover:shadow-lg transition duration-200">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="p-2.5 rounded-lg bg-amber-500/10 text-amber-500 group-hover:bg-amber-500 group-hover:text-white transition">
                            <x-filament::icon icon="heroicon-o-building-storefront" class="h-6 w-6" />
                        </span>
                        <x-filament::badge color="success">Pilot Store</x-filament::badge>
                    </div>
                    <h3 class="font-semibold text-base text-gray-900 dark:text-white group-hover:text-amber-500 transition">
                        Acme Retail BD
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Flagship 3-branch pilot (Dhanmondi, Gulshan, Uttara) with live GL & VAT.
                    </p>
                </div>
                <div class="mt-4 flex items-center text-xs font-medium text-amber-600 dark:text-amber-400 group-hover:translate-x-0.5 transition">
                    <span>Open Store ERP</span>
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5 ml-1" />
                </div>
            </a>

            <!-- Offline POS Cashier -->
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
                        POS Terminal Register
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        IndexedDB offline cashier register with hardware bridge and barcode scanner.
                    </p>
                </div>
                <div class="mt-4 flex items-center text-xs font-medium text-sky-600 dark:text-sky-400 group-hover:translate-x-0.5 transition">
                    <span>Launch POS Terminal</span>
                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5 ml-1" />
                </div>
            </a>

            <!-- Manage Tenants -->
            <a href="{{ url('/platform/tenants') }}"
               class="group relative flex flex-col justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-emerald-500 dark:hover:border-emerald-500 hover:shadow-lg transition duration-200">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="p-2.5 rounded-lg bg-emerald-500/10 text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition">
                            <x-filament::icon icon="heroicon-o-building-office-2" class="h-6 w-6" />
                        </span>
                        <x-filament::badge color="success">Multi-Tenant</x-filament::badge>
                    </div>
                    <h3 class="font-semibold text-base text-gray-900 dark:text-white group-hover:text-emerald-500 transition">
                        Manage Retail Tenants
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Onboard new retail chains, configure subdomains, and inspect RLS quotas.
                    </p>
                </div>
                <div class="mt-4 flex items-center text-xs font-medium text-emerald-600 dark:text-emerald-400 group-hover:translate-x-0.5 transition">
                    <span>View All Tenants</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 ml-1" />
                </div>
            </a>

            <!-- Pricing & Plans -->
            <a href="{{ url('/platform/plans') }}"
               class="group relative flex flex-col justify-between p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 hover:border-purple-500 dark:hover:border-purple-500 hover:shadow-lg transition duration-200">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="p-2.5 rounded-lg bg-purple-500/10 text-purple-500 group-hover:bg-purple-500 group-hover:text-white transition">
                            <x-filament::icon icon="heroicon-o-credit-card" class="h-6 w-6" />
                        </span>
                        <x-filament::badge color="primary">Billing</x-filament::badge>
                    </div>
                    <h3 class="font-semibold text-base text-gray-900 dark:text-white group-hover:text-purple-500 transition">
                        SaaS Plans & Tiers
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Starter (৳2,500), Standard (৳5,000), and Enterprise (৳15,000) tiers.
                    </p>
                </div>
                <div class="mt-4 flex items-center text-xs font-medium text-purple-600 dark:text-purple-400 group-hover:translate-x-0.5 transition">
                    <span>Configure Plans</span>
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 ml-1" />
                </div>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
