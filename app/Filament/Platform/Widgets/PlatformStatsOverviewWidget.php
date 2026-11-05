<?php

namespace App\Filament\Platform\Widgets;

use App\Core\Platform\Services\PlatformTelemetryService;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\PosTerminal;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PlatformStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var int | array<string, ?int> | null
     */
    protected int|array|null $columns = [
        'default' => 1,
        'sm' => 2,
        'md' => 2,
        'lg' => 4,
        'xl' => 4,
    ];

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $telemetry = app(PlatformTelemetryService::class);

        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $inArrearsTenants = Tenant::where('status', 'in_arrears')->count();

        // Real 7-day tenant registration trend (zero fabrication)
        $sevenDaysAgo = Carbon::now()->subDays(6)->startOfDay();
        /** @var array<int, int> $tenantHistory */
        $tenantHistory = Tenant::query()
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->map(fn (mixed $c): int => (int) $c)
            ->toArray();

        // 2. Real Financial Metrics (Zero hardcoded fallback)
        $financials = $telemetry->getFinancialMetrics();

        // 3. Physical Outlets and Edge Terminals Fleet
        $branchesCount = Branch::count();
        $terminalsCount = PosTerminal::count();
        $openShiftsCount = PosShift::where('status', 'open')->count();

        // 4. Live PostgreSQL RLS Catalog Inspection
        $rls = $telemetry->getRlsCompliance();
        $rlsStatus = $rls['status'] === 'ok';

        $tenantStat = Stat::make('Retail Organizations', (string) $totalTenants)
            ->description("{$activeTenants} Active".($inArrearsTenants > 0 ? " • {$inArrearsTenants} In Arrears" : ' • 0 In Arrears'))
            ->descriptionIcon('heroicon-m-building-office-2')
            ->color($inArrearsTenants > 0 ? 'warning' : 'success');

        if (count($tenantHistory) >= 2) {
            $tenantStat->chart($tenantHistory);
        }

        $mrrStat = Stat::make('Platform MRR', $financials['formatted_mrr'])
            ->description(
                $financials['has_data']
                    ? "{$financials['formatted_arr']} ARR • {$financials['active_subscriptions_count']} Active Chains"
                    : 'No active paid subscriptions • ৳ 0 ARR'
            )
            ->descriptionIcon('heroicon-m-banknotes')
            ->color($financials['has_data'] ? 'primary' : 'gray');

        $fleetStat = Stat::make('Outlets & Edge POS', "{$branchesCount} Stores")
            ->description("{$terminalsCount} Terminals • {$openShiftsCount} Open Cashier Shifts")
            ->descriptionIcon('heroicon-m-computer-desktop')
            ->color('info');

        $rlsStat = Stat::make('PostgreSQL 17 RLS', "{$rls['forced_tables']}/{$rls['total_tables']} Forced")
            ->description($rlsStatus ? '100% tenant tables enforce fail-closed RLS' : 'CRITICAL: Tables without forced RLS detected!')
            ->descriptionIcon($rlsStatus ? 'heroicon-m-shield-check' : 'heroicon-m-exclamation-triangle')
            ->color($rlsStatus ? 'success' : 'danger');

        return [
            $tenantStat,
            $mrrStat,
            $fleetStat,
            $rlsStat,
        ];
    }
}
