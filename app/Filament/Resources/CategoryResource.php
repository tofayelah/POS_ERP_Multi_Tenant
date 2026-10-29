<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string | UnitEnum | null $navigationGroup = 'Product Master';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Categories';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Hierarchy & Details')
                    ->description('Organize products into hierarchical categories (e.g. Groceries > Spices).')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextInput::make('name_en')
                                    ->label('Category Name (English)')
                                    ->placeholder('e.g. Groceries, Dairy & Snacks')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('name_bn')
                                    ->label('বিভাগের নাম (বাংলা)')
                                    ->placeholder('যেমন: মুদি সামগ্রী, দুগ্ধ ও স্ন্যাক্স')
                                    ->maxLength(255),
                                TextInput::make('code')
                                    ->label('Category Code')
                                    ->placeholder('e.g. CAT-GROC, CAT-BEV')
                                    ->maxLength(64),
                                Select::make('parent_id')
                                    ->label('Parent Category')
                                    ->relationship('parent', 'name_en')
                                    ->placeholder('None (Top-Level Category)')
                                    ->searchable()
                                    ->preload(),
                                Toggle::make('is_active')
                                    ->label('Active Status')
                                    ->default(true),
                            ]),
                        Textarea::make('description')
                            ->label('Category Description')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name_en')
                    ->label('Category Name')
                    ->description(fn (Category $record): ?string => $record->name_bn)
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('parent.name_en')
                    ->label('Parent Category')
                    ->badge()
                    ->color('info')
                    ->placeholder('Top-level'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->badge()
                    ->color('success'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('d M Y, h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name_en'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
