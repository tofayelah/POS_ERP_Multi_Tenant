<?php

namespace App\Filament\Resources\TaxCodes\RelationManagers;

use App\Models\TaxCode;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    protected static ?string $title = 'Effective Rates / কার্যকরী মূসক হার';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('rate_percent')
                    ->label('VAT Rate % / মূসক হার %')
                    ->numeric()
                    ->required()
                    ->default('15.00'),

                TextInput::make('supplementary_duty_percent')
                    ->label('Supplementary Duty % / সম্পূরক শুল্ক %')
                    ->numeric()
                    ->required()
                    ->default('0.00'),

                DatePicker::make('effective_from')
                    ->label('Effective From / হতে')
                    ->required()
                    ->default(now()),

                DatePicker::make('effective_to')
                    ->label('Effective To / পর্যন্ত')
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('rate_percent')
            ->columns([
                TextColumn::make('rate_percent')
                    ->label('VAT Rate % / মূসক %')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('supplementary_duty_percent')
                    ->label('Supplementary Duty % / সম্পূরক শুল্ক %')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('effective_from')
                    ->label('Effective From / হতে')
                    ->date()
                    ->sortable(),

                TextColumn::make('effective_to')
                    ->label('Effective To / পর্যন্ত')
                    ->date()
                    ->placeholder('Indefinite / অনির্দিষ্ট')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        /** @var TaxCode $owner */
                        $owner = $this->getOwnerRecord();
                        $data['tenant_id'] = $owner->tenant_id;
                        $data['company_id'] = $owner->company_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
