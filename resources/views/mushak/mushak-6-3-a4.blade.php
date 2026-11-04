@php
    $isBn = $lang === 'bn';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $isBn ? 'মূসক-৬.৩ কর চালানপত্র' : 'Mushak-6.3 Tax Invoice' }} - {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #111; margin: 20px; line-height: 1.4; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #222; padding-bottom: 10px; }
        .header h1 { font-size: 16px; margin: 0; font-weight: bold; }
        .header h2 { font-size: 13px; margin: 4px 0; }
        .header p { font-size: 11px; margin: 2px 0; }
        .meta-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .meta-table td { padding: 4px; vertical-align: top; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .items-table th, .items-table td { border: 1px solid #444; padding: 6px; text-align: left; }
        .items-table th { background: #f2f2f2; font-weight: bold; text-align: center; font-size: 11px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; }
        .signatures { width: 100%; margin-top: 50px; }
        .signatures td { width: 50%; text-align: center; padding-top: 40px; border-top: 1px dashed #777; }
        .notice { font-size: 10px; color: #555; text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $isBn ? 'গণপ্রজাতন্ত্রী বাংলাদেশ সরকার' : 'Government of the People\'s Republic of Bangladesh' }}</h1>
        <h2>{{ $isBn ? 'জাতীয় রাজস্ব বোর্ড' : 'National Board of Revenue' }}</h2>
        <p><strong>{{ $isBn ? 'কর চালানপত্র' : 'TAX INVOICE' }}</strong></p>
        <p>[{{ $isBn ? 'বিধি ৪০ এর উপ-বিধি (১) এর দফা (গ) ও দফা (চ) দ্রষ্টব্য' : 'Under Rule 40(1)(c) and (f) of VAT & SD Rules, 2016' }}]</p>
        <p><strong>{{ $isBn ? 'ফরম মূসক-৬.৩' : 'Mushak-6.3' }}</strong></p>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <strong>{{ $isBn ? 'নিবন্ধিত ব্যক্তির নাম' : 'Registered Person Name' }}:</strong> {{ $company->legal_name }}<br>
                <strong>{{ $isBn ? 'বিআইএন (BIN)' : 'BIN' }}:</strong> {{ $company->bin ?? '001234567-0101' }}<br>
                <strong>{{ $isBn ? 'শাখা / আউটলেট' : 'Branch / Outlet' }}:</strong> {{ $branch->name }} ({{ $branch->code }})<br>
                <strong>{{ $isBn ? 'চালান ইস্যুর ঠিকানা' : 'Address' }}:</strong> {{ $branch->address ?? 'Dhaka, Bangladesh' }}
            </td>
            <td style="width: 40%;">
                <strong>{{ $isBn ? 'চালানপত্র নম্বর' : 'Invoice No' }}:</strong> {{ $invoice->invoice_number }}<br>
                @if($invoice->sdc_invoice_number)
                <strong>{{ $isBn ? 'এসডিসি নম্বর' : 'SDC No' }}:</strong> {{ $invoice->sdc_invoice_number }}<br>
                @endif
                <strong>{{ $isBn ? 'ইস্যুর তারিখ' : 'Invoice Date' }}:</strong> {{ $invoice->invoice_date }}<br>
                <strong>{{ $isBn ? 'ইস্যুর সময়' : 'Issue Time' }}:</strong> {{ $invoice->created_at->format('H:i:s') }}
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top: 10px; border-top: 1px solid #ccc;">
                <strong>{{ $isBn ? 'ক্রেতার নাম' : 'Buyer Name' }}:</strong> {{ $customer ? $customer->name : ($isBn ? 'খুচরা গ্রাহক' : 'Retail Customer') }} |
                <strong>{{ $isBn ? 'ক্রেতার বিআইএন' : 'Buyer BIN' }}:</strong> {{ $customer && $customer->customer_bin ? $customer->customer_bin : ($isBn ? 'প্রযোজ্য নয়' : 'N/A') }} |
                <strong>{{ $isBn ? 'মোবাইল' : 'Phone' }}:</strong> {{ $customer && $customer->phone ? $customer->phone : ($isBn ? 'প্রযোজ্য নয়' : 'N/A') }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">{{ $isBn ? 'ক্রম' : 'SL' }}</th>
                <th style="width: 35%;">{{ $isBn ? 'পণ্য বা সেবার বিবরণ' : 'Item Description' }}</th>
                <th style="width: 10%;">{{ $isBn ? 'একক' : 'UOM' }}</th>
                <th style="width: 10%;">{{ $isBn ? 'পরিমাণ' : 'Qty' }}</th>
                <th style="width: 12%;">{{ $isBn ? 'একক মূল্য (৳)' : 'Unit Price' }}</th>
                <th style="width: 13%;">{{ $isBn ? 'মোট মূল্য (৳)' : 'Total Price' }}</th>
                <th style="width: 10%;">{{ $isBn ? 'সম্পূরক শুল্ক (৳)' : 'SD (৳)' }}</th>
                <th style="width: 10%;">{{ $isBn ? 'মূল্য সংযোজন কর (৳)' : 'VAT (৳)' }}</th>
                <th style="width: 15%;">{{ $isBn ? 'সর্বমোট মূল্য (৳)' : 'Gross (৳)' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->lines as $index => $line)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>
                    {{ $line->item_description }}
                    @if($line->batch_number)
                    <br><small style="color: #666;">{{ $isBn ? 'ব্যাচ' : 'Batch' }}: {{ $line->batch_number }}</small>
                    @endif
                </td>
                <td class="text-center">{{ $line->uom }}</td>
                <td class="text-right">{{ number_format((float)$line->quantity, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->line_total, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->supplementary_duty_amount, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->tax_amount, 2) }} ({{ (float)$line->vat_rate }}%)</td>
                <td class="text-right">{{ number_format((float)$line->line_total + (float)$line->tax_amount + (float)$line->supplementary_duty_amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-right"><strong>{{ $isBn ? 'মোট' : 'Subtotal' }}:</strong></td>
                <td class="text-right"><strong>{{ number_format((float)$invoice->subtotal, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format((float)$invoice->supplementary_duty_amount, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format((float)$invoice->tax_amount, 2) }}</strong></td>
                <td class="text-right"><strong>{{ number_format((float)$invoice->subtotal + (float)$invoice->tax_amount + (float)$invoice->supplementary_duty_amount, 2) }}</strong></td>
            </tr>
            @if((float)$invoice->discount_amount > 0)
            <tr>
                <td colspan="8" class="text-right">{{ $isBn ? 'ছাড় (Discount)' : 'Discount' }}:</td>
                <td class="text-right">-{{ number_format((float)$invoice->discount_amount, 2) }}</td>
            </tr>
            @endif
            <tr style="background: #f9f9f9; font-size: 13px;">
                <td colspan="8" class="text-right"><strong>{{ $isBn ? 'সর্বমোট প্রদেয় টাকা (Net Payable)' : 'Total Payable' }}:</strong></td>
                <td class="text-right"><strong>৳{{ number_format((float)$invoice->total_payable, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if($invoice->fiscal_signature)
    <div style="margin: 15px 0; padding: 6px; border: 1px dashed #555; font-family: monospace; font-size: 10px;">
        <strong>{{ $isBn ? 'ডিজিটাল স্বাক্ষর (Fiscal Signature)' : 'Fiscal Signature' }}:</strong> {{ $invoice->fiscal_signature }}
    </div>
    @endif

    <table class="signatures">
        <tr>
            <td>{{ $isBn ? 'ক্রেতার স্বাক্ষর' : 'Buyer\'s Signature' }}</td>
            <td>{{ $isBn ? 'কর্তৃপক্ষের স্বাক্ষর' : 'Authorized Signature' }}</td>
        </tr>
    </table>

    <div class="notice">
        {{ $isBn ? 'মূল্য সংযোজন কর ও সম্পূরক শুল্ক আইন, ২০১২ এর অধীনে এটি একটি বৈধ কর চালানপত্র।' : 'This is a statutory tax invoice issued under the Bangladesh VAT and Supplementary Duty Act, 2012.' }}
    </div>
</body>
</html>
