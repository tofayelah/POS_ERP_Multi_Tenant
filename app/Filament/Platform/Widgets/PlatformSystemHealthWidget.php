<?php

namespace App\Filament\Platform\Widgets;

use Filament\Widgets\Widget;

class PlatformSystemHealthWidget extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    /**
     * @var view-string
     */
    protected string $view = 'filament.platform.widgets.system-health';
}
