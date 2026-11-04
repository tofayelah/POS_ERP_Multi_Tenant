<?php

namespace App\Filament\Resources\PosTerminals;

use App\Filament\Resources\PosTerminals\Pages\CreatePosTerminal;
use App\Filament\Resources\PosTerminals\Pages\EditPosTerminal;
use App\Filament\Resources\PosTerminals\Pages\ListPosTerminals;
use App\Models\Branch;
use App\Models\PosTerminal;
use App\Models\Tenant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PosTerminalResource extends Resource
{
    protected static ?string $model = PosTerminal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'POS Terminals / পিওএস টার্মিনাল';

    protected static \UnitEnum|string|null $navigationGroup = 'POS';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->label('Branch / শাখা')
                    ->options(function () {
                        /** @var Tenant|null $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant ? Branch::where('tenant_id', $tenant->id)->pluck('name', 'id') : [];
                    })
                    ->required(),

                TextInput::make('terminal_code')
                    ->label('Terminal Code / টার্মিনাল কোড')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name')
                    ->label('Terminal Name / নাম')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('terminal_code')
                    ->label('Code / কোড')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Terminal Name / নাম')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Branch / শাখা')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => ListPosTerminals::route('/'),
            'create' => CreatePosTerminal::route('/create'),
            'edit' => EditPosTerminal::route('/{record}/edit'),
        ];
    }
}
