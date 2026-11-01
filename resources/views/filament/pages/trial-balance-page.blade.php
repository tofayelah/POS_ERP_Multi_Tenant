<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        @if($report)
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <div style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <p style="font-size: 0.875rem; font-weight: 500; color: #64748b; margin: 0;">Total Debit Amount</p>
                    <p style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0.5rem 0 0 0; font-family: monospace;">৳ {{ $report->totalDebit->getAmount() }}</p>
                </div>
                <div style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <p style="font-size: 0.875rem; font-weight: 500; color: #64748b; margin: 0;">Total Credit Amount</p>
                    <p style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0.5rem 0 0 0; font-family: monospace;">৳ {{ $report->totalCredit->getAmount() }}</p>
                </div>
                <div style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <p style="font-size: 0.875rem; font-weight: 500; color: #64748b; margin: 0;">Ledger Balance Status</p>
                    <div style="margin-top: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        @if($report->isBalanced)
                            <span style="display: inline-flex; align-items: center; border-radius: 9999px; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; color: #047857;">
                                ✓ Strictly Balanced (Debits = Credits)
                            </span>
                        @else
                            <span style="display: inline-flex; align-items: center; border-radius: 9999px; background: #fef2f2; border: 1px solid #fecaca; padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; color: #b91c1c;">
                                ⚠ Unbalanced Ledger
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <div style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden;">
                <div style="border-bottom: 1px solid #e2e8f0; padding: 1rem 1.5rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin: 0;">General Ledger Accounts</h3>
                    <p style="font-size: 0.875rem; color: #64748b; margin: 0.25rem 0 0 0;">Statutory trial balance as of {{ now()->toFormattedDateString() }}</p>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 0.75rem 1.5rem; font-weight: 600; color: #475569;">Account Code</th>
                                <th style="padding: 0.75rem 1.5rem; font-weight: 600; color: #475569;">Account Name</th>
                                <th style="padding: 0.75rem 1.5rem; font-weight: 600; color: #475569;">Type</th>
                                <th style="padding: 0.75rem 1.5rem; font-weight: 600; color: #475569; text-align: right;">Net Debit (BDT)</th>
                                <th style="padding: 0.75rem 1.5rem; font-weight: 600; color: #475569; text-align: right;">Net Credit (BDT)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report->accounts as $row)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.75rem 1.5rem; font-family: monospace; font-weight: 600; color: #0f172a;">{{ $row->accountCode }}</td>
                                    <td style="padding: 0.75rem 1.5rem; color: #334155;">{{ $row->accountName }}</td>
                                    <td style="padding: 0.75rem 1.5rem; text-transform: capitalize; color: #64748b;">{{ $row->accountType }}</td>
                                    <td style="padding: 0.75rem 1.5rem; text-align: right; font-family: monospace; color: #0f172a;">৳ {{ $row->netDebit->getAmount() }}</td>
                                    <td style="padding: 0.75rem 1.5rem; text-align: right; font-family: monospace; color: #0f172a;">৳ {{ $row->netCredit->getAmount() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding: 2rem 1.5rem; text-align: center; color: #94a3b8;">
                                        No ledger activity posted in this period.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr style="border-top: 2px solid #cbd5e1; background: #f8fafc; font-weight: 700;">
                                <td colspan="3" style="padding: 1rem 1.5rem; color: #0f172a;">Total</td>
                                <td style="padding: 1rem 1.5rem; text-align: right; font-family: monospace; color: #0f172a;">৳ {{ $report->totalDebit->getAmount() }}</td>
                                <td style="padding: 1rem 1.5rem; text-align: right; font-family: monospace; color: #0f172a;">৳ {{ $report->totalCredit->getAmount() }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @else
            <div style="background: white; border-radius: 0.75rem; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <p style="font-size: 0.875rem; color: #64748b;">No active company profile found for the current tenant.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
