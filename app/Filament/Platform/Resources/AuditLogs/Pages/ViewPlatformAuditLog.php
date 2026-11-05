<?php

namespace App\Filament\Platform\Resources\AuditLogs\Pages;

use App\Filament\Platform\Resources\AuditLogs\PlatformAuditLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPlatformAuditLog extends ViewRecord
{
    protected static string $resource = PlatformAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
