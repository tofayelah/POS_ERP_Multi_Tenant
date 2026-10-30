@php
    $status = $this->getOnboardingStatus();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        @if ($status['is_fully_configured'] ?? false)
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center gap-3">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <h3 class="font-bold text-base text-emerald-200">Onboarding Complete & Statutory Data Seeded</h3>
                    <p class="text-sm text-emerald-300/80">Your store is fully provisioned with Bangladesh Chart of Accounts, NBR VAT codes, POS terminal, and fiscal periods.</p>
                </div>
            </div>
        @else
            <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center gap-3">
                <svg class="w-6 h-6 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h3 class="font-bold text-base text-amber-200">Onboarding In Progress</h3>
                    <p class="text-sm text-amber-300/80">Some statutory records or store resources are still pending initialisation.</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="p-5 rounded-xl border border-slate-700/60 bg-slate-800/40 backdrop-blur">
                <div class="text-xs uppercase font-semibold text-slate-400 tracking-wider">Chart of Accounts</div>
                <div class="text-3xl font-extrabold text-white mt-2">{{ $status['coa_count'] ?? 0 }}</div>
                <div class="text-xs text-sky-400 mt-1">Bangladesh Retail Standard</div>
            </div>

            <div class="p-5 rounded-xl border border-slate-700/60 bg-slate-800/40 backdrop-blur">
                <div class="text-xs uppercase font-semibold text-slate-400 tracking-wider">NBR Tax & VAT Codes</div>
                <div class="text-3xl font-extrabold text-white mt-2">{{ $status['tax_code_count'] ?? 0 }}</div>
                <div class="text-xs text-emerald-400 mt-1">VAT 15%, 5%, 0%, Exempt</div>
            </div>

            <div class="p-5 rounded-xl border border-slate-700/60 bg-slate-800/40 backdrop-blur">
                <div class="text-xs uppercase font-semibold text-slate-400 tracking-wider">Retail Branches</div>
                <div class="text-3xl font-extrabold text-white mt-2">{{ $status['branch_count'] ?? 0 }}</div>
                <div class="text-xs text-indigo-400 mt-1">Active Store Outlets</div>
            </div>

            <div class="p-5 rounded-xl border border-slate-700/60 bg-slate-800/40 backdrop-blur">
                <div class="text-xs uppercase font-semibold text-slate-400 tracking-wider">POS Terminals</div>
                <div class="text-3xl font-extrabold text-white mt-2">{{ $status['terminal_count'] ?? 0 }}</div>
                <div class="text-xs text-amber-400 mt-1">Offline-first Cash Counters</div>
            </div>

            <div class="p-5 rounded-xl border border-slate-700/60 bg-slate-800/40 backdrop-blur">
                <div class="text-xs uppercase font-semibold text-slate-400 tracking-wider">Fiscal Years & Periods</div>
                <div class="text-3xl font-extrabold text-white mt-2">{{ $status['period_count'] ?? 0 }} Periods</div>
                <div class="text-xs text-purple-400 mt-1">July–June Statutory Calendar</div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
