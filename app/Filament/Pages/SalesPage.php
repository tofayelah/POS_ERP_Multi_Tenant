<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class SalesPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'Sales';

    protected ?string $heading = 'Sales & Orders';

    protected static \UnitEnum|string|null $navigationGroup = 'CRM & Sales';

    protected string $view = 'filament.pages.placeholder';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user instanceof \App\Models\User) {
            return false;
        }

        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Branch Manager',
            'Shift Supervisor',
            'Accountant',
            'Sales Manager',
            'Cashier',
            'Sales Representative',
            'Auditor',
        ]);
    }
}
