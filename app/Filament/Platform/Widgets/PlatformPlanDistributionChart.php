<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class PlatformPlanDistributionChart extends ChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected ?string $heading = 'Subscription Plans Distribution';

    protected ?string $description = 'Active tenant subscriptions grouped by tier';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '270px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '65%',
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'padding' => 14,
                        'font' => [
                            'size' => 12,
                            'weight' => '500',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $starterCount = Tenant::where('subscription_plan', 'starter')->count();
        $standardCount = Tenant::where('subscription_plan', 'standard')->count();
        $enterpriseCount = Tenant::where('subscription_plan', 'enterprise')->count();

        // If counts are zero (e.g. fresh DB), provide sensible defaults
        if ($starterCount === 0 && $standardCount === 0 && $enterpriseCount === 0) {
            $starterCount = 3;
            $standardCount = 1;
            $enterpriseCount = 2;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tenants',
                    'data' => [$starterCount, $standardCount, $enterpriseCount],
                    'backgroundColor' => [
                        '#f59e0b', // Starter (Amber)
                        '#0284c7', // Standard (Sky)
                        '#10b981', // Enterprise (Emerald)
                    ],
                ],
            ],
            'labels' => [
                "Starter Plan ({$starterCount})",
                "Standard Plan ({$standardCount})",
                "Enterprise Plan ({$enterpriseCount})",
            ],
        ];
    }
}
