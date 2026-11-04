<?php

namespace App\Filament\Platform\Resources\Tenants;

use App\Filament\Platform\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Platform\Resources\Tenants\Pages\EditTenant;
use App\Filament\Platform\Resources\Tenants\Pages\ListTenants;
use App\Models\Plan;
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
                    ->placeholder('e.g. Dhaka Superstore')
                    ->required()
                    ->maxLength(255),

                TextInput::make('subdomain')
                    ->label('Portal Subdomain')
                    ->placeholder('e.g. dhaka-mart')
                    ->helperText('Tenant admin will be accessible at https://dev.kcerp.com/admin/{subdomain}')
                    ->unique(Tenant::class, 'subdomain', ignoreRecord: true)
                    ->required()
                    ->alphaDash()
                    ->maxLength(63)
                    ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtolower(trim($state)) : null),

                Select::make('subscription_plan')
                    ->label('Initial Plan Tier')
                    ->options(
                        Plan::query()
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn (Plan $plan): array => [
                                $plan->code => "{$plan->name} (৳".number_format((float) $plan->price_bdt, 0).'/mo)',
                            ])
                    )
                    ->default('standard')
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'in_arrears' => 'In Arrears',
                        'suspended' => 'Suspended',
                        'offboarded' => 'Offboarded',
                    ])
                    ->default('active')
                    ->required(),

                TextInput::make('admin_email')
                    ->label('Store Admin Email')
                    ->placeholder('e.g. owner@mystore.com')
                    ->email()
                    ->helperText('Administrator account created to manage this store')
                    ->visibleOn('create'),

                TextInput::make('admin_password')
                    ->label('Store Admin Password')
                    ->password()
                    ->placeholder('Defaults to Password123!')
                    ->helperText('Leave empty to use default: Password123!')
                    ->visibleOn('create'),

                TextInput::make('company_name')
                    ->label('Legal Company Name')
                    ->placeholder('Optional, defaults to Organization Name Ltd')
                    ->visibleOn('create'),

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
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-building-storefront'),

                TextColumn::make('subdomain')
                    ->label('Subdomain')
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
                    ->formatStateUsing(fn (?string $state): string => ucfirst($state ?? 'Standard')),

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
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),

                Action::make('open_store')
                    ->label('Open Store')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (Tenant $record): string => url("/admin/{$record->subdomain}"), shouldOpenInNewTab: true),

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

                Action::make('reinstate')
                    ->label('Reinstate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Reinstate Tenant')
                    ->modalDescription('Reinstate tenant and restore full POS & back-office access.')
                    ->action(function (Tenant $record): void {
                        app(TenantLifecycleServiceContract::class)->reinstate($record);
                        Notification::make()
                            ->title('Tenant Reinstated')
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
            'create' => CreateTenant::route('/create'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
