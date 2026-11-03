<?php

namespace App\Filament\Platform\Widgets;

use Filament\Widgets\Widget;

class PlatformQuickActionsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.platform.widgets.quick-actions';
}
