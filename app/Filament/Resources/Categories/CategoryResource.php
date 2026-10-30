<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\Company;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categories / শ্রেণী';

    protected static \UnitEnum|string|null $navigationGroup = 'Product Master';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->label('Company / কোম্পানি')
                    ->options(function () {
                        $tenant = Filament::getTenant();

                        return $tenant ? Company::where('tenant_id', $tenant->id)->pluck('legal_name', 'id') : [];
                    })
                    ->default(fn () => Filament::getTenant()?->companies()->first()?->id)
                    ->required(),

                Select::make('parent_id')
                    ->label('Parent Category / মূল শ্রেণী')
                    ->relationship('parent', 'name_en')
                    ->nullable()
                    ->searchable(),

                TextInput::make('code')
                    ->label('Category Code / কোড')
                    ->required()
                    ->maxLength(50),

                TextInput::make('name_en')
                    ->label('English Name / ইংরেজি নাম')
                    ->required()
                    ->maxLength(255),

                TextInput::make('name_bn')
                    ->label('Bangla Name / বাংলা নাম')
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Description / বিবরণ')
                    ->nullable(),

                Toggle::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code / কোড')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_en')
                    ->label('English Name / ইংরেজি নাম')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_bn')
                    ->label('Bangla Name / বাংলা নাম')
                    ->searchable(),

                TextColumn::make('parent.name_en')
                    ->label('Parent / মূল শ্রেণী')
                    ->placeholder('—')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active / সক্রিয়')
                    ->boolean(),
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

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
