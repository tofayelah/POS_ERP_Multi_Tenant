@php
    $isBn = $lang === 'bn';
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <title>{{ $isBn ? 'মূসক-৬.৭ ক্রেডিট নোট' : 'Mushak-6.7 Credit Note' }} - {{ $creditNote->credit_note_number }}</title>
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
        .signatures { width: 100%; margin-top: 50px; }
        .signatures td { width: 50%; text-align: center; padding-top: 40px; border-top: 1px dashed #777; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $isBn ? 'গণপ্রজাতন্ত্রী বাংলাদেশ সরকার' : 'Government of the People\'s Republic of Bangladesh' }}</h1>
        <h2>{{ $isBn ? 'জাতীয় রাজস্ব বোর্ড' : 'National Board of Revenue' }}</h2>
        <p><strong>{{ $isBn ? 'ক্রেডিট নোট' : 'CREDIT NOTE' }}</strong></p>
        <p>[{{ $isBn ? 'বিধি ৪০ এর উপ-বিধি (১) এর দফা (ঙ) দ্রষ্টব্য' : 'Under Rule 40(1)(e) of VAT & SD Rules, 2016' }}]</p>
        <p><strong>{{ $isBn ? 'ফরম মূসক-৬.৭' : 'Mushak-6.7' }}</strong></p>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <strong>{{ $isBn ? 'নিবন্ধিত ব্যক্তির নাম' : 'Company Name' }}:</strong> {{ $company->legal_name }}<br>
                <strong>{{ $isBn ? 'বিআইএন (BIN)' : 'BIN' }}:</strong> {{ $company->bin ?? '001234567-0101' }}<br>
                <strong>{{ $isBn ? 'শাখা' : 'Branch' }}:</strong> {{ $branch->name }}
            </td>
            <td style="width: 40%;">
                <strong>{{ $isBn ? 'ক্রেডিট নোট নম্বর' : 'Credit Note No' }}:</strong> {{ $creditNote->credit_note_number }}<br>
                <strong>{{ $isBn ? 'তারিখ' : 'Date' }}:</strong> {{ $creditNote->credit_note_date }}<br>
                <strong>{{ $isBn ? 'মূল চালানপত্র নম্বর' : 'Original Invoice No' }}:</strong> {{ $invoice->invoice_number }}<br>
                <strong>{{ $isBn ? 'মূল চালানের তারিখ' : 'Original Invoice Date' }}:</strong> {{ $invoice->invoice_date }}
            </td>
        </tr>
        <tr>
            <td colspan="2" style="padding-top: 8px; border-top: 1px solid #ccc;">
                <strong>{{ $isBn ? 'ক্রেডিট নোট ইস্যুর কারণ' : 'Reason for Issue' }}:</strong> {{ $creditNote->reason }}
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>{{ $isBn ? 'ক্রম' : 'SL' }}</th>
                <th>{{ $isBn ? 'বিবরণ' : 'Item Description' }}</th>
                <th>{{ $isBn ? 'ফেরত পরিমাণ' : 'Returned Qty' }}</th>
                <th>{{ $isBn ? 'একক মূল্য' : 'Unit Price' }}</th>
                <th>{{ $isBn ? 'মূল্য (কর ব্যতীত)' : 'Net Amount' }}</th>
                <th>{{ $isBn ? 'সম্পূরক শুল্ক' : 'SD' }}</th>
                <th>{{ $isBn ? 'হ্রাসকারী মূসক' : 'Deductive VAT' }}</th>
                <th>{{ $isBn ? 'মোট সমন্বয়' : 'Total Refund' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($creditNote->lines as $index => $line)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $line->item_description }}</td>
                <td class="text-right">{{ number_format((float)$line->quantity, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->unit_price, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->subtotal, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->supplementary_duty_amount, 2) }}</td>
                <td class="text-right">{{ number_format((float)$line->tax_amount, 2) }} ({{ (float)$line->vat_rate }}%)</td>
                <td class="text-right">{{ number_format((float)$line->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background: #f9f9f9; font-weight: bold;">
                <td colspan="4" class="text-right">{{ $isBn ? 'মোট সমন্বয়কৃত টাকা' : 'Total Reversal' }}:</td>
                <td class="text-right">{{ number_format((float)$creditNote->subtotal, 2) }}</td>
                <td class="text-right">{{ number_format((float)$creditNote->supplementary_duty_amount, 2) }}</td>
                <td class="text-right">{{ number_format((float)$creditNote->tax_amount, 2) }}</td>
                <td class="text-right">৳{{ number_format((float)$creditNote->total_amount, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="signatures">
        <tr>
            <td>{{ $isBn ? 'গ্রহীতার স্বাক্ষর' : 'Customer\'s Signature' }}</td>
            <td>{{ $isBn ? 'দায়িত্বপ্রাপ্ত কর্মকর্তার স্বাক্ষর' : 'Authorized Officer Signature' }}</td>
        </tr>
    </table>
</body>
</html>
