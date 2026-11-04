<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 2rem;">
        {{-- Receivables (AR) Aging Section --}}
        <div style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.125rem; font-weight: 700; color: #1e293b; margin: 0;">Customer Receivables (AR) Aging</h3>
                    <p style="font-size: 0.875rem; color: #64748b; margin: 0.25rem 0 0 0;">Open subledger items categorized by overdue intervals</p>
                </div>
                @if($arAging)
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 700; font-family: monospace;">
                        Total AR Due: ৳ {{ $arAging->summary->total->getAmount() }}
                    </div>
                @endif
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569;">Customer Name</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">0 - 30 Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">31 - 60 Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">61 - 90 Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">91 - 120 Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">120+ Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: #1e293b; text-align: right;">Total Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($arAging && count($arAging->details) > 0)
                            @foreach($arAging->details as $row)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem 1rem; font-weight: 500; color: #1e293b;">{{ $row->partyName }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->days0To30->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->days31To60->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->days61To90->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->days91To120->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->daysOver120->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; font-weight: 700; color: #0f172a;">৳ {{ $row->buckets->total->getAmount() }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="7" style="padding: 2rem 1rem; text-align: center; color: #94a3b8;">
                                    No outstanding customer receivables open items.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Payables (AP) Aging Section --}}
        <div style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h3 style="font-size: 1.125rem; font-weight: 700; color: #1e293b; margin: 0;">Supplier Payables (AP) Aging</h3>
                    <p style="font-size: 0.875rem; color: #64748b; margin: 0.25rem 0 0 0;">Open subledger items categorized by invoice due date overdue intervals</p>
                </div>
                @if($apAging)
                    <div style="background: #fffbeb; border: 1px solid #fde68a; color: #b45309; padding: 0.5rem 1rem; border-radius: 0.5rem; font-weight: 700; font-family: monospace;">
                        Total AP Due: ৳ {{ $apAging->summary->total->getAmount() }}
                    </div>
                @endif
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569;">Supplier Name</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">0 - 30 Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">31 - 60 Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">61 - 90 Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">91 - 120 Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 600; color: #475569; text-align: right;">120+ Days</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; color: #1e293b; text-align: right;">Total Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if($apAging && count($apAging->details) > 0)
                            @foreach($apAging->details as $row)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem 1rem; font-weight: 500; color: #1e293b;">{{ $row->partyName }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->days0To30->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->days31To60->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->days61To90->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->days91To120->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; color: #334155;">৳ {{ $row->buckets->daysOver120->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1rem; text-align: right; font-family: monospace; font-weight: 700; color: #0f172a;">৳ {{ $row->buckets->total->getAmount() }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="7" style="padding: 2rem 1rem; text-align: center; color: #94a3b8;">
                                    No outstanding supplier payables open items.
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
