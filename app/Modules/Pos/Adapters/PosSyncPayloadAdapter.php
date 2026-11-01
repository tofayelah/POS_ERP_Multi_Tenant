<?php

namespace App\Modules\Pos\Adapters;

use App\Modules\Pos\DTOs\SyncBatchRequestDTO;
use App\Modules\Pos\DTOs\SyncSaleDTO;
use App\Modules\Pos\DTOs\SyncSaleItemDTO;
use Carbon\Carbon;
use InvalidArgumentException;

class PosSyncPayloadAdapter
{
    /**
     * Adapt incoming raw JSON payload (v1 or v2) into a normalized SyncBatchRequestDTO.
     *
     * @param  array<string, mixed>  $payload
     */
    public function adapt(array $payload, string $clientVersion = '1.2.0'): SyncBatchRequestDTO
    {
        $batchId = (string) ($payload['batch_id'] ?? '');
        $terminalId = (string) ($payload['terminal_id'] ?? '');
        $shiftId = (string) ($payload['shift_id'] ?? '');
        $deviceTimestamp = isset($payload['device_timestamp']) ? Carbon::parse((string) $payload['device_timestamp']) : null;

        if (empty($batchId) || empty($terminalId)) {
            throw new InvalidArgumentException('Missing mandatory batch_id or terminal_id in sync payload.');
        }

        $sales = [];
        $rawSales = is_array($payload['sales'] ?? null) ? $payload['sales'] : [];

        foreach ($rawSales as $rawSale) {
            if (! is_array($rawSale)) {
                continue;
            }

            $clientTxnId = (string) ($rawSale['client_txn_id'] ?? '');
            $deviceTime = Carbon::parse((string) ($rawSale['device_time'] ?? now()->toIso8601String()));
            $customerId = isset($rawSale['customer_id']) && $rawSale['customer_id'] !== '' ? (string) $rawSale['customer_id'] : null;
            $paymentStatus = (string) ($rawSale['payment_status'] ?? 'paid');
            $paymentMethod = strtolower((string) ($rawSale['payment_method'] ?? 'cash'));
            $pricingBasis = (string) ($rawSale['pricing_basis'] ?? 'tax_inclusive');
            $sdcInvoiceNumber = isset($rawSale['sdc_invoice_number']) && $rawSale['sdc_invoice_number'] !== '' ? (string) $rawSale['sdc_invoice_number'] : null;
            $fiscalSignature = isset($rawSale['fiscal_signature']) && $rawSale['fiscal_signature'] !== '' ? (string) $rawSale['fiscal_signature'] : null;

            $items = [];
            $rawItems = is_array($rawSale['items'] ?? null) ? $rawSale['items'] : [];

            foreach ($rawItems as $rawItem) {
                if (! is_array($rawItem)) {
                    continue;
                }

                $variantId = (string) ($rawItem['variant_id'] ?? '');
                $quantity = (string) ($rawItem['quantity'] ?? '1.0000');

                // v1 schema uses 'price', v2 uses 'unit_price'
                $unitPrice = (string) ($rawItem['unit_price'] ?? $rawItem['price'] ?? '0.0000');

                // v1 schema uses 'tax_rate', v2 uses 'tax_code_id'
                $taxCodeId = isset($rawItem['tax_code_id']) ? (string) $rawItem['tax_code_id'] : null;
                $flatTaxRate = isset($rawItem['tax_rate']) ? (string) $rawItem['tax_rate'] : null;
                $discountAmount = (string) ($rawItem['discount_amount'] ?? '0.0000');

                $items[] = new SyncSaleItemDTO(
                    variantId: $variantId,
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    taxCodeId: $taxCodeId,
                    flatTaxRate: $flatTaxRate,
                    discountAmount: $discountAmount
                );
            }

            $sales[] = new SyncSaleDTO(
                clientTxnId: $clientTxnId,
                deviceTime: $deviceTime,
                customerId: $customerId,
                paymentStatus: $paymentStatus,
                paymentMethod: $paymentMethod,
                pricingBasis: $pricingBasis,
                sdcInvoiceNumber: $sdcInvoiceNumber,
                fiscalSignature: $fiscalSignature,
                items: $items
            );
        }

        return new SyncBatchRequestDTO(
            batchId: $batchId,
            terminalId: $terminalId,
            shiftId: $shiftId,
            deviceTimestamp: $deviceTimestamp,
            sales: $sales
        );
    }
}
