<?php

namespace App\Filament\Platform\Resources\Subscriptions;

use App\Filament\Platform\Resources\Subscriptions\Pages\CreateTenantSubscription;
use App\Filament\Platform\Resources\Subscriptions\Pages\EditTenantSubscription;
use App\Filament\Platform\Resources\Subscriptions\Pages\ListTenantSubscriptions;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantSubscriptionResource extends Resource
{
    protected static ?string $model = TenantSubscription::class;

    protected static ?string $slug = 'subscriptions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationLabel = 'Subscriptions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->label('Organization / Tenant')
                    ->options(Tenant::query()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('plan_id')
                    ->label('Subscription Plan Tier')
                    ->options(
                        Plan::query()
                            ->where('is_active', true)
                            ->get()
                            ->mapWithKeys(fn (Plan $plan): array => [
                                $plan->id => "{$plan->name} (৳".number_format((float) $plan->price_bdt, 0)." / {$plan->billing_interval})",
                            ])
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('status')
                    ->label('Subscription Status')
                    ->options([
                        'active' => 'Active',
                        'in_arrears' => 'In Arrears',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                    ])
                    ->default('active')
                    ->required(),

                DateTimePicker::make('starts_at')
                    ->label('Starts At')
                    ->default(now())
                    ->required(),

                DateTimePicker::make('current_period_starts_at')
                    ->label('Current Period Starts')
                    ->default(now())
                    ->required(),

                DateTimePicker::make('current_period_ends_at')
                    ->label('Expires / Renews At')
                    ->default(now()->addMonth())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->label('Tenant Organization')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-building-office-2'),

                TextColumn::make('plan.name')
                    ->label('Plan Tier')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtolower($state ?? '')) {
                        'enterprise plan' => 'success',
                        'standard retail plan', 'standard plan' => 'info',
                        default => 'warning',
                    }),

                TextColumn::make('plan.price_bdt')
                    ->label('Rate (৳)')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'in_arrears' => 'warning',
                        'cancelled', 'expired' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('starts_at')
                    ->label('Started')
                    ->dateTime('M d, Y')
                    ->sortable(),

                TextColumn::make('current_period_ends_at')
                    ->label('Renewal Date')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantSubscriptions::route('/'),
            'create' => CreateTenantSubscription::route('/create'),
            'edit' => EditTenantSubscription::route('/{record}/edit'),
        ];
    }
}
