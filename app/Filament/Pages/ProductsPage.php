<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ProductsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Products';

    protected ?string $heading = 'Product Master';

    protected static \UnitEnum|string|null $navigationGroup = 'Product Master';

    protected string $view = 'filament.pages.placeholder';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Branch Manager',
            'Shift Supervisor',
            'Accountant',
            'Procurement Manager',
            'Sales Manager',
            'Warehouse Manager',
            'Cashier',
            'Sales Representative',
            'Auditor',
        ]);
    }
}
