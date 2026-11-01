@php
    $isBn = $lang === 'bn';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }} (80mm)</title>
    <!-- Format: Mushak 6.3 80mm POS Thermal Receipt -->
    <style>
        body { width: 72mm; margin: 0 auto; padding: 4mm; font-family: 'Courier New', monospace; font-size: 11px; line-height: 1.25; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 4px 0; }
        .row { display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; margin: 4px 0; }
        th, td { padding: 2px 0; font-size: 10px; }
        th { text-align: left; border-bottom: 1px dashed #000; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="center bold" style="font-size: 13px;">{{ $company->legal_name }}</div>
    <div class="center">{{ $branch->name }}</div>
    <div class="center">{{ $branch->address ?? 'Dhaka, Bangladesh' }}</div>
    <div class="center">BIN: {{ $company->bin ?? '001234567-0101' }}</div>
    <div class="center bold">{{ $isBn ? 'কর চালানপত্র (মূসক-৬.৩)' : 'TAX INVOICE (MUSHAK-6.3)' }}</div>
    <div class="divider"></div>

    <div class="row"><span>{{ $isBn ? 'চালান নম্বর' : 'Inv No' }}:</span><span class="bold">{{ $invoice->invoice_number }}</span></div>
    @if($invoice->sdc_invoice_number)
    <div class="row"><span>SDC No:</span><span>{{ $invoice->sdc_invoice_number }}</span></div>
    @endif
    <div class="row"><span>{{ $isBn ? 'তারিখ' : 'Date' }}:</span><span>{{ $invoice->invoice_date }} {{ $invoice->created_at->format('H:i') }}</span></div>
    @if($invoice->terminal)
    <div class="row"><span>Terminal:</span><span>{{ $invoice->terminal->terminal_code }}</span></div>
    @endif
    <div class="row"><span>{{ $isBn ? 'ক্রেতা' : 'Customer' }}:</span><span>{{ $customer ? $customer->name : ($isBn ? 'খুচরা গ্রাহক' : 'Walk-in') }}</span></div>
    <div class="divider"></div>

    <table>
        <thead>
            <tr>
                <th>{{ $isBn ? 'বিবরণ' : 'Item' }}</th>
                <th class="text-right">{{ $isBn ? 'পরিমাণ' : 'Qty' }}</th>
                <th class="text-right">{{ $isBn ? 'মূল্য' : 'Price' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $line)
            <tr>
                <td colspan="3" class="bold">{{ $line->item_description }}</td>
            </tr>
            <tr>
                <td style="padding-left: 6px; color: #333;">VAT {{ (float)$line->vat_rate }}%</td>
                <td class="text-right">{{ (float)$line->quantity }}</td>
                <td class="text-right">৳{{ number_format((float)$line->line_total + (float)$line->tax_amount + (float)$line->supplementary_duty_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>
    <div class="row"><span>{{ $isBn ? 'মোট মূল্য' : 'Subtotal' }}:</span><span>৳{{ number_format((float)$invoice->subtotal, 2) }}</span></div>
    @if((float)$invoice->discount_amount > 0)
    <div class="row"><span>{{ $isBn ? 'ছাড়' : 'Discount' }}:</span><span>-৳{{ number_format((float)$invoice->discount_amount, 2) }}</span></div>
    @endif
    @if((float)$invoice->supplementary_duty_amount > 0)
    <div class="row"><span>{{ $isBn ? 'সম্পূরক শুল্ক' : 'SD Total' }}:</span><span>৳{{ number_format((float)$invoice->supplementary_duty_amount, 2) }}</span></div>
    @endif
    <div class="row"><span>{{ $isBn ? 'মূসক' : 'VAT Total' }}:</span><span>৳{{ number_format((float)$invoice->tax_amount, 2) }}</span></div>
    <div class="divider"></div>
    <div class="row bold" style="font-size: 13px;"><span>{{ $isBn ? 'সর্বমোট' : 'TOTAL PAYABLE' }}:</span><span>৳{{ number_format((float)$invoice->total_payable, 2) }}</span></div>
    <div class="divider"></div>

    @if($invoice->fiscal_signature)
    <div class="center" style="font-size: 9px; word-break: break-all; margin: 4px 0;">
        SIG: {{ substr($invoice->fiscal_signature, 0, 32) }}...
    </div>
    @endif

    <div class="center" style="font-size: 10px; margin-top: 6px;">
        {{ $isBn ? 'ধন্যবাদ, আবার আসবেন!' : 'Thank you for shopping with us!' }}
    </div>
</body>
</html>
