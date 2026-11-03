<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; gap: 0.625rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 0.5rem; background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                    <x-filament::icon icon="heroicon-o-bolt" style="width: 1.25rem; height: 1.25rem;" />
                </span>
                <span style="font-size: 1.15rem; font-weight: 700; letter-spacing: -0.01em;">
                    Platform Quick Actions & Store Launchpad
                </span>
            </div>
        </x-slot>
        <x-slot name="description">
            <span style="color: #9ca3af; font-size: 0.875rem;">
                One-click access to retail store ERP instances, cashier POS terminals, tenant provisioning, and billing controls.
            </span>
        </x-slot>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 1.25rem; margin-top: 1rem;">
            <!-- Card 1: Acme Retail BD (Pilot) -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                                <x-filament::icon icon="heroicon-o-building-storefront" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="success">Pilot Store</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            Acme Retail BD
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Flagship 3-branch pilot (Dhanmondi, Gulshan, Uttara) with live double-entry GL & Mushak 6.3 VAT.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url('/admin/acme')"
                            target="_blank"
                            icon="heroicon-m-arrow-top-right-on-square"
                            icon-position="after"
                            color="warning"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            Open Store ERP
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Card 2: Offline POS Cashier -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(2, 132, 199, 0.15); color: #38bdf8;">
                                <x-filament::icon icon="heroicon-o-computer-desktop" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="info">Offline PWA</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            POS Cashier Terminal
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Dexie IndexedDB offline cashier register with barcode scanner and Go hardware bridge integration.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url('/pos/app')"
                            target="_blank"
                            icon="heroicon-m-arrow-top-right-on-square"
                            icon-position="after"
                            color="info"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            Launch POS Terminal
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Card 3: Staging Retail Mart -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(147, 51, 234, 0.15); color: #c084fc;">
                                <x-filament::icon icon="heroicon-o-building-office-2" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="primary">Staging Testbed</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            Staging Retail Mart
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Secondary pilot testbed with Dhanmondi Superstore and Staging Flagship branches for rehearsals.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url('/admin/staging')"
                            target="_blank"
                            icon="heroicon-m-arrow-top-right-on-square"
                            icon-position="after"
                            color="primary"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            Open Staging ERP
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Card 4: Manage Retail Tenants -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(16, 185, 129, 0.15); color: #34d399;">
                                <x-filament::icon icon="heroicon-o-user-group" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="success">Multi-Tenant</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            Retail Organizations
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Onboard new retail chains, configure subdomains, inspect RLS boundaries, and manage tenant lifecycles.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url('/platform/tenants')"
                            icon="heroicon-m-arrow-right"
                            icon-position="after"
                            color="gray"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            Manage Tenants
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Card 5: Plans & Tiers -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(234, 88, 12, 0.15); color: #fb923c;">
                                <x-filament::icon icon="heroicon-o-credit-card" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="warning">SaaS Billing</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            Plans & Tier Limits
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Starter (৳2,500), Standard (৳5,000), and Enterprise (৳15,000) tiers with terminal & branch quotas.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url('/platform/plans')"
                            icon="heroicon-m-arrow-right"
                            icon-position="after"
                            color="gray"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            Configure Plans
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Card 6: Watchdog & Invariant Health -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(16, 185, 129, 0.15); color: #34d399;">
                                <x-filament::icon icon="heroicon-o-shield-check" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="success">Watchdog 100%</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            Daily Invariant Watchdog
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Automated reconciliation of GL cash accounts, inventory movements, Mushak 6.2 VAT, and audit chains.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url('/ready')"
                            target="_blank"
                            icon="heroicon-m-arrow-top-right-on-square"
                            icon-position="after"
                            color="success"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            System Health Status
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
