<?php

namespace App\Filament\Platform\Resources\Plans;

use App\Filament\Platform\Resources\Plans\Pages\CreatePlan;
use App\Filament\Platform\Resources\Plans\Pages\EditPlan;
use App\Filament\Platform\Resources\Plans\Pages\ListPlans;
use App\Models\Plan;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'Plans';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Plan Code / Slug')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name')
                    ->label('Plan Name')
                    ->required()
                    ->maxLength(100),

                TextInput::make('price_bdt')
                    ->label('Price (BDT)')
                    ->numeric()
                    ->default(0.00)
                    ->required(),

                Select::make('billing_interval')
                    ->label('Interval')
                    ->options([
                        'monthly' => 'Monthly',
                        'annual' => 'Annual',
                    ])
                    ->default('monthly')
                    ->required(),

                TextInput::make('max_branches')
                    ->label('Max Branches')
                    ->numeric()
                    ->default(1)
                    ->required(),

                TextInput::make('max_terminals')
                    ->label('Max POS Terminals')
                    ->numeric()
                    ->default(1)
                    ->required(),

                TextInput::make('max_users')
                    ->label('Max Users')
                    ->numeric()
                    ->default(5)
                    ->required(),

                CheckboxList::make('included_modules')
                    ->label('Included Modules')
                    ->options([
                        'core' => 'Core Platform',
                        'accounting' => 'Finance & Accounting',
                        'tax' => 'NBR Tax & VAT',
                        'catalog' => 'Product Catalog',
                        'inventory' => 'Inventory Management',
                        'procurement' => 'Procurement & Purchase',
                        'sales' => 'CRM & Sales',
                        'pos' => 'POS Cashier Terminal',
                        'reporting' => 'BI & Executive Reports',
                    ])
                    ->columns(3),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Textarea::make('description')
                    ->label('Description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('price_bdt')
                    ->label('Price (BDT)')
                    ->money('BDT')
                    ->sortable(),

                TextColumn::make('billing_interval')
                    ->label('Interval')
                    ->badge(),

                TextColumn::make('max_branches')
                    ->label('Max Branches'),

                TextColumn::make('max_terminals')
                    ->label('Max Terminals'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlans::route('/'),
            'create' => CreatePlan::route('/create'),
            'edit' => EditPlan::route('/{record}/edit'),
        ];
    }
}
