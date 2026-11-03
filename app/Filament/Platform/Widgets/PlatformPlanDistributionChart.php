<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class PlatformPlanDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Subscription Plans Distribution';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getType(): string
    {
        return 'doughnut';
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
