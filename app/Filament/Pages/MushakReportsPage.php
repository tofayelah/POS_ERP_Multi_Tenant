<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class MushakReportsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Mushak reports';

    protected ?string $heading = 'Mushak VAT Reports';

    protected static \UnitEnum|string|null $navigationGroup = 'Tax & VAT Compliance';

    protected string $view = 'filament.pages.placeholder';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        // Cashier: 6.3 Print, Shift Supervisor: None, Branch Manager: Branch 6.3, Accountant: Full, Tenant Owner: Full
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Branch Manager',
            'Accountant',
            'Cashier',
            'Auditor',
        ]);
    }
}
