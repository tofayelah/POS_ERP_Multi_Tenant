<?php

namespace App\Filament\Resources\LandedCosts;

use App\Filament\Resources\LandedCosts\Pages\CreateLandedCost;
use App\Filament\Resources\LandedCosts\Pages\EditLandedCost;
use App\Filament\Resources\LandedCosts\Pages\ListLandedCosts;
use App\Models\Company;
use App\Models\LandedCost;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LandedCostResource extends Resource
{
    protected static ?string $model = LandedCost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Landed Costs / আমদানি ও পরিবহন ব্যয়';

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label('Company / কোম্পানি')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Company::where('tenant_id', $tenant->id)->pluck('legal_name', 'id') : [];
                    })
                    ->default(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Company::where('tenant_id', $tenant->id)->first()?->id : null;
                    })
                    ->required(),

                Select::make('cost_type')
                    ->label('Cost Type / ব্যয়ের ধরণ')
                    ->options([
                        'freight' => 'Freight / পরিবহন',
                        'customs' => 'Customs / শুল্ক',
                        'insurance' => 'Insurance / বীমা',
                        'handling' => 'Handling / লোডিং-আনলোডিং',
                        'other' => 'Other / অন্যান্য',
                    ])
                    ->required(),

                TextInput::make('amount')
                    ->label('Amount / পরিমাণ (BDT)')
                    ->numeric()
                    ->required(),

                Select::make('allocation_method')
                    ->label('Allocation Method / বণ্টন পদ্ধতি')
                    ->options([
                        'by_quantity' => 'By Quantity / পরিমাণ অনুযায়ী',
                        'by_value' => 'By Value / মূল্য অনুযায়ী',
                        'by_weight' => 'By Weight / ওজন অনুযায়ী',
                    ])
                    ->default('by_quantity')
                    ->required(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('cost_type')
                    ->label('Cost Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount (BDT)')
                    ->numeric(decimalPlaces: 4)
                    ->sortable(),

                TextColumn::make('allocation_method')
                    ->label('Allocation Method')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'posted' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('posted_at')
                    ->label('Posted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandedCosts::route('/'),
            'create' => CreateLandedCost::route('/create'),
            'edit' => EditLandedCost::route('/{record}/edit'),
        ];
    }
}
