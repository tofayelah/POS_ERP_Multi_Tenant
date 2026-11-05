<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Plan;
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
        $palette = [
            '#f59e0b', // Amber
            '#0284c7', // Sky
            '#10b981', // Emerald
            '#a855f7', // Purple
            '#f43f5e', // Rose
            '#ea580c', // Orange
        ];

        $plans = Plan::all();
        $counts = [];
        $labels = [];
        $colors = [];
        $total = 0;

        foreach ($plans as $index => $plan) {
            $count = Tenant::where('subscription_plan', $plan->code)
                ->where('status', 'active')
                ->count();

            $total += $count;
            $counts[] = $count;
            $labels[] = "{$plan->name} ({$count})";
            $colors[] = $palette[$index % count($palette)];
        }

        // Honest empty state when no active subscriptions exist
        if ($total === 0) {
            return [
                'datasets' => [
                    [
                        'label' => 'Tenants',
                        'data' => [0],
                        'backgroundColor' => ['#334155'], // Slate 700
                    ],
                ],
                'labels' => ['No Active Subscriptions (0)'],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tenants',
                    'data' => $counts,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
