<?php

namespace App\Filament\Widgets;

use App\Models\Branch;
use App\Models\PosTerminal;
use App\Models\SalesInvoice;
use App\Models\StockBalance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StoreStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        // Today's Sales
        $todaySales = (float) SalesInvoice::whereDate('invoice_date', today())
            ->where('status', 'issued')
            ->sum('total_payable');

        // Total Stock Value
        $stockValuation = (float) StockBalance::query()
            ->select(DB::raw('COALESCE(SUM(quantity_on_hand * weighted_avg_cost), 0) as total_val'))
            ->value('total_val');

        // Active Outlets & Terminals
        $branchesCount = Branch::where('is_active', true)->count();
        $terminalsCount = PosTerminal::where('is_active', true)->count();

        // Output VAT Accrued (Mushak 6.2) for current month
        $vatAccrued = (float) SalesInvoice::where('invoice_date', '>=', now()->startOfMonth())
            ->where('status', 'issued')
            ->sum('tax_amount');

        return [
            Stat::make("Today's Gross Sales", '৳ '.number_format($todaySales, 2))
                ->description('All POS & store registers today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([5000, 12000, 18000, 24000, max(30000, (int) $todaySales)]),

            Stat::make('Stock Valuation (On-Hand)', '৳ '.number_format($stockValuation, 2))
                ->description('Weighted average cost across warehouses')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info')
                ->chart([450000, 480000, 510000, max(550000, (int) $stockValuation)]),

            Stat::make('Retail Outlets & POS', "{$branchesCount} Branches")
                ->description("{$terminalsCount} Active POS Terminals")
                ->descriptionIcon('heroicon-m-computer-desktop')
                ->color('primary'),

            Stat::make('Output VAT Accrued (Mushak 6.2)', '৳ '.number_format($vatAccrued, 2))
                ->description('Statutory NBR VAT for current tax period')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('warning'),
        ];
    }
}
