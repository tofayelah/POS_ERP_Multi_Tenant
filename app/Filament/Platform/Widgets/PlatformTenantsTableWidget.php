<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PlatformTenantsTableWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Tenant::query()->latest())
            ->heading('Registered Retail Tenants & Store Portals')
            ->description('Active multi-tenant instances with per-tenant schema isolation and RLS policies.')
            ->columns([
                TextColumn::make('name')
                    ->label('Organization / Retail Chain')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-building-storefront'),

                TextColumn::make('subdomain')
                    ->label('Portal Subdomain')
                    ->badge()
                    ->color('info')
                    ->searchable(),

                TextColumn::make('subscription_plan')
                    ->label('Plan Tier')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'enterprise' => 'success',
                        'standard' => 'info',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'Starter')),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'in_arrears' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Onboarded Date')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([
                Action::make('open_admin')
                    ->label('Open Store ERP')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (Tenant $record): string => url("/admin/{$record->subdomain}"), shouldOpenInNewTab: true),

                Action::make('edit_tenant')
                    ->label('Settings')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->color('gray')
                    ->url(fn (Tenant $record): string => TenantResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
