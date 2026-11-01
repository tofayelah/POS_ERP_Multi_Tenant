<?php

namespace App\Modules\Tax\Services;

use App\Models\AccountingPeriod;
use App\Models\CreditNote;
use App\Models\JournalLine;
use App\Models\SalesInvoice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\View;

class MushakReportService
{
    /**
     * Render Mushak 6.3 Tax Invoice in A4 or 80mm format, English or Bangla.
     */
    public function renderMushak63(SalesInvoice $invoice, string $format = 'a4', string $lang = 'en'): string
    {
        $invoice->loadMissing(['company', 'branch', 'customer', 'lines', 'terminal']);

        $viewName = ($format === '80mm' || $format === 'pos_80mm')
            ? 'mushak.mushak-6-3-80mm'
            : 'mushak.mushak-6-3-a4';

        return View::make($viewName, [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'branch' => $invoice->branch,
            'customer' => $invoice->customer,
            'lang' => $lang,
        ])->render();
    }

    /**
     * Render Mushak 6.7 Credit Note.
     */
    public function renderMushak67CreditNote(CreditNote $creditNote, string $lang = 'bn'): string
    {
        $creditNote->loadMissing(['company', 'branch', 'salesInvoice', 'customer', 'lines']);

        return View::make('mushak.mushak-6-7', [
            'creditNote' => $creditNote,
            'invoice' => $creditNote->salesInvoice,
            'company' => $creditNote->company,
            'branch' => $creditNote->branch,
            'customer' => $creditNote->customer,
            'lang' => $lang,
        ])->render();
    }

    /**
     * Render Mushak 6.6 VDS Certificate (উৎস কর্তন সনদপত্র).
     *
     * @param  array{certificate_number: string, date: string, supplier_name: string, supplier_bin: string, invoice_number: string, invoice_date: string, total_value: string, vat_amount: string, vds_amount: string}  $vdsData
     */
    public function renderMushak66Vds(array $vdsData, string $lang = 'bn'): string
    {
        $isBn = $lang === 'bn';
        $title = $isBn ? 'উৎসে কর কর্তন সনদপত্র (ফরম মূসক-৬.৬)' : 'Certificate of Tax Deduction at Source (Mushak-6.6)';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><title>{$title}</title></head>
        <body style="font-family: Arial, sans-serif; margin: 30px; line-height: 1.5;">
            <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px;">
                <h2 style="margin: 0;">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার - জাতীয় রাজস্ব বোর্ড</h2>
                <h3>{$title}</h3>
                <p>[বিধি ৪০ এর উপ-বিধি (১) এর দফা (ঘ) দ্রষ্টব্য]</p>
            </div>
            <div style="margin-top: 20px;">
                <p><strong>সনদপত্র নম্বর:</strong> {$vdsData['certificate_number']} | <strong>তারিখ:</strong> {$vdsData['date']}</p>
                <p><strong>সরবরাহকারীর নাম:</strong> {$vdsData['supplier_name']} | <strong>বিআইএন:</strong> {$vdsData['supplier_bin']}</p>
                <p><strong>চালানপত্র নম্বর:</strong> {$vdsData['invoice_number']} | <strong>তারিখ:</strong> {$vdsData['invoice_date']}</p>
                <p><strong>মোট মূল্য:</strong> ৳{$vdsData['total_value']} | <strong>মূসকের পরিমাণ:</strong> ৳{$vdsData['vat_amount']} | <strong>কর্তনকৃত মূসক (VDS):</strong> ৳{$vdsData['vds_amount']}</p>
            </div>
            <div style="margin-top: 60px; border-top: 1px dashed #666; width: 250px; text-align: center;">
                দায়িত্বপ্রাপ্ত কর্মকর্তার স্বাক্ষর ও সিল
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Render Mushak 6.8 Debit Note (ডেবিট নোট).
     *
     * @param  array{debit_note_number: string, date: string, original_invoice_number: string, reason: string, additional_subtotal: string, additional_vat: string, total_payable: string}  $data
     */
    public function renderMushak68DebitNote(array $data, string $lang = 'bn'): string
    {
        $isBn = $lang === 'bn';
        $title = $isBn ? 'ডেবিট নোট (ফরম মূসক-৬.৮)' : 'Debit Note (Mushak-6.8)';

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><meta charset="utf-8"><title>{$title}</title></head>
        <body style="font-family: Arial, sans-serif; margin: 30px; line-height: 1.5;">
            <div style="text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px;">
                <h2 style="margin: 0;">গণপ্রজাতন্ত্রী বাংলাদেশ সরকার - জাতীয় রাজস্ব বোর্ড</h2>
                <h3>{$title}</h3>
                <p>[বিধি ৪০ এর উপ-বিধি (১) এর দফা (ঙ) দ্রষ্টব্য]</p>
            </div>
            <div style="margin-top: 20px;">
                <p><strong>ডেবিট নোট নম্বর:</strong> {$data['debit_note_number']} | <strong>তারিখ:</strong> {$data['date']}</p>
                <p><strong>মূল চালানপত্র নম্বর:</strong> {$data['original_invoice_number']}</p>
                <p><strong>কারণ:</strong> {$data['reason']}</p>
                <p><strong>অতিরিক্ত মূল্য:</strong> ৳{$data['additional_subtotal']} | <strong>অতিরিক্ত মূসক:</strong> ৳{$data['additional_vat']}</p>
                <p><strong>মোট প্রদেয় সমন্বয়:</strong> ৳{$data['total_payable']}</p>
            </div>
            <div style="margin-top: 60px; border-top: 1px dashed #666; width: 250px; text-align: center;">
                দায়িত্বপ্রাপ্ত কর্মকর্তার স্বাক্ষর
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Mushak 6.2: Sales Register (বিক্রয় হিসাব পুস্তক).
     *
     * @return array{entries: array<int, array<string, mixed>>, subtotal: string, supplementary_duty_total: string, vat_total: string, grand_total: string}
     */
    public function getMushak62SalesBook(string $companyId, CarbonInterface|string $fromDate, CarbonInterface|string $toDate): array
    {
        $from = $fromDate instanceof CarbonInterface ? $fromDate->toDateString() : Carbon::parse($fromDate)->toDateString();
        $to = $toDate instanceof CarbonInterface ? $toDate->toDateString() : Carbon::parse($toDate)->toDateString();

        $invoices = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'issued')
            ->whereBetween('invoice_date', [$from, $to])
            ->with(['customer', 'lines'])
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get();

        $entries = [];
        $totalSubtotal = 0.0;
        $totalSd = 0.0;
        $totalVat = 0.0;
        $totalGrand = 0.0;

        foreach ($invoices as $inv) {
            $sub = (float) $inv->subtotal;
            $sd = (float) $inv->supplementary_duty_amount;
            $vat = (float) $inv->tax_amount;
            $grand = (float) $inv->total_payable;

            $totalSubtotal += $sub;
            $totalSd += $sd;
            $totalVat += $vat;
            $totalGrand += $grand;

            $entries[] = [
                'id' => $inv->id,
                'invoice_date' => $inv->invoice_date,
                'invoice_number' => $inv->invoice_number,
                'sdc_invoice_number' => $inv->sdc_invoice_number,
                'buyer_name' => $inv->customer ? $inv->customer->name : 'Walk-in Customer',
                'buyer_bin' => $inv->customer?->customer_bin,
                'subtotal' => number_format($sub, 2, '.', ''),
                'supplementary_duty_amount' => number_format($sd, 2, '.', ''),
                'tax_amount' => number_format($vat, 2, '.', ''),
                'total_payable' => number_format($grand, 2, '.', ''),
                'lines_count' => $inv->lines->count(),
            ];
        }

        // Account for Credit Notes (Mushak 6.7) issued in this period
        $creditNotes = CreditNote::query()
            ->where('company_id', $companyId)
            ->where('status', 'issued')
            ->whereBetween('credit_note_date', [$from, $to])
            ->with(['customer', 'lines'])
            ->orderBy('credit_note_date')
            ->orderBy('credit_note_number')
            ->get();

        foreach ($creditNotes as $cn) {
            $sub = -(float) $cn->subtotal;
            $sd = -(float) $cn->supplementary_duty_amount;
            $vat = -(float) $cn->tax_amount;
            $grand = -(float) $cn->total_amount;

            $totalSubtotal += $sub;
            $totalSd += $sd;
            $totalVat += $vat;
            $totalGrand += $grand;

            $entries[] = [
                'id' => $cn->id,
                'invoice_date' => $cn->credit_note_date,
                'invoice_number' => $cn->credit_note_number.' (CN)',
                'sdc_invoice_number' => null,
                'buyer_name' => $cn->customer ? $cn->customer->name : 'Walk-in Customer',
                'buyer_bin' => $cn->customer?->customer_bin,
                'subtotal' => number_format($sub, 2, '.', ''),
                'supplementary_duty_amount' => number_format($sd, 2, '.', ''),
                'tax_amount' => number_format($vat, 2, '.', ''),
                'total_payable' => number_format($grand, 2, '.', ''),
                'lines_count' => $cn->lines->count(),
            ];
        }

        return [
            'entries' => $entries,
            'subtotal' => number_format($totalSubtotal, 2, '.', ''),
            'supplementary_duty_total' => number_format($totalSd, 2, '.', ''),
            'vat_total' => number_format($totalVat, 2, '.', ''),
            'grand_total' => number_format($totalGrand, 2, '.', ''),
        ];
    }

    /**
     * Mushak 6.1: Purchase Register (ক্রয় হিসাব পুস্তক).
     *
     * @return array{entries: array<int, array<string, mixed>>, total_purchase_value: string, total_input_vat: string}
     */
    public function getMushak61PurchaseBook(string $companyId, CarbonInterface|string $fromDate, CarbonInterface|string $toDate): array
    {
        // Purchase Book mock / structure for statutory M3 reporting
        return [
            'entries' => [],
            'total_purchase_value' => '0.00',
            'total_input_vat' => '0.00',
        ];
    }

    /**
     * Mushak 9.1 Monthly VAT Return Summary Figures (Design §1.3, §8.2(8)).
     *
     * @return array{period: string, standard_vat_sales: string, standard_vat_amount: string, truncated_vat_sales: string, truncated_vat_amount: string, exempt_sales: string, supplementary_duty_amount: string, input_tax_rebate: string, net_vat_payable: string}
     */
    public function getMushak91MonthlyFigures(string $companyId, CarbonInterface|string $month): array
    {
        $monthDate = $month instanceof CarbonInterface ? $month->copy() : Carbon::parse($month);
        $startDate = $monthDate->copy()->startOfMonth()->toDateString();
        $endDate = $monthDate->copy()->endOfMonth()->toDateString();

        $invoices = SalesInvoice::query()
            ->where('company_id', $companyId)
            ->where('status', 'issued')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->with('lines')
            ->get();

        $stdSales = 0.0;
        $stdVat = 0.0;
        $truncSales = 0.0;
        $truncVat = 0.0;
        $exemptSales = 0.0;
        $totalSd = 0.0;

        foreach ($invoices as $inv) {
            $totalSd += (float) $inv->supplementary_duty_amount;

            foreach ($inv->lines as $line) {
                $rate = (float) $line->vat_rate;
                $lineTotal = (float) $line->line_total;
                $lineTax = (float) $line->tax_amount;

                if ($rate === 15.0) {
                    $stdSales += $lineTotal;
                    $stdVat += $lineTax;
                } elseif ($rate > 0.0) {
                    $truncSales += $lineTotal;
                    $truncVat += $lineTax;
                } else {
                    $exemptSales += $lineTotal;
                }
            }
        }

        $inputTaxRebate = 0.00;
        $netPayable = ($stdVat + $truncVat) - $inputTaxRebate;

        return [
            'period' => $monthDate->format('F Y'),
            'standard_vat_sales' => number_format($stdSales, 2, '.', ''),
            'standard_vat_amount' => number_format($stdVat, 2, '.', ''),
            'truncated_vat_sales' => number_format($truncSales, 2, '.', ''),
            'truncated_vat_amount' => number_format($truncVat, 2, '.', ''),
            'exempt_sales' => number_format($exemptSales, 2, '.', ''),
            'supplementary_duty_amount' => number_format($totalSd, 2, '.', ''),
            'input_tax_rebate' => number_format($inputTaxRebate, 2, '.', ''),
            'net_vat_payable' => number_format($netPayable, 2, '.', ''),
        ];
    }

    /**
     * Assert exact mathematical reconciliation between Mushak 6.2 Sales Book VAT total
     * and the general ledger journal balance for VAT Payable account in that period.
     *
     * @return array{reconciled: bool, register_vat: string, gl_vat: string, difference: string}
     */
    public function reconcileRegisterVatWithGl(string $companyId, string $periodId, string $vatAccountId): array
    {
        /** @var AccountingPeriod $period */
        $period = AccountingPeriod::findOrFail($periodId);

        $salesBook = $this->getMushak62SalesBook($companyId, $period->start_date, $period->end_date);
        $registerVat = $salesBook['vat_total'];

        // Compute net credits to the VAT Payable account in this period
        $journalLines = JournalLine::query()
            ->where('company_id', $companyId)
            ->where('account_id', $vatAccountId)
            ->whereHas('journalEntry', function ($q) use ($periodId) {
                $q->where('period_id', $periodId);
            })
            ->get();

        $totalCredits = 0.0;
        $totalDebits = 0.0;

        foreach ($journalLines as $line) {
            $totalCredits += (float) $line->credit_amount;
            $totalDebits += (float) $line->debit_amount;
        }

        // Net VAT posted from sales = Total Credits - Total Debits (reversals from credit notes)
        $netGlVat = $totalCredits - $totalDebits;
        $formattedGlVat = number_format($netGlVat, 2, '.', '');

        $diff = (float) $registerVat - $netGlVat;
        $isReconciled = abs($diff) < 0.001;

        return [
            'reconciled' => $isReconciled,
            'register_vat' => $registerVat,
            'gl_vat' => $formattedGlVat,
            'difference' => number_format($diff, 2, '.', ''),
        ];
    }
}
