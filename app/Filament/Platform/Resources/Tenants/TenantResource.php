<?php

namespace App\Filament\Platform\Resources\Tenants;

use App\Filament\Platform\Resources\Tenants\Pages\EditTenant;
use App\Filament\Platform\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
use App\Modules\Billing\Contracts\TenantLifecycleServiceContract;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Tenants';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Organization / Tenant Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('subdomain')
                    ->label('Subdomain')
                    ->required()
                    ->maxLength(63),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'in_arrears' => 'In Arrears',
                        'suspended' => 'Suspended',
                        'offboarded' => 'Offboarded',
                    ])
                    ->required(),

                TextInput::make('subscription_plan')
                    ->label('Plan')
                    ->default('standard'),

                TextInput::make('data_residency_region')
                    ->label('Data Residency Region')
                    ->default('dhaka-1'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Tenant Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subdomain')
                    ->label('Subdomain')
                    ->badge()
                    ->color('info')
                    ->searchable(),

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
                    ->label('Created At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),

                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Tenant')
                    ->modalDescription('Suspending will restrict tenant mutations and block new POS shifts while preserving exports.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Suspension Reason')
                            ->placeholder('e.g. Non-payment of subscription dues')
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        app(TenantLifecycleServiceContract::class)->suspend($record, (string) $data['reason']);
                        Notification::make()
                            ->title('Tenant Suspended')
                            ->body("{$record->name} is now suspended.")
                            ->danger()
                            ->send();
                    })
                    ->visible(fn (Tenant $record): bool => $record->status !== 'suspended'),

                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activate Tenant')
                    ->modalDescription('Activate tenant and restore full POS & back-office access.')
                    ->action(function (Tenant $record): void {
                        app(TenantLifecycleServiceContract::class)->activate($record);
                        Notification::make()
                            ->title('Tenant Activated')
                            ->body("{$record->name} has been restored to active status.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Tenant $record): bool => $record->status === 'suspended'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
