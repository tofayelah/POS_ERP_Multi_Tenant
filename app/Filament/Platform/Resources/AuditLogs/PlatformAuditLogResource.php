<?php

namespace App\Filament\Platform\Resources\AuditLogs;

use App\Filament\Platform\Resources\AuditLogs\Pages\ListPlatformAuditLogs;
use App\Filament\Platform\Resources\AuditLogs\Pages\ViewPlatformAuditLog;
use App\Models\PlatformAuditLog;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PlatformAuditLogResource extends Resource
{
    protected static ?string $model = PlatformAuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Platform Audit Logs';

    protected static \UnitEnum|string|null $navigationGroup = 'SECURITY & GOVERNANCE';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('created_at')
                    ->label('Timestamp')
                    ->disabled(),

                TextInput::make('actor_name')
                    ->label('Operator Name')
                    ->disabled(),

                TextInput::make('actor_email')
                    ->label('Operator Email')
                    ->disabled(),

                TextInput::make('action')
                    ->label('Administrative Action')
                    ->disabled(),

                TextInput::make('resource_type')
                    ->label('Resource Type')
                    ->disabled(),

                TextInput::make('resource_id')
                    ->label('Resource ID')
                    ->disabled(),

                TextInput::make('client_ip')
                    ->label('Client IP')
                    ->disabled(),

                TextInput::make('status')
                    ->label('Status')
                    ->disabled(),

                Textarea::make('resource_description')
                    ->label('Description')
                    ->columnSpanFull()
                    ->disabled(),

                Textarea::make('reason')
                    ->label('Justification / Reason')
                    ->columnSpanFull()
                    ->disabled(),

                KeyValue::make('old_values')
                    ->label('Original State (Pre-Mutation)')
                    ->columnSpanFull()
                    ->disabled(),

                KeyValue::make('new_values')
                    ->label('New State (Post-Mutation)')
                    ->columnSpanFull()
                    ->disabled(),

                TextInput::make('payload_hash')
                    ->label('Cryptographic SHA-256 HMAC Seal')
                    ->columnSpanFull()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('actor_name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (PlatformAuditLog $record): string => $record->actor_email),

                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'tenant.suspended' => 'danger',
                        'tenant.reinstated' => 'success',
                        'tenant.created' => 'primary',
                        'plan.created', 'plan.updated' => 'info',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('resource_description')
                    ->label('Description')
                    ->searchable()
                    ->limit(45)
                    ->tooltip(fn (PlatformAuditLog $record): ?string => $record->resource_description),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->searchable()
                    ->limit(30)
                    ->placeholder('None specified'),

                TextColumn::make('client_ip')
                    ->label('Client IP')
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'success' ? 'success' : 'danger'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No platform audit events recorded')
            ->emptyStateDescription('Administrative mutations will be cryptographically recorded here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformAuditLogs::route('/'),
            'view' => ViewPlatformAuditLog::route('/{record}'),
        ];
    }
}
