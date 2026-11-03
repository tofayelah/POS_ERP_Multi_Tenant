<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; gap: 0.625rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 0.5rem; background: rgba(2, 132, 199, 0.15); color: #38bdf8;">
                    <x-filament::icon icon="heroicon-o-bolt" style="width: 1.25rem; height: 1.25rem;" />
                </span>
                <span style="font-size: 1.15rem; font-weight: 700; letter-spacing: -0.01em;">
                    Store Quick Launchpad & Daily Cashier Tasks
                </span>
            </div>
        </x-slot>
        <x-slot name="description">
            <span style="color: #9ca3af; font-size: 0.875rem;">
                Quickly jump into cashier POS sales, statutory tax reports, real-time stock balances, and financial ledgers.
            </span>
        </x-slot>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr)); gap: 1rem; margin-top: 1rem;">
            <!-- Launch POS Cashier -->
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
                            High-speed barcode checkout, receipt printing, cash drawer kick, and offline sale buffering.
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
                            Open POS Register
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Mushak 6.3 Tax Invoices -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                                <x-filament::icon icon="heroicon-o-document-chart-bar" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="warning">NBR VAT</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            Mushak 6.3 & 6.2 Registers
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Generate official NBR tax invoices, credit notes (Mushak 6.7), debit notes (6.8), and sales registers.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url()->current() . '/mushak-reports'"
                            icon="heroicon-m-arrow-right"
                            icon-position="after"
                            color="warning"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            View VAT Registers
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Stock Balances & Locations -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(16, 185, 129, 0.15); color: #34d399;">
                                <x-filament::icon icon="heroicon-o-archive-box" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="success">Inventory</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            Stock Balances & Move
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Inspect multi-warehouse on-hand balances, batch numbers, expiry dates, and inter-branch transfers.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url()->current() . '/stock-balances'"
                            icon="heroicon-m-arrow-right"
                            icon-position="after"
                            color="success"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            Manage Stock
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            <!-- Trial Balance & Financials -->
            <x-filament::section :compact="true" style="height: 100%;">
                <div style="display: flex; flex-direction: column; height: 100%; justify-content: space-between; min-height: 190px;">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.85rem;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: rgba(147, 51, 234, 0.15); color: #c084fc;">
                                <x-filament::icon icon="heroicon-o-scale" style="width: 1.5rem; height: 1.5rem;" />
                            </span>
                            <x-filament::badge color="primary">Accounting</x-filament::badge>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #ffffff; margin-bottom: 0.35rem;">
                            General Ledger & Trial Balance
                        </h3>
                        <p style="font-size: 0.825rem; line-height: 1.45; color: #9ca3af; margin-bottom: 1.25rem;">
                            Double-entry balance verification, posted journals, accounts receivable/payable aging registers.
                        </p>
                    </div>
                    <div style="margin-top: auto;">
                        <x-filament::button
                            tag="a"
                            :href="url()->current() . '/trial-balance'"
                            icon="heroicon-m-arrow-right"
                            icon-position="after"
                            color="primary"
                            style="width: 100%; justify-content: center; font-weight: 600;"
                        >
                            View Financials
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
