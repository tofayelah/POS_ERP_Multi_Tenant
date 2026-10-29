<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AuditLogPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Audit log';

    protected ?string $heading = 'Audit Log Trail';

    protected static \UnitEnum|string|null $navigationGroup = 'Administration';

    protected string $view = 'filament.pages.placeholder';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user instanceof \App\Models\User) {
            return false;
        }

        // Cashier: None, Shift Supervisor: None, Branch Manager: Branch Logs, Accountant: Read-Only, Tenant Owner: Full
        return $user->hasAnyRole([
            'Tenant Owner',
            'Tenant Admin',
            'Branch Manager',
            'Accountant',
            'Auditor',
        ]);
    }
}
