<?php

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Modules\Sales\Contracts\SalesDocumentChainContract;
use App\Modules\Sales\DTOs\CreateSalesOrderDTO;
use App\Modules\Sales\DTOs\CreateSalesOrderLineDTO;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Quotation $record */
        $record = $this->getRecord();
        $data['lines'] = $record->lines->toArray();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (Quotation $record): bool => $record->status === 'draft'),

            Action::make('convert_to_so')
                ->label('Convert to Sales Order / বিক্রয় আদেশে রূপান্তর')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Convert to Sales Order')
                ->modalDescription('This will create an official Sales Order linked to this quotation and mark the quotation as converted.')
                ->visible(fn (Quotation $record): bool => in_array($record->status, ['draft', 'sent'], true) && ! $record->valid_until->isPast())
                ->action(function (Quotation $record, SalesDocumentChainContract $service) {
                    $lines = [];
                    foreach ($record->lines as $line) {
                        $lines[] = new CreateSalesOrderLineDTO(
                            quotationLineId: $line->id,
                            productId: $line->product_id,
                            variantId: $line->variant_id,
                            orderedQuantity: (string) $line->quantity,
                            unitPrice: (string) $line->unit_price,
                            taxAmount: (string) $line->tax_amount
                        );
                    }

                    $dto = new CreateSalesOrderDTO(
                        companyId: $record->company_id,
                        branchId: $record->branch_id,
                        customerId: $record->customer_id,
                        quotationId: $record->id,
                        orderDate: now()->toDateString(),
                        expectedDeliveryDate: now()->addDays(7)->toDateString(),
                        createdBy: (string) (Auth::id() ?? Str::uuid()),
                        lines: $lines
                    );

                    $so = $service->convertQuotationToSalesOrder($record->id, $dto);

                    Notification::make()
                        ->title('Quotation Converted to SO')
                        ->body("Created Sales Order #{$so->order_number}")
                        ->success()
                        ->send();
                }),
        ];
    }
}
