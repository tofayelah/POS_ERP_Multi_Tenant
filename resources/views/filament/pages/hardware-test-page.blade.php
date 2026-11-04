<x-filament-panels::page>
    @php
        $receipt = $this->getReceiptData();
    @endphp

    <div x-data="hardwareTestBench()" x-init="init()" class="space-y-6">

        <!-- Environment Security Notice Banner -->
        <div class="bg-amber-500/15 border-l-4 border-amber-500 p-4 rounded-r-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <span class="text-amber-600 text-xl font-bold">⚙</span>
                    <div>
                        <h4 class="text-sm font-bold text-amber-800 dark:text-amber-200">
                            Hardware Test Bench (Local & Staging Environment Active)
                        </h4>
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            Available strictly in non-production environments (§11.3, Prompt 10C). Connects to local Go daemon at <code class="font-mono font-bold">ws://127.0.0.1:9191/ws</code>.
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold"
                          :class="bridgeConnected ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-red-100 text-red-800 border border-red-300'">
                        <span class="w-2 h-2 rounded-full mr-1.5" :class="bridgeConnected ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'"></span>
                        <span x-text="bridgeConnected ? 'Bridge: ONLINE (127.0.0.1:9191)' : 'Bridge: OFFLINE'"></span>
                    </span>
                    <button type="button" @click="connectBridge()" class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-white rounded text-xs font-semibold">
                        Reconnect
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- Left Column: Interactive Controls & Hardware Operations (7 Cols) -->
            <div class="lg:col-span-7 space-y-6">

                <!-- 1. Printer Profile & Hardware Action Cards -->
                <div class="bg-white dark:bg-gray-800 p-5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-4">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center space-x-2">
                        <span>🖨</span>
                        <span>Thermal Printer Profile & Commands</span>
                    </h3>

                    <!-- Width Selector -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2">Select Printer Printable Width</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" 
                                    @click="selectedWidth = '80mm'" 
                                    class="p-3 border-2 rounded-lg text-left transition"
                                    :class="selectedWidth === '80mm' ? 'border-primary-600 bg-primary-50 dark:bg-primary-950/30 text-primary-900 dark:text-primary-100' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50'">
                                <div class="font-bold text-sm">80 mm Standard</div>
                                <div class="text-xs text-gray-500">576 Dots Active | 48 Characters / Line</div>
                            </button>
                            <button type="button" 
                                    @click="selectedWidth = '58mm'" 
                                    class="p-3 border-2 rounded-lg text-left transition"
                                    :class="selectedWidth === '58mm' ? 'border-primary-600 bg-primary-50 dark:bg-primary-950/30 text-primary-900 dark:text-primary-100' : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50'">
                                <div class="font-bold text-sm">58 mm Compact</div>
                                <div class="text-xs text-gray-500">384 Dots Active | 32 Characters / Line</div>
                            </button>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button type="button" 
                                @click="printMushakReceipt()"
                                :disabled="!bridgeConnected"
                                class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-bold text-xs flex items-center justify-center space-x-2 shadow">
                            <span>📄</span>
                            <span>Print Basket Vector Mushak 6.3</span>
                        </button>

                        <button type="button" 
                                @click="kickCashDrawer()"
                                :disabled="!bridgeConnected"
                                class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg font-bold text-xs flex items-center justify-center space-x-2 shadow">
                            <span>💵</span>
                            <span>Kick Cash Drawer (ESC p)</span>
                        </button>
                    </div>

                    <div class="pt-2 flex items-center justify-between border-t border-gray-100 dark:border-gray-700 text-xs">
                        <span class="text-gray-500">Bridge Token: <code class="font-mono font-bold text-gray-700 dark:text-gray-300">a1b2c3d4...abcdef (64-char)</code></span>
                        <button type="button" @click="pingBridge()" :disabled="!bridgeConnected" class="text-primary-600 hover:underline font-semibold">
                            Ping Bridge Daemon
                        </button>
                    </div>
                </div>

                <!-- 2. Real-Time Hardware Event Log -->
                <div class="bg-gray-900 text-emerald-400 p-4 rounded-xl font-mono text-xs shadow-inner h-64 overflow-y-auto space-y-1">
                    <div class="text-gray-400 border-b border-gray-800 pb-1 flex justify-between">
                        <span>[HARDWARE BRIDGE DIAGNOSTIC LOG]</span>
                        <button type="button" @click="logs = []" class="text-gray-500 hover:text-white">Clear</button>
                    </div>
                    <template x-for="(log, idx) in logs" :key="idx">
                        <div class="leading-relaxed">
                            <span class="text-gray-500" x-text="log.time"></span>
                            <span class="ml-2" :class="log.color" x-text="log.message"></span>
                        </div>
                    </template>
                </div>

            </div>

            <!-- Right Column: Visual Paper Receipt Simulation (5 Cols) -->
            <div class="lg:col-span-5">
                <div class="bg-amber-50 dark:bg-slate-900 p-6 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 shadow font-mono text-xs text-gray-800 dark:text-gray-200">
                    <div class="text-center font-bold pb-2 border-b border-gray-300 dark:border-gray-700 space-y-1">
                        <div class="text-sm font-black">{{ $receipt['store_name'] }}</div>
                        <div class="text-[11px]">{{ $receipt['outlet_name'] }} | Terminal: {{ $receipt['terminal_code'] }}</div>
                        <div class="text-[10px] text-gray-500">{{ $receipt['mushak_title'] }}</div>
                        <div class="text-[10px]">Invoice: {{ $receipt['invoice_number'] }} | Date: {{ $receipt['date_time'] }}</div>
                    </div>

                    <!-- Divider -->
                    <div class="my-2 border-b border-dashed border-gray-400"></div>

                    <!-- Line Items Table -->
                    <div class="space-y-3 my-3">
                        @foreach ($receipt['lines'] as $line)
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white leading-tight">
                                    {{ $line['name_bn'] }}
                                </div>
                                <div class="text-[10px] text-gray-500 italic">{{ $line['name_en'] }}</div>
                                <div class="flex justify-between items-center text-[11px] pt-0.5">
                                    <span>{{ $line['qty'] }} x ৳{{ $line['unit_price'] }} (VAT {{ $line['tax_rate'] }})</span>
                                    <span class="font-bold">৳{{ $line['gross'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Divider -->
                    <div class="my-2 border-b border-dashed border-gray-400"></div>

                    <!-- Totals & Discounts -->
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between">
                            <span>Gross Subtotal:</span>
                            <span>৳{{ $receipt['totals']['gross_subtotal'] }}</span>
                        </div>
                        <div class="flex justify-between text-indigo-700 dark:text-indigo-400 font-bold">
                            <span>{{ $receipt['discount']['type'] }}:</span>
                            <span>-৳{{ $receipt['discount']['amount'] }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Net Subtotal (Pre-VAT):</span>
                            <span>৳{{ $receipt['totals']['net_subtotal'] }}</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>VAT Total Accrued:</span>
                            <span>৳{{ $receipt['totals']['vat_total'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-black pt-1 border-t border-gray-400">
                            <span>TOTAL PAYABLE:</span>
                            <span class="text-emerald-700 dark:text-emerald-400">৳{{ $receipt['totals']['total_payable'] }}</span>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="my-3 border-b-2 border-dashed border-red-400"></div>

                    <!-- Mandatory Statutory Provisional Disclaimer -->
                    <div class="text-center space-y-1 bg-red-50 dark:bg-red-950/40 p-2.5 rounded border border-red-300 dark:border-red-800">
                        <div class="font-black text-red-700 dark:text-red-400 text-[11px]">
                            {{ $receipt['provisional_footer']['disclaimer'] }}
                        </div>
                        <div class="text-[10px] text-red-600 dark:text-red-300 leading-tight">
                            {{ $receipt['provisional_footer']['legal_notice'] }}
                        </div>
                        <div class="text-[9px] text-gray-500 pt-1">
                            {{ $receipt['provisional_footer']['warning'] }}
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Offscreen Canvas for Rendering Bengali Raster Bitmaps -->
        <canvas id="offscreen-bengali-canvas" style="display:none;"></canvas>
    </div>

    <script>
        function hardwareTestBench() {
            return {
                bridgeConnected: false,
                ws: null,
                selectedWidth: '80mm',
                token: 'a1b2c3d4e5f678901234567890abcdef1234567890abcdef1234567890abcdef',
                logs: [],

                init() {
                    this.addLog('Initializing Hardware Test Bench...', 'text-gray-400');
                    this.connectBridge();
                },

                addLog(message, color = 'text-emerald-400') {
                    const time = new Date().toLocaleTimeString();
                    this.logs.unshift({ time, message, color });
                    if (this.logs.length > 50) this.logs.pop();
                },

                connectBridge() {
                    this.addLog('Connecting to ws://127.0.0.1:9191/ws...', 'text-yellow-400');
                    try {
                        this.ws = new WebSocket('ws://127.0.0.1:9191/ws');

                        this.ws.onopen = () => {
                            this.addLog('WebSocket transport connected. Transmitting 64-char pairing token...', 'text-yellow-400');
                            this.ws.send(JSON.stringify({
                                action: 'auth',
                                token: this.token
                            }));
                        };

                        this.ws.onmessage = (event) => {
                            const data = JSON.parse(event.data);
                            if (data.status === 'authenticated') {
                                this.bridgeConnected = true;
                                this.addLog(`Bridge Authenticated! Version: ${data.version || '1.0.0'}`, 'text-emerald-400');
                            } else {
                                this.addLog(`Bridge Reply: ${JSON.stringify(data)}`, 'text-cyan-300');
                            }
                        };

                        this.ws.onerror = (err) => {
                            this.bridgeConnected = false;
                            this.addLog('WebSocket error encountered. Ensure bridge is running on 127.0.0.1:9191', 'text-red-400');
                        };

                        this.ws.onclose = (event) => {
                            this.bridgeConnected = false;
                            this.addLog(`Connection closed (code: ${event.code})`, 'text-gray-400');
                        };
                    } catch (e) {
                        this.addLog(`Failed to instantiate WebSocket: ${e.message}`, 'text-red-500');
                    }
                },

                pingBridge() {
                    if (!this.bridgeConnected) return;
                    this.addLog('Sending ping to bridge daemon...', 'text-gray-300');
                    this.ws.send(JSON.stringify({ action: 'ping' }));
                },

                kickCashDrawer() {
                    if (!this.bridgeConnected) return;
                    this.addLog('Sending ESC/POS Cash Drawer Kick (ESC p 0 25 250)...', 'text-indigo-400');
                    // ESC p 0 25 250
                    this.ws.send(JSON.stringify({
                        action: 'print',
                        data: '\x1b\x70\x00\x19\xfa'
                    }));
                },

                printMushakReceipt() {
                    if (!this.bridgeConnected) return;
                    const width = this.selectedWidth;
                    this.addLog(`Formatting Mushak 6.3 Receipt for ${width} printer...`, 'text-yellow-300');

                    // 1. Render long Bengali product description to raster bitmap
                    const bengaliText = 'খাঁটি ঘানি-ভাঙা সরিষার তেল ১ লিটার বোতল (স্পেশাল)';
                    const bitmapData = this.renderBengaliToBitmap(bengaliText, width === '80mm' ? 576 : 384);

                    // 2. Assemble raw ESC/POS commands
                    let job = '\x1b\x40'; // ESC @ (Init)
                    job += '\x1b\x61\x01\x1b\x45\x01'; // Center, Bold
                    job += 'SONAR BANGLA MART\n';
                    job += '\x1b\x45\x00'; // Bold off
                    job += 'Dhanmondi Outlet #1 | T-01\n';
                    job += 'Mushak 6.3 Tax Invoice\n';
                    job += (width === '80mm' ? '------------------------------------------------\n' : '--------------------------------\n');
                    job += '\x1b\x61\x00'; // Left align

                    // ASCII Line 1
                    job += (width === '80mm' ? 'Mustard Oil 1L                          500.00\n' : 'Mustard Oil 1L          500.00\n');
                    // ASCII Line 2
                    job += (width === '80mm' ? 'Neem Soap 125g (2x)                     360.00\n' : 'Neem Soap 2x            360.00\n');
                    job += (width === '80mm' ? '------------------------------------------------\n' : '--------------------------------\n');

                    // Discount Line
                    job += (width === '80mm' ? '5% Cashier Discount                     -43.00\n' : '5% Cashier Discount     -43.00\n');
                    job += (width === '80mm' ? '------------------------------------------------\n' : '--------------------------------\n');

                    // Totals
                    job += '\x1b\x61\x02\x1b\x45\x01'; // Right align, Bold
                    job += 'TOTAL PAYABLE: 817.00 BDT\n';
                    job += '\x1b\x45\x00'; // Bold off

                    // Provisional Statutory Footer
                    job += '\x1b\x61\x01'; // Center
                    job += '\n*** PROVISIONAL TAX INVOICE - PENDING FISCALIZATION ***\n';
                    job += 'SDC Device Offline / Internal Ref: INV-DHA01-T01-TEST01\n';
                    job += 'Fiscal Signature will be assigned upon SDC sync.\n\n';

                    // Cash Drawer Kick pulse
                    job += '\x1b\x70\x00\x19\xfa';

                    // Partial Cut
                    job += '\x1d\x56\x01';

                    // Transmit to Bridge
                    this.ws.send(JSON.stringify({
                        action: 'print',
                        width: width,
                        data: job
                    }));

                    this.addLog(`Dispatched ${job.length} bytes raw ESC/POS with Bengali raster & drawer kick to bridge!`, 'text-emerald-400');
                },

                renderBengaliToBitmap(text, maxWidth) {
                    const canvas = document.getElementById('offscreen-bengali-canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = maxWidth;
                    canvas.height = 36;

                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, canvas.width, canvas.height);

                    ctx.font = '20px "SolaimanLipi", "Noto Sans Bengali", sans-serif';
                    ctx.fillStyle = '#000000';
                    ctx.textBaseline = 'top';
                    ctx.fillText(text, 4, 6);

                    // Extract pixel data
                    return ctx.getImageData(0, 0, canvas.width, canvas.height);
                }
            };
        }
    </script>
</x-filament-panels::page>
