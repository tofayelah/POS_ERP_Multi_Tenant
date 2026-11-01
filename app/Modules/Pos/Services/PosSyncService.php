<?php

namespace App\Modules\Pos\Services;

use App\Core\Tenancy\TenantContext;
use App\Models\Customer;
use App\Models\PosTerminal;
use App\Models\ProductVariant;
use App\Models\SalesInvoice;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Warehouse;
use App\Modules\Inventory\Contracts\OfflineOversellServiceContract;
use App\Modules\Pos\Adapters\PosSyncPayloadAdapter;
use App\Modules\Pos\Contracts\PosSyncServiceContract;
use App\Modules\Pos\DTOs\SyncSaleDTO;
use App\Modules\Tax\Contracts\InvoiceIssuerContract;
use App\Modules\Tax\DTOs\InvoiceLineItemDTO;
use App\Modules\Tax\DTOs\IssueInvoiceRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PosSyncService implements PosSyncServiceContract
{
    public function __construct(
        protected PosSyncPayloadAdapter $adapter,
        protected ?InvoiceIssuerContract $invoiceIssuer = null,
        protected ?OfflineOversellServiceContract $oversellService = null,
    ) {
        $this->invoiceIssuer = $invoiceIssuer ?? app(InvoiceIssuerContract::class);
        $this->oversellService = $oversellService ?? app(OfflineOversellServiceContract::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function syncBatch(array $payload, string $clientVersion = '1.2.0'): array
    {
        $batch = $this->adapter->adapt($payload, $clientVersion);

        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new InvalidArgumentException('Cannot sync POS batch without active TenantContext.');
        }

        /** @var PosTerminal|null $terminal */
        $terminal = PosTerminal::query()
            ->with(['company', 'branch'])
            ->where('tenant_id', $tenantId)
            ->where('id', $batch->terminalId)
            ->first();

        if (! $terminal) {
            throw new InvalidArgumentException("POS Terminal [{$batch->terminalId}] not found for tenant.");
        }

        $serverReceivedTime = now();
        $clockSkewThreshold = (int) config('pos.clock_skew_threshold_seconds', 300);

        // Find warehouse for terminal's company
        $warehouse = Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $terminal->company_id)
            ->where('is_active', true)
            ->first();

        $results = [];

        foreach ($batch->sales as $sale) {
            try {
                $result = DB::transaction(function () use ($terminal, $sale, $serverReceivedTime, $clockSkewThreshold, $warehouse) {
                    return $this->processSingleSale($terminal, $sale, $serverReceivedTime, $clockSkewThreshold, $warehouse);
                });
                $results[] = $result;
            } catch (\Throwable $e) {
                $results[] = [
                    'client_txn_id' => $sale->clientTxnId,
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return [
            'batch_id' => $batch->batchId,
            'terminal_id' => $batch->terminalId,
            'status' => 'success',
            'processed_count' => count($batch->sales),
            'results' => $results,
        ];
    }

    /**
     * Process an individual sale in its own isolated database transaction / savepoint.
     *
     * @return array<string, mixed>
     */
    protected function processSingleSale(
        PosTerminal $terminal,
        SyncSaleDTO $sale,
        Carbon $serverReceivedTime,
        int $clockSkewThreshold,
        ?Warehouse $warehouse,
    ): array {
        $tenantId = $terminal->tenant_id;
        $companyId = $terminal->company_id;

        // 1. Idempotency Check (ON CONFLICT DO NOTHING semantics)
        /** @var SalesInvoice|null $existing */
        $existing = SalesInvoice::query()
            ->where('tenant_id', $tenantId)
            ->where('terminal_id', $terminal->id)
            ->where('client_txn_id', $sale->clientTxnId)
            ->first();

        if ($existing) {
            // Fiscal enrichment on existing issued invoice if SDC data arrives in later sync
            if ($existing->status === 'issued' &&
                (($existing->fiscal_signature === null && $sale->fiscalSignature !== null) ||
                 ($existing->sdc_invoice_number === null && $sale->sdcInvoiceNumber !== null))) {
                DB::table('sales_invoices')
                    ->where('id', $existing->id)
                    ->update([
                        'fiscal_signature' => $sale->fiscalSignature ?? $existing->fiscal_signature,
                        'sdc_invoice_number' => $sale->sdcInvoiceNumber ?? $existing->sdc_invoice_number,
                    ]);
            }

            return [
                'client_txn_id' => $sale->clientTxnId,
                'status' => 'already_synced',
                'invoice_id' => $existing->id,
                'invoice_number' => $existing->invoice_number,
            ];
        }

        // 2. Dual timestamps and clock skew detection
        $deviceTime = $sale->deviceTime;
        $skewSeconds = (int) abs($serverReceivedTime->diffInSeconds($deviceTime));
        $isClockSkewFlagged = $skewSeconds > $clockSkewThreshold;

        // 3. Resolve line items & variants
        $lines = [];
        $variantsMap = [];

        foreach ($sale->items as $item) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()
                ->with(['product', 'taxCode'])
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('id', $item->variantId)
                ->first();

            if (! $variant) {
                throw new InvalidArgumentException("Product variant [{$item->variantId}] not found.");
            }

            $variantsMap[$variant->id] = $variant;

            // Resolve TaxCode
            $taxCode = null;
            if ($item->taxCodeId) {
                $taxCode = TaxCode::query()
                    ->where('tenant_id', $tenantId)
                    ->where('company_id', $companyId)
                    ->where('id', $item->taxCodeId)
                    ->first();
            }
            $taxCode = $taxCode ?? $variant->taxCode;

            $lines[] = new InvoiceLineItemDTO(
                variant: $variant,
                itemDescription: $variant->name_en ?? $variant->product->name_en ?? 'POS Item',
                quantity: $item->quantity,
                unitPrice: $item->unitPrice,
                taxCode: $taxCode,
                discount: $item->discountAmount,
                uom: $variant->base_uom ?? 'Pcs',
                vatRatePercent: $item->flatTaxRate,
            );
        }

        // 4. Resolve customer
        $customer = null;
        if ($sale->customerId) {
            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->where('id', $sale->customerId)
                ->first();
        }

        // Resolve poster user
        $postedBy = auth()->id() ?? User::where('tenant_id', $tenantId)->value('id') ?? (string) Str::uuid();

        // 5. Issue invoice through InvoiceIssuer (NEW-27: draft -> lines -> issued atomically with GL posting)
        $issueRequest = new IssueInvoiceRequest(
            company: $terminal->company ?? $terminal->company_id,
            branch: $terminal->branch ?? $terminal->branch_id,
            postedBy: $postedBy,
            lines: $lines,
            customer: $customer,
            terminal: $terminal,
            clientTxnId: $sale->clientTxnId,
            invoiceDate: $deviceTime,
            businessDate: $deviceTime,
            pricingBasis: $sale->pricingBasis,
            isSyncedFromOffline: true,
            paymentStatus: $sale->paymentStatus,
            deviceTime: $deviceTime,
            serverReceivedTime: $serverReceivedTime,
            clockSkewSeconds: $skewSeconds,
            isClockSkewFlagged: $isClockSkewFlagged,
        );

        $invoice = $this->invoiceIssuer->issue($issueRequest);

        // 6. Record Stock Deduction & Oversell (ADR-016, NEW-26)
        if ($this->oversellService && $warehouse) {
            foreach ($sale->items as $item) {
                $variant = $variantsMap[$item->variantId] ?? null;
                $unitCost = $variant ? (string) $variant->cost_price : '0.0000';

                $this->oversellService->recordOfflineSale(
                    companyId: $companyId,
                    warehouseId: $warehouse->id,
                    productVariantId: $item->variantId,
                    quantity: $item->quantity,
                    unitCost: $unitCost,
                    salesInvoiceId: $invoice->id,
                    branchId: $terminal->branch_id,
                );
            }
        }

        // 7. Fiscal Enrichment Path (Trigger 4 compliant)
        if ($sale->fiscalSignature !== null || $sale->sdcInvoiceNumber !== null) {
            DB::table('sales_invoices')
                ->where('id', $invoice->id)
                ->update([
                    'fiscal_signature' => $sale->fiscalSignature,
                    'sdc_invoice_number' => $sale->sdcInvoiceNumber,
                ]);
        }

        return [
            'client_txn_id' => $sale->clientTxnId,
            'status' => 'committed',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'clock_skew_flagged' => $isClockSkewFlagged,
        ];
    }
}
