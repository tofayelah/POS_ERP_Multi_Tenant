<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-500/15 text-amber-500">
                    <x-filament::icon icon="heroicon-o-bolt" class="w-5 h-5" />
                </span>
                <span class="text-lg font-bold tracking-tight text-white">
                    Platform Quick Actions & Store Launchpad
                </span>
            </div>
        </x-slot>
        <x-slot name="description">
            <span class="text-sm text-slate-400">
                Direct access to active store ERP instances, edge POS registers, tenant onboarding, and platform controls.
            </span>
        </x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mt-4">
            {{-- Dynamic Active Tenant Store Cards --}}
            @forelse ($activeTenants as $tenant)
                <x-filament::section :compact="true" class="h-full border border-white/5 bg-slate-900/60">
                    <div class="flex flex-col h-full justify-between min-h-[190px]">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500/15 text-amber-400">
                                    <x-filament::icon icon="heroicon-o-building-storefront" class="w-5 h-5" />
                                </span>
                                <x-filament::badge color="success">Active ERP</x-filament::badge>
                            </div>
                            <h3 class="text-base font-bold text-white mb-1 truncate" title="{{ $tenant->name }}">
                                {{ $tenant->name }}
                            </h3>
                            <p class="text-xs leading-relaxed text-slate-400 mb-4">
                                Portal: <span class="font-mono text-sky-400 font-semibold">{{ $tenant->subdomain }}.kcerp.com</span>
                                <br>
                                Plan: <span class="text-slate-300 font-medium capitalize">{{ $tenant->subscription_plan ?? 'Standard' }}</span>
                            </p>
                        </div>
                        <div class="mt-auto pt-2">
                            <x-filament::button
                                tag="a"
                                :href="url('/admin/' . $tenant->subdomain)"
                                target="_blank"
                                icon="heroicon-m-arrow-top-right-on-square"
                                icon-position="after"
                                color="warning"
                                class="w-full justify-center font-semibold shadow-sm"
                            >
                                Open Store ERP
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @empty
                <x-filament::section :compact="true" class="h-full border border-dashed border-amber-500/30 bg-slate-900/40">
                    <div class="flex flex-col h-full justify-between min-h-[190px] text-center p-2">
                        <div class="my-auto">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500/15 text-amber-500 mb-2">
                                <x-filament::icon icon="heroicon-o-plus-circle" class="w-6 h-6" />
                            </span>
                            <h3 class="text-sm font-bold text-white mb-1">No Active Tenants</h3>
                            <p class="text-xs text-slate-400">Onboard your first retail chain to launch an ERP instance.</p>
                        </div>
                        <div class="mt-auto pt-3">
                            <x-filament::button
                                tag="a"
                                :href="url('/platform/tenants/create')"
                                icon="heroicon-m-plus"
                                color="warning"
                                class="w-full justify-center font-semibold"
                            >
                                Onboard Tenant
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endforelse

            {{-- Offline POS Cashier Terminal Card --}}
            <x-filament::section :compact="true" class="h-full border border-white/5 bg-slate-900/60">
                <div class="flex flex-col h-full justify-between min-h-[190px]">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-sky-500/15 text-sky-400">
                                <x-filament::icon icon="heroicon-o-computer-desktop" class="w-5 h-5" />
                            </span>
                            <x-filament::badge color="info">Offline PWA</x-filament::badge>
                        </div>
                        <h3 class="text-base font-bold text-white mb-1">
                            POS Terminal Shell
                        </h3>
                        <p class="text-xs leading-relaxed text-slate-400 mb-4">
                            Dexie IndexedDB offline cashier register with hardware bridge barcode & printer integration.
                        </p>
                    </div>
                    <div class="mt-auto pt-2">
                        <x-filament::button
                            tag="a"
                            :href="url('/pos')"
                            target="_blank"
                            icon="heroicon-m-arrow-top-right-on-square"
                            icon-position="after"
                            color="info"
                            class="w-full justify-center font-semibold shadow-sm"
                        >
                            Launch POS Register
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            {{-- Onboard / Manage Tenants Card --}}
            <x-filament::section :compact="true" class="h-full border border-white/5 bg-slate-900/60">
                <div class="flex flex-col h-full justify-between min-h-[190px]">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-500/15 text-emerald-400">
                                <x-filament::icon icon="heroicon-o-user-group" class="w-5 h-5" />
                            </span>
                            <x-filament::badge color="success">Organizations</x-filament::badge>
                        </div>
                        <h3 class="text-base font-bold text-white mb-1">
                            Manage Retail Chains
                        </h3>
                        <p class="text-xs leading-relaxed text-slate-400 mb-4">
                            Onboard new retail chains, configure subdomains, inspect RLS boundaries, and manage lifecycles.
                        </p>
                    </div>
                    <div class="mt-auto pt-2">
                        <x-filament::button
                            tag="a"
                            :href="url('/platform/tenants')"
                            icon="heroicon-m-arrow-right"
                            icon-position="after"
                            color="gray"
                            class="w-full justify-center font-semibold shadow-sm"
                        >
                            Manage Tenants
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            {{-- Configure Plans Card --}}
            <x-filament::section :compact="true" class="h-full border border-white/5 bg-slate-900/60">
                <div class="flex flex-col h-full justify-between min-h-[190px]">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-orange-500/15 text-orange-400">
                                <x-filament::icon icon="heroicon-o-credit-card" class="w-5 h-5" />
                            </span>
                            <x-filament::badge color="warning">Tiers & Limits</x-filament::badge>
                        </div>
                        <h3 class="text-base font-bold text-white mb-1">
                            Plans & Quotas
                        </h3>
                        <p class="text-xs leading-relaxed text-slate-400 mb-4">
                            Configure SaaS subscription tiers, branch and POS terminal quotas, pricing, and module entitlements.
                        </p>
                    </div>
                    <div class="mt-auto pt-2">
                        <x-filament::button
                            tag="a"
                            :href="url('/platform/plans')"
                            icon="heroicon-m-arrow-right"
                            icon-position="after"
                            color="gray"
                            class="w-full justify-center font-semibold shadow-sm"
                        >
                            Configure Plans
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
