<?php

namespace App\Filament\Resources\StockCountSessions;

use App\Filament\Resources\StockCountSessions\Pages\CreateStockCountSession;
use App\Filament\Resources\StockCountSessions\Pages\EditStockCountSession;
use App\Filament\Resources\StockCountSessions\Pages\ListStockCountSessions;
use App\Models\StockCountSession;
use App\Models\Tenant;
use App\Models\Warehouse;
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

class StockCountSessionResource extends Resource
{
    protected static ?string $model = StockCountSession::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Stock Counts / গণনা সেশন';

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('warehouse_id')
                    ->label('Warehouse / গুদাম')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Warehouse::where('tenant_id', $tenant->id)->pluck('name', 'id') : [];
                    })
                    ->required(),

                TextInput::make('session_number')
                    ->label('Session #')
                    ->disabled(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session_number')
                    ->label('Session #')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'in_progress' => 'warning',
                        'reconciled' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('snapshot_at')
                    ->label('Snapshot At')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Completed At')
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
            'index' => ListStockCountSessions::route('/'),
            'create' => CreateStockCountSession::route('/create'),
            'edit' => EditStockCountSession::route('/{record}/edit'),
        ];
    }
}
