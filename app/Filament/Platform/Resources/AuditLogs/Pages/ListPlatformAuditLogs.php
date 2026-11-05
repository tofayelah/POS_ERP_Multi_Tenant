<?php

namespace App\Filament\Platform\Resources\AuditLogs\Pages;

use App\Filament\Platform\Resources\AuditLogs\PlatformAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAuditLogs extends ListRecords
{
    protected static string $resource = PlatformAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
