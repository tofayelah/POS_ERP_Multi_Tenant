<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class StoreQuickActionsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.store-quick-actions';
}
