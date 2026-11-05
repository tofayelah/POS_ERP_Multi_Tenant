<?php

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class PlatformQuickActionsWidget extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.platform.widgets.quick-actions';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Collection<int, Tenant> $activeTenants */
        $activeTenants = Tenant::query()
            ->where('status', 'active')
            ->latest()
            ->take(4)
            ->get();

        return [
            'activeTenants' => $activeTenants,
            'totalTenants' => Tenant::count(),
        ];
    }
}
