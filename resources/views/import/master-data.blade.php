<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Import & Dry-Run Validation (TEN-06) - KC ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased">
    <!-- Header -->
    <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur sticky top-0 z-50 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-3">
            <div class="w-9 h-9 rounded-lg bg-sky-600 flex items-center justify-center font-bold text-white shadow-lg shadow-sky-900/30">
                ERP
            </div>
            <div>
                <h1 class="text-base font-bold text-white flex items-center gap-2">
                    KC ERP Master Data Import
                    <span class="text-xs px-2 py-0.5 rounded bg-sky-950 text-sky-400 border border-sky-800">TEN-06</span>
                </h1>
                <p class="text-xs text-slate-400">Excel (.xlsx) & CSV Ingestion Engine with Zero-Write Dry Run</p>
            </div>
        </div>
        <div class="flex items-center space-x-4 text-xs">
            <span class="text-slate-400">Tenant: <strong class="text-slate-200">{{ $tenant->name ?? 'Default Tenant' }}</strong></span>
            <span class="text-slate-400">Company: <strong class="text-slate-200">{{ $company->name ?? 'Primary Co' }}</strong></span>
            <a href="/pos" class="px-3 py-1.5 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-300 transition">Back to POS</a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-5xl w-full mx-auto p-6 md:p-8 space-y-8">
        <!-- Info Card -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5 text-sm text-slate-300">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-sky-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="space-y-1">
                    <h3 class="font-semibold text-white">Statutory Retail Ingestion Guard (Design §7.1, §7.2, TEN-06)</h3>
                    <p class="text-slate-400 text-xs leading-relaxed">
                        The Master Data Import Engine supports bulk loading for Products, Customers, and Suppliers. 
                        When <strong>Dry-Run Mode</strong> is active, every row is parsed and verified against mandatory code requirements, SKU uniqueness, and non-negative pricing. <strong>Zero rows are written to PostgreSQL</strong> during dry-run validation.
                    </p>
                </div>
            </div>
        </div>

        <!-- Upload Form -->
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                Upload Master Data Spreadsheet
            </h2>

            <form action="/import/master-data" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Master Type -->
                    <div>
                        <label for="type" class="block text-xs font-semibold text-slate-300 mb-2 uppercase tracking-wider">
                            Master Record Type
                        </label>
                        <select name="type" id="type" data-testid="import-type-select" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-sky-500">
                            <option value="products" selected>Products & Variants (SKU, MRP, Cost)</option>
                            <option value="customers">Customers (Name, Phone, Credit Limit)</option>
                            <option value="suppliers">Suppliers (Name, Code, BIN)</option>
                        </select>
                    </div>

                    <!-- File Picker -->
                    <div>
                        <label for="import_file" class="block text-xs font-semibold text-slate-300 mb-2 uppercase tracking-wider">
                            Spreadsheet File (.xlsx, .csv)
                        </label>
                        <input type="file" name="import_file" id="import_file" data-testid="import-file-input" required accept=".xlsx,.csv"
                               class="w-full text-sm text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-sky-600 file:text-white hover:file:bg-sky-500 cursor-pointer bg-slate-950 border border-slate-700 rounded-lg py-1.5 px-3">
                    </div>
                </div>

                <!-- Dry Run Toggle -->
                <div class="flex items-center gap-3 bg-slate-950/60 border border-slate-800 p-4 rounded-lg">
                    <input type="checkbox" name="dry_run" id="dry_run" value="1" data-testid="dry-run-checkbox" checked
                           class="w-4 h-4 rounded border-slate-700 text-sky-600 focus:ring-sky-500 bg-slate-900 cursor-pointer">
                    <label for="dry_run" class="text-sm font-medium text-slate-200 cursor-pointer">
                        Dry Run Validation Only <span class="text-xs text-amber-400 font-mono">(Recommended: Validates syntax, constraints & reports errors without database writes)</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-3">
                    <button type="submit" data-testid="btn-submit-import" class="px-5 py-2.5 rounded-lg bg-sky-600 hover:bg-sky-500 font-semibold text-sm text-white shadow-lg shadow-sky-900/30 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Validate & Process Import
                    </button>
                </div>
            </form>
        </div>

        <!-- Dry Run Results Section -->
        @if ($submitted && $result)
        <section id="dry-run-report" data-testid="dry-run-report" class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-xl space-y-5">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    @if ($result['valid'])
                        <div class="w-8 h-8 rounded-full bg-emerald-950 border border-emerald-700 flex items-center justify-center text-emerald-400 font-bold">✓</div>
                        <div>
                            <h3 class="text-base font-bold text-emerald-400">Validation Passed Successfully</h3>
                            <p class="text-xs text-slate-400">{{ $dryRun ? 'Dry-run passed with 0 errors. Ready for live import.' : 'Import committed: ' . $result['processed'] . ' rows inserted/updated.' }}</p>
                        </div>
                    @else
                        <div class="w-8 h-8 rounded-full bg-rose-950 border border-rose-700 flex items-center justify-center text-rose-400 font-bold">✕</div>
                        <div>
                            <h3 class="text-base font-bold text-rose-400">Dry-Run Validation Failed: {{ count($result['errors']) }} Row Errors Detected</h3>
                            <p class="text-xs text-slate-400">File: <span class="font-mono text-slate-200">{{ $fileName ?? 'uploaded_file' }}</span> &bull; 0 Database Writes Committed</p>
                        </div>
                    @endif
                </div>
                <div class="text-right">
                    <span class="inline-block text-xs font-mono font-bold px-3 py-1 rounded {{ $result['valid'] ? 'bg-emerald-950 text-emerald-300 border border-emerald-800' : 'bg-rose-950 text-rose-300 border border-rose-800' }}">
                        {{ $dryRun ? 'DRY-RUN VALIDATION' : 'LIVE COMMIT' }}
                    </span>
                </div>
            </div>

            <!-- Error Table -->
            @if (! $result['valid'] && ! empty($result['errors']))
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                            <th class="py-2.5 px-3">Excel Row</th>
                            <th class="py-2.5 px-3">Field</th>
                            <th class="py-2.5 px-3">Violation Reason</th>
                            <th class="py-2.5 px-3 text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 font-mono">
                        @foreach ($result['errors'] as $err)
                        <tr class="hover:bg-slate-800/40 transition" data-testid="error-row-{{ $err['row'] }}">
                            <td class="py-3 px-3 font-bold text-rose-300">
                                Row {{ $err['row'] }}
                            </td>
                            <td class="py-3 px-3 text-amber-300 font-medium">
                                {{ $err['field'] }}
                            </td>
                            <td class="py-3 px-3 text-slate-200 font-sans">
                                {{ $err['message'] }}
                            </td>
                            <td class="py-3 px-3 text-right">
                                <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold bg-rose-950 text-rose-400 border border-rose-800">
                                    Rejected
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-rose-950/20 border border-rose-900/40 p-4 rounded-lg text-xs text-rose-300 flex items-center justify-between">
                <span>All <strong>{{ count($result['errors']) }} deliberate row errors</strong> were trapped by the dry-run guard. Correct the file and re-upload.</span>
                <span class="font-mono text-[11px] text-slate-400">TEN-06 Invariant: 100% Fail-Closed</span>
            </div>
            @endif
        </section>
        @endif
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 py-4 px-6 text-center text-xs text-slate-500">
        Retail ERP &copy; 2026 &bull; Multi-Tenant Enterprise Master Data Engine (ADR-002, TEN-06)
    </footer>
</body>
</html>
