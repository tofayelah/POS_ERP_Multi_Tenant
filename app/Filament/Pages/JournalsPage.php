<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class JournalsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Journals';

    protected ?string $heading = 'General Ledger Journals';

    protected static ?string $navigationGroup = 'Finance Accounting';

    protected string $view = 'filament.pages.placeholder';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // Cashier: None, Shift Supervisor: None, Branch Manager: Read, Accountant: Full, Tenant Owner: Full
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Branch Manager',
            'Accountant',
            'Auditor',
        ]);
    }
}
