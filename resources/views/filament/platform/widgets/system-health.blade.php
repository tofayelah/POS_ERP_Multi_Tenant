<x-filament-widgets::widget>
    <x-filament::section class="h-full border border-white/5 bg-slate-900/60">
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-emerald-500/15 text-emerald-400">
                        <x-filament::icon icon="heroicon-o-shield-check" class="w-4 h-4" />
                    </span>
                    <span class="font-bold text-base text-white">System & Security Telemetry</span>
                </div>
                <x-filament::badge :color="$overallColor">{{ $overallStatus }}</x-filament::badge>
            </div>
        </x-slot>
        <x-slot name="description">
            <span class="text-xs text-slate-400">Live multi-tenant catalog probe & infrastructure status (Checked {{ $checkedAt }})</span>
        </x-slot>

        <div class="flex flex-col justify-between h-[calc(100%-1rem)] min-h-[270px]">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                <!-- PostgreSQL 17 DB -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg bg-white/[0.02] border border-white/5">
                    <span class="w-2 h-2 rounded-full {{ $postgres['status'] === 'ok' ? 'bg-emerald-500' : 'bg-rose-500' }} mt-1.5 shrink-0"></span>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">PostgreSQL 17 DB</div>
                        <div class="text-[11px] text-slate-400 truncate" title="{{ $postgres['details'] }}">{{ $postgres['details'] }}</div>
                    </div>
                </div>

                <!-- PostgreSQL RLS -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg bg-white/[0.02] border border-white/5">
                    <span class="w-2 h-2 rounded-full {{ $rls['status'] === 'ok' ? 'bg-emerald-500' : 'bg-rose-500' }} mt-1.5 shrink-0"></span>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">PostgreSQL RLS</div>
                        <div class="text-[11px] text-slate-400 truncate" title="{{ $rls['details'] }}">{{ $rls['details'] }}</div>
                    </div>
                </div>

                <!-- Redis 7 Cluster -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg bg-white/[0.02] border border-white/5">
                    <span class="w-2 h-2 rounded-full {{ $redis['status'] === 'ok' ? 'bg-emerald-500' : 'bg-rose-500' }} mt-1.5 shrink-0"></span>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">Redis 7 Cluster</div>
                        <div class="text-[11px] text-slate-400 truncate" title="{{ $redis['details'] }}">{{ $redis['details'] }}</div>
                    </div>
                </div>

                <!-- Horizon Queues -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg bg-white/[0.02] border border-white/5">
                    <span class="w-2 h-2 rounded-full {{ $queue['status'] === 'ok' ? 'bg-emerald-500' : ($queue['status'] === 'warning' ? 'bg-amber-500' : 'bg-rose-500') }} mt-1.5 shrink-0"></span>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">Horizon Queues</div>
                        <div class="text-[11px] text-slate-400 truncate" title="{{ $queue['details'] }}">{{ $queue['details'] }}</div>
                    </div>
                </div>

                <!-- Go Hardware Bridge -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg bg-white/[0.02] border border-white/5">
                    <span class="w-2 h-2 rounded-full {{ $bridge['status'] === 'ok' ? 'bg-emerald-500' : ($bridge['status'] === 'degraded' ? 'bg-amber-500' : 'bg-slate-500') }} mt-1.5 shrink-0"></span>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">Go POS Bridge</div>
                        <div class="text-[11px] text-slate-400 truncate" title="{{ $bridge['details'] }}">{{ $bridge['details'] }}</div>
                    </div>
                </div>

                <!-- Audit Trail Integrity -->
                <div class="flex items-start gap-2.5 p-2 rounded-lg bg-white/[0.02] border border-white/5">
                    <span class="w-2 h-2 rounded-full {{ $audit['status'] === 'ok' ? 'bg-emerald-500' : 'bg-rose-500' }} mt-1.5 shrink-0"></span>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold text-slate-200">Audit Trail Seal</div>
                        <div class="text-[11px] text-slate-400 truncate" title="{{ $audit['details'] }}">{{ $audit['details'] }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-2 border-t border-white/5">
                <x-filament::button
                    tag="a"
                    :href="url('/ready')"
                    target="_blank"
                    icon="heroicon-m-arrow-top-right-on-square"
                    icon-position="after"
                    color="gray"
                    size="sm"
                    class="w-full justify-center font-semibold text-xs"
                >
                    View System Readiness JSON (/ready)
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
