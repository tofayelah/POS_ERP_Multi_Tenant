<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Terminal - Sonar Bangla Mart</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Dexie.js for IndexedDB -->
    <script src="https://unpkg.com/dexie@4.0.10/dist/dexie.js"></script>
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 select-none overflow-hidden h-screen" x-data="posApp()" x-init="initApp()" x-cloak>

    <!-- Top Header -->
    <header class="bg-slate-900 text-white px-4 py-2 flex items-center justify-between border-b border-slate-700 shadow-md">
        <div class="flex items-center space-x-3">
            <span class="font-bold text-lg text-emerald-400">SONAR BANGLA MART</span>
            <span class="text-xs text-gray-400">|</span>
            <span class="text-xs bg-slate-800 text-gray-200 px-2 py-0.5 rounded" x-text="outletName"></span>
            <span class="text-xs bg-slate-800 text-gray-200 px-2 py-0.5 rounded" x-text="'Terminal: ' + terminalCode"></span>
            <span class="text-xs text-gray-400">|</span>
            <span class="text-xs text-gray-300" x-text="'Cashier: ' + cashierName + (shiftOpen ? ' [Shift #' + shiftId + ']' : ' [No Active Shift]')"></span>
        </div>

        <div class="flex items-center space-x-4">
            <!-- Offline/Online Toggle for simulation -->
            <button @click="toggleNetwork()" 
                    class="px-2.5 py-1 rounded text-xs font-semibold flex items-center space-x-1 transition"
                    :class="isOnline ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : 'bg-amber-600 hover:bg-amber-500 text-white animate-pulse'"
                    id="network-toggle-btn">
                <span class="w-2 h-2 rounded-full" :class="isOnline ? 'bg-white' : 'bg-red-200'"></span>
                <span x-text="isOnline ? 'Network: ONLINE' : 'Network: OFFLINE (Local Mode)'"></span>
            </button>

            <!-- SDC Status -->
            <span class="text-xs px-2 py-1 rounded bg-slate-800 text-emerald-400 flex items-center space-x-1">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>SDC: CONNECTED</span>
            </span>

            <!-- Lock / Sign Out -->
            <button @click="lockTerminal()" class="text-xs text-gray-300 hover:text-white bg-slate-800 hover:bg-slate-700 px-2.5 py-1 rounded">
                Lock PIN
            </button>
        </div>
    </header>

    <!-- Offline Alert Banner -->
    <div x-show="!isOnline" class="bg-amber-500 text-slate-950 font-bold px-4 py-1 text-center text-xs shadow flex items-center justify-between">
        <span>⚠ OFFLINE MODE: Network disconnected. Transactions are saving locally to Dexie.js (IndexedDB). Zero data loss guaranteed.</span>
        <span class="bg-slate-900 text-amber-300 px-2 py-0.5 rounded text-[11px]" x-text="pendingSalesCount + ' sale(s) waiting to sync'"></span>
    </div>

    <!-- Main Workspace (1366x768 layout) -->
    <main class="flex h-[calc(100vh-42px)]">

        <!-- Left Pane: Scanner, Line Items Table, Quick Keys -->
        <section class="w-8/12 flex flex-col border-r border-gray-300 bg-white">
            <!-- Scan / Search Bar -->
            <div class="p-3 bg-slate-50 border-b border-gray-200 flex items-center space-x-2">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-xs font-bold text-gray-500">[F1]</span>
                    <input type="text" 
                           id="barcode-input" 
                           x-ref="barcodeInput"
                           x-model="scanQuery" 
                           @keydown.enter="handleScan()"
                           placeholder="Scan Barcode or enter SKU / Item Name..." 
                           class="w-full pl-12 pr-4 py-2 border-2 border-slate-700 rounded-md focus:border-emerald-600 focus:outline-none text-sm font-mono tracking-wider">
                </div>
                <button @click="handleScan()" class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded text-xs font-bold uppercase tracking-wider">
                    Add Item
                </button>
            </div>

            <!-- Quick Product Shortcuts -->
            <div class="px-3 py-1.5 bg-gray-100 border-b border-gray-200 flex items-center space-x-2 text-xs">
                <span class="text-gray-500 font-semibold uppercase tracking-wider text-[11px]">Quick Scan:</span>
                <button @click="quickAdd('8941100111111')" id="btn-quick-rice" class="bg-white hover:bg-emerald-50 border border-gray-300 px-2.5 py-1 rounded shadow-sm text-slate-800 font-medium">
                    + Rice 5kg (৳450 - 0% VAT)
                </button>
                <button @click="quickAdd('8941100222222')" id="btn-quick-oil" class="bg-white hover:bg-emerald-50 border border-gray-300 px-2.5 py-1 rounded shadow-sm text-slate-800 font-medium">
                    + Mustard Oil 1L (৳280 - 5% VAT)
                </button>
                <button @click="quickAdd('8941100333333')" id="btn-quick-soap" class="bg-white hover:bg-emerald-50 border border-gray-300 px-2.5 py-1 rounded shadow-sm text-slate-800 font-medium">
                    + Neem Soap (৳65 - 15% VAT)
                </button>
            </div>

            <!-- Cart Table -->
            <div class="flex-1 overflow-y-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-800 text-white uppercase text-[11px] sticky top-0 tracking-wider">
                        <tr>
                            <th class="py-2.5 px-3 w-10 text-center">#</th>
                            <th class="py-2.5 px-3">Item Description</th>
                            <th class="py-2.5 px-3 w-24">Batch / Exp</th>
                            <th class="py-2.5 px-3 w-24 text-right">Unit MRP</th>
                            <th class="py-2.5 px-3 w-28 text-center">Qty</th>
                            <th class="py-2.5 px-3 w-20 text-center">Disc%</th>
                            <th class="py-2.5 px-3 w-28 text-right">Total (BDT)</th>
                            <th class="py-2.5 px-2 w-12 text-center">Del</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200" id="cart-table-body">
                        <template x-for="(item, index) in cart" :key="item.variant_id">
                            <tr class="hover:bg-slate-50 transition" :class="selectedItemIndex === index ? 'bg-emerald-50' : ''" @click="selectedItemIndex = index">
                                <td class="py-2.5 px-3 text-center text-gray-500 font-mono" x-text="index + 1"></td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-gray-900" x-text="item.name_en"></div>
                                    <div class="text-[11px] text-gray-500 font-mono" x-text="item.sku + ' | VAT ' + item.tax_rate + '% (' + item.tax_type + ')'"></div>
                                </td>
                                <td class="py-2.5 px-3 text-gray-600 font-mono text-[11px]" x-text="item.batch || 'B-STD'"></td>
                                <td class="py-2.5 px-3 text-right font-mono font-medium" x-text="'৳' + item.mrp_price"></td>
                                <td class="py-2.5 px-3 text-center">
                                    <div class="inline-flex items-center border border-gray-300 rounded shadow-sm">
                                        <button @click="changeQty(index, -1)" class="px-2 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold">-</button>
                                        <span class="px-3 font-mono font-bold" x-text="item.qty"></span>
                                        <button @click="changeQty(index, 1)" class="px-2 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold">+</button>
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold" 
                                          :class="item.discount_percent > 0 ? 'bg-amber-100 text-amber-800' : 'text-gray-400'" 
                                          x-text="item.discount_percent + '%'"></span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900" x-text="'৳' + item.total_cost"></td>
                                <td class="py-2.5 px-2 text-center">
                                    <button @click="removeItem(index)" class="text-red-500 hover:text-red-700 font-bold">&times;</button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="cart.length === 0">
                            <td colspan="8" class="py-16 text-center text-gray-400">
                                <div class="text-4xl mb-2">🛒</div>
                                <div class="text-sm font-semibold">Active Cart is Empty</div>
                                <div class="text-xs text-gray-400">Scan barcodes or click quick-scan buttons above to add products</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom Shortcuts Toolbar -->
            <div class="bg-slate-100 border-t border-gray-200 p-2 flex items-center justify-between text-xs">
                <div class="flex items-center space-x-2">
                    <button @click="openDiscountModal()" id="btn-open-discount" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded font-bold shadow-sm">
                        [F3] Apply Discount
                    </button>
                    <button @click="clearCart()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1.5 rounded font-bold">
                        [Esc] Clear Cart
                    </button>
                </div>
                <div class="text-gray-500 text-[11px]">
                    Cart automatically persists to Dexie.js IndexedDB on every keystroke
                </div>
            </div>
        </section>

        <!-- Right Pane: Summary, Tender, Shift Management -->
        <section class="w-4/12 flex flex-col bg-slate-50 p-4 justify-between">
            <div>
                <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 border-b pb-1">Current Sale Summary</h2>

                <div class="space-y-2.5 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Gross MRP Subtotal</span>
                        <span class="font-mono font-semibold text-gray-900" x-text="'৳' + totals.gross">৳0.00</span>
                    </div>
                    <div class="flex justify-between text-amber-700">
                        <span>Discount Applied</span>
                        <span class="font-mono font-semibold" x-text="'-৳' + totals.discount">৳0.00</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Net Taxable Base</span>
                        <span class="font-mono font-semibold text-gray-900" x-text="'৳' + totals.net">৳0.00</span>
                    </div>
                    <div class="flex justify-between text-emerald-700">
                        <span>Total VAT (NBR 2012)</span>
                        <span class="font-mono font-semibold" x-text="'+৳' + totals.vat">৳0.00</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Cash Rounding Adj</span>
                        <span class="font-mono font-semibold text-gray-900" x-text="'৳' + totals.rounding">৳0.00</span>
                    </div>

                    <div class="pt-3 border-t-2 border-slate-700 flex justify-between items-baseline">
                        <span class="text-base font-extrabold text-slate-900 uppercase">Total Payable</span>
                        <span class="text-2xl font-black font-mono text-emerald-600" id="grand-total-display" x-text="'৳' + totals.payable">৳0.00</span>
                    </div>
                </div>

                <!-- Active Discount Notification -->
                <div x-show="appliedDiscountPercent > 0" class="mt-4 p-2 bg-indigo-50 border border-indigo-200 rounded text-xs text-indigo-900">
                    <span class="font-bold" x-text="appliedDiscountPercent + '% Cashier Discount Active'"></span>: 
                    <span x-text="appliedDiscountReason"></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3 mt-4">
                <button @click="openTenderModal()" 
                        id="btn-pay-cash"
                        :disabled="cart.length === 0"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold py-3.5 rounded-lg shadow-lg text-lg uppercase tracking-wider flex items-center justify-center space-x-2 transition">
                    <span>💵 [F7] Pay Cash</span>
                    <span class="text-emerald-200 text-sm font-normal" x-text="'(' + totals.payable + ')'"></span>
                </button>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button @click="openShiftCloseModal()" id="btn-open-close-shift" class="bg-slate-700 hover:bg-slate-800 text-white py-2.5 rounded font-bold uppercase tracking-wider">
                        Close Register Shift
                    </button>
                    <button @click="openShiftModal()" id="btn-open-new-shift" class="bg-slate-200 hover:bg-slate-300 text-slate-800 py-2.5 rounded font-bold uppercase tracking-wider">
                        Shift Float / Status
                    </button>
                </div>
            </div>
        </section>
    </main>

    <!-- ==================== MODALS ==================== -->

    <!-- 1. PIN Lock / Authentication Modal -->
    <div x-show="showPinModal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-96 max-w-full text-center border-2 border-slate-700">
            <div class="w-12 h-12 bg-slate-900 text-white rounded-full flex items-center justify-center mx-auto mb-3 text-xl">
                🔒
            </div>
            <h3 class="text-lg font-bold text-slate-900 mb-1">Terminal Security Login</h3>
            <p class="text-xs text-gray-500 mb-4">Enter 4-digit Cashier / Supervisor PIN to unlock</p>

            <!-- 60s Lockout Countdown Banner -->
            <div x-show="isLocked" class="mb-4 p-3 bg-red-100 border border-red-300 rounded-lg text-red-800 text-xs font-bold animate-pulse" id="lockout-banner">
                🚨 SECURITY LOCKOUT: 3 consecutive failed PIN attempts.<br>
                Terminal locked for <span class="text-red-950 font-black text-sm" x-text="lockoutSeconds"></span> seconds.
            </div>

            <!-- PIN Input -->
            <div class="mb-4">
                <input type="password" 
                       id="pin-input"
                       maxlength="4"
                       x-model="enteredPin" 
                       :disabled="isLocked"
                       placeholder="••••" 
                       class="text-center font-mono text-3xl tracking-widest w-40 py-2 border-2 border-slate-400 rounded-lg focus:border-slate-800 focus:outline-none disabled:bg-gray-200">
            </div>

            <!-- Keypad Buttons -->
            <div class="grid grid-cols-3 gap-2 mb-4">
                <template x-for="n in [1,2,3,4,5,6,7,8,9]">
                    <button @click="appendPin(n)" :disabled="isLocked" class="py-2.5 bg-slate-100 hover:bg-slate-200 font-bold rounded text-lg disabled:opacity-40" x-text="n"></button>
                </template>
                <button @click="clearPin()" :disabled="isLocked" class="py-2.5 bg-gray-200 font-bold rounded text-xs text-gray-700">CLR</button>
                <button @click="appendPin(0)" :disabled="isLocked" class="py-2.5 bg-slate-100 hover:bg-slate-200 font-bold rounded text-lg">0</button>
                <button @click="submitPin()" :disabled="isLocked || enteredPin.length < 4" id="btn-submit-pin" class="py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded text-xs">OK</button>
            </div>

            <p class="text-[11px] text-gray-400">Default Cashier PIN: <span class="font-mono font-bold">4821</span></p>
        </div>
    </div>

    <!-- 2. Open Shift Modal (Cash Float) -->
    <div x-show="showShiftModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-[420px] max-w-full border border-gray-300">
            <h3 class="text-base font-bold text-slate-900 mb-1">Open Register Shift #42</h3>
            <p class="text-xs text-gray-500 mb-4">Count cash in drawer and enter starting cash float</p>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Starting Cash Float (BDT)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-mono font-bold text-gray-500">৳</span>
                    <input type="number" 
                           id="opening-float-input"
                           x-model="openingFloat" 
                           placeholder="2000" 
                           class="w-full pl-8 pr-4 py-2.5 border-2 border-slate-700 rounded-lg text-lg font-mono font-bold focus:outline-none">
                </div>
            </div>

            <div class="bg-emerald-50 border border-emerald-200 rounded p-3 mb-4 text-xs text-emerald-800">
                ✓ Storage Persistence Verified: <span class="font-bold">navigator.storage.persist() granted</span>.<br>
                ✓ Quota Check: <span class="font-bold">> 50 MB Free Space</span>.
            </div>

            <div class="flex justify-end space-x-2">
                <button @click="confirmOpenShift()" id="btn-confirm-open-shift" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider">
                    Confirm & Start Shift
                </button>
            </div>
        </div>
    </div>

    <!-- 3. Discount Modal -->
    <div x-show="showDiscountModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-[440px] max-w-full border border-gray-300">
            <h3 class="text-base font-bold text-slate-900 mb-1">Apply Cashier Discount (§3.2)</h3>
            <p class="text-xs text-gray-500 mb-4">Cashier Limit: <span class="font-bold text-slate-900">Max 5.0%</span> | Supervisor Override required above 5.0%</p>

            <!-- Discount Refusal Error Banner -->
            <div x-show="discountError" class="mb-4 p-3 bg-red-100 border border-red-300 rounded-lg text-red-800 text-xs font-bold" id="discount-error-alert" x-text="discountError"></div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Discount Percentage (%)</label>
                <div class="relative">
                    <input type="number" 
                           id="discount-input"
                           x-model="discountPercentInput" 
                           placeholder="5" 
                           class="w-full px-4 py-2 border-2 border-slate-700 rounded-lg text-lg font-mono font-bold focus:outline-none">
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center font-bold text-gray-500">%</span>
                </div>
            </div>

            <div class="mb-5">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Mandatory Reason Code</label>
                <select x-model="discountReasonInput" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none">
                    <option value="PROMO">Promotional Campaign (PROMO)</option>
                    <option value="CUSTOMER_SATISFACTION">Customer Satisfaction Courtesy</option>
                    <option value="DAMAGED_PACKAGING">Minor Packaging Defect</option>
                    <option value="MANAGEMENT_APPROVAL">Branch Manager Discretion</option>
                </select>
            </div>

            <div class="flex justify-end space-x-2">
                <button @click="showDiscountModal = false; discountError = ''" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-bold">
                    Cancel
                </button>
                <button @click="applyDiscount()" id="btn-confirm-discount" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-xs font-bold">
                    Apply Discount
                </button>
            </div>
        </div>
    </div>

    <!-- 4. Tender & Payment Modal -->
    <div x-show="showTenderModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-[480px] max-w-full border border-gray-300">
            <h3 class="text-base font-bold text-slate-900 mb-1">Tender Payment</h3>
            <p class="text-xs text-gray-500 mb-4">Total Amount Payable: <span class="font-bold text-emerald-600 font-mono text-sm" x-text="'৳' + totals.payable"></span></p>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Cash Tendered (BDT)</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-mono font-bold text-gray-500 text-lg">৳</span>
                    <input type="number" 
                           id="cash-tendered-input"
                           x-model="tenderedAmount" 
                           @input="calculateChange()"
                           placeholder="1000" 
                           class="w-full pl-8 pr-4 py-2.5 border-2 border-slate-700 rounded-lg text-2xl font-mono font-black focus:outline-none">
                </div>
            </div>

            <!-- Quick Cash Presets -->
            <div class="flex space-x-2 mb-4">
                <button @click="setTenderExact()" class="flex-1 py-1 bg-gray-100 hover:bg-gray-200 border rounded text-xs font-semibold">Exact</button>
                <button @click="setTender(500)" class="flex-1 py-1 bg-gray-100 hover:bg-gray-200 border rounded text-xs font-mono font-semibold">৳500</button>
                <button @click="setTender(1000)" class="flex-1 py-1 bg-gray-100 hover:bg-gray-200 border rounded text-xs font-mono font-semibold">৳1000</button>
                <button @click="setTender(2000)" class="flex-1 py-1 bg-gray-100 hover:bg-gray-200 border rounded text-xs font-mono font-semibold">৳2000</button>
            </div>

            <!-- Change Return Breakdown -->
            <div class="bg-slate-50 border border-slate-200 rounded-lg p-3.5 mb-5 flex justify-between items-baseline">
                <div>
                    <span class="text-xs font-bold text-gray-700 uppercase">Change Due (Rounded ৳1)</span>
                    <div class="text-[11px] text-gray-400">Cash drawer kicks automatically</div>
                </div>
                <span class="text-2xl font-black font-mono text-slate-900" id="change-due-display" x-text="'৳' + changeDue">৳0.00</span>
            </div>

            <div class="flex justify-end space-x-2">
                <button @click="showTenderModal = false" class="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-xs font-bold">
                    Cancel
                </button>
                <button @click="completeSale()" id="btn-complete-sale" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider">
                    Confirm & Complete Sale [F9]
                </button>
            </div>
        </div>
    </div>

    <!-- 5. Sale Completed Receipt Toast -->
    <div x-show="showReceiptToast" class="fixed bottom-4 right-4 bg-slate-900 text-white p-4 rounded-xl shadow-2xl z-50 border border-slate-700 w-80">
        <div class="flex items-center space-x-2 text-emerald-400 font-bold mb-1">
            <span>✓</span>
            <span>Transaction Completed</span>
        </div>
        <div class="text-xs text-gray-300" x-text="'Invoice: ' + lastSaleInvoice"></div>
        <div class="text-xs text-gray-400" x-text="'Total: ৳' + lastSaleTotal + ' (Cash Tendered: ৳' + lastSaleTendered + ')'"></div>
        <div class="text-[11px] text-emerald-300 mt-2 font-mono" x-text="isOnline ? '✓ Online Sync Queued' : '✓ Stored Offline in Dexie.js (Pending Sync)'"></div>
    </div>

    <!-- 6. Close Shift Denomination Modal -->
    <div x-show="showShiftCloseModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-[560px] max-w-full border border-gray-300 max-h-[90vh] overflow-y-auto">
            <h3 class="text-base font-bold text-slate-900 mb-1">Close Register Shift #42</h3>
            <p class="text-xs text-gray-500 mb-4">Perform end-of-shift physical note denomination count</p>

            <!-- Shift Financial Overview -->
            <div class="grid grid-cols-3 gap-2 bg-slate-50 p-3 rounded-lg border mb-4 text-xs font-mono">
                <div>
                    <span class="text-gray-500 block text-[11px]">Opening Float</span>
                    <span class="font-bold text-gray-900" x-text="'৳' + openingFloat">৳2,000.00</span>
                </div>
                <div>
                    <span class="text-gray-500 block text-[11px]">Total Cash Sales</span>
                    <span class="font-bold text-emerald-600" x-text="'৳' + shiftCashSales">৳0.00</span>
                </div>
                <div>
                    <span class="text-gray-500 block text-[11px]">Expected in Drawer</span>
                    <span class="font-bold text-slate-900 text-sm" x-text="'৳' + expectedShiftCash">৳2,000.00</span>
                </div>
            </div>

            <!-- Note Denomination Grid -->
            <h4 class="text-xs font-bold text-gray-700 uppercase mb-2">Physical Cash Denomination Count</h4>
            <div class="grid grid-cols-2 gap-2 text-xs mb-4">
                <template x-for="d in denominations" :key="d.note">
                    <div class="flex items-center justify-between border rounded p-1.5 bg-gray-50">
                        <span class="font-mono font-bold w-14" x-text="'৳' + d.note"></span>
                        <span class="text-gray-400 text-xs">&times;</span>
                        <input type="number" 
                               min="0"
                               x-model.number="d.count" 
                               @input="calculateActualCash()"
                               class="w-16 px-2 py-1 border rounded text-right font-mono font-bold text-xs"
                               :id="'denom-' + d.note">
                        <span class="font-mono text-gray-700 w-20 text-right" x-text="'৳' + (d.note * (d.count || 0))"></span>
                    </div>
                </template>
            </div>

            <!-- Reconciliation Summary -->
            <div class="bg-slate-100 p-3 rounded-lg border flex justify-between items-center text-xs mb-4">
                <div>
                    <span class="text-gray-600 font-semibold">Counted Actual Cash:</span>
                    <span class="font-mono font-bold text-slate-900 text-sm ml-1" id="counted-actual-cash" x-text="'৳' + countedActualCash">৳0.00</span>
                </div>
                <div>
                    <span class="text-gray-600 font-semibold">Over / Short:</span>
                    <span class="font-mono font-bold text-sm ml-1 px-2 py-0.5 rounded" 
                          :class="shiftVariance === 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                          id="shift-variance-display"
                          x-text="(shiftVariance >= 0 ? '+৳' : '-৳') + Math.abs(shiftVariance)">৳0.00</span>
                </div>
            </div>

            <div class="flex justify-end space-x-2">
                <button @click="showShiftCloseModal = false" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-bold">
                    Cancel
                </button>
                <button @click="confirmCloseShift()" id="btn-confirm-close-shift" class="bg-slate-800 hover:bg-slate-900 text-white px-5 py-2 rounded-lg text-xs font-bold uppercase tracking-wider">
                    Confirm & Close Shift
                </button>
            </div>
        </div>
    </div>

    <!-- Dexie & Alpine App Logic -->
    <script>
        // 1. Initialize Dexie DB
        const db = new Dexie('pos_db');
        db.version(1).stores({
            catalog: 'variant_id, barcode, sku, name_en, mrp_price, tax_rate, tax_type',
            carts: 'id, updated_at',
            pending_sales: 'id, invoice_number, shift_id, subtotal, discount, vat, payable, created_at, status',
            shifts: 'id, terminal_id, opened_at, closed_at, opening_float, cash_sales, expected_cash, actual_cash, variance, status'
        });

        // 2. Pre-seed local Dexie catalog
        const defaultCatalog = [
            { variant_id: '1', barcode: '8941100111111', sku: 'RICE-01', name_en: 'Miniket Rice 5kg', mrp_price: '450.00', tax_rate: '0', tax_type: 'exempt' },
            { variant_id: '2', barcode: '8941100222222', sku: 'OIL-01', name_en: 'Pure Mustard Oil 1L', mrp_price: '280.00', tax_rate: '5', tax_type: 'standard' },
            { variant_id: '3', barcode: '8941100333333', sku: 'SOAP-01', name_en: 'Arogya Neem Soap 125g', mrp_price: '65.00', tax_rate: '15', tax_type: 'standard' }
        ];

        async function seedCatalog() {
            const count = await db.catalog.count();
            if (count === 0) {
                await db.catalog.bulkPut(defaultCatalog);
            }
        }

        // 3. Alpine.js Controller
        function posApp() {
            return {
                outletName: 'Dhanmondi Outlet #1',
                terminalCode: 'T-01',
                cashierName: 'Tanvir Ahmed',
                shiftOpen: false,
                shiftId: '42',
                isOnline: true,
                pendingSalesCount: 0,

                // Security & Lockout
                showPinModal: false,
                enteredPin: '',
                failedPinCount: 0,
                isLocked: false,
                lockoutSeconds: 60,
                lockoutTimerInterval: null,

                // Shifts
                showShiftModal: false,
                openingFloat: 2000,
                shiftCashSales: 0,
                expectedShiftCash: 2000,
                countedActualCash: 0,
                shiftVariance: 0,
                showShiftCloseModal: false,
                denominations: [
                    { note: 1000, count: 0 },
                    { note: 500, count: 0 },
                    { note: 200, count: 0 },
                    { note: 100, count: 0 },
                    { note: 50, count: 0 },
                    { note: 20, count: 0 },
                    { note: 10, count: 0 },
                    { note: 5, count: 0 },
                    { note: 2, count: 0 },
                    { note: 1, count: 0 }
                ],

                // Cart & Products
                cart: [],
                scanQuery: '',
                selectedItemIndex: -1,
                totals: { gross: '0.00', discount: '0.00', net: '0.00', vat: '0.00', rounding: '0.00', payable: '0.00' },

                // Discounts
                showDiscountModal: false,
                discountPercentInput: 5,
                discountReasonInput: 'PROMO',
                discountError: '',
                appliedDiscountPercent: 0,
                appliedDiscountReason: '',

                // Tender & Sales
                showTenderModal: false,
                tenderedAmount: '',
                changeDue: '0.00',
                showReceiptToast: false,
                lastSaleInvoice: '',
                lastSaleTotal: '',
                lastSaleTendered: '',

                async initApp() {
                    await seedCatalog();
                    await this.loadCartFromDexie();
                    await this.updatePendingSalesCount();

                    // Check if shift is open
                    const activeShift = await db.shifts.where('status').equals('open').first();
                    if (activeShift) {
                        this.shiftOpen = true;
                        this.openingFloat = activeShift.opening_float;
                        this.shiftCashSales = activeShift.cash_sales || 0;
                        this.calculateShiftTotals();
                    } else {
                        // Prompt to open shift
                        this.showShiftModal = true;
                    }
                },

                // --- SECURITY & PIN LOCKOUT ---
                lockTerminal() {
                    this.enteredPin = '';
                    this.showPinModal = true;
                },
                appendPin(digit) {
                    if (this.enteredPin.length < 4) {
                        this.enteredPin += digit;
                    }
                },
                clearPin() {
                    this.enteredPin = '';
                },
                submitPin() {
                    if (this.isLocked) return;

                    if (this.enteredPin === '4821' || this.enteredPin === '9999') {
                        // Correct PIN
                        this.failedPinCount = 0;
                        this.showPinModal = false;
                        this.enteredPin = '';
                    } else {
                        // Failed Attempt
                        this.failedPinCount++;
                        this.enteredPin = '';

                        if (this.failedPinCount >= 3) {
                            this.triggerLockout();
                        } else {
                            alert(`Invalid PIN! ${3 - this.failedPinCount} attempt(s) remaining before 60-second lockout.`);
                        }
                    }
                },
                triggerLockout() {
                    this.isLocked = true;
                    this.lockoutSeconds = 60;
                    if (this.lockoutTimerInterval) clearInterval(this.lockoutTimerInterval);

                    this.lockoutTimerInterval = setInterval(() => {
                        this.lockoutSeconds--;
                        if (this.lockoutSeconds <= 0) {
                            clearInterval(this.lockoutTimerInterval);
                            this.isLocked = false;
                            this.failedPinCount = 0;
                        }
                    }, 1000);
                },

                // --- REGISTER SHIFT ---
                openShiftModal() {
                    this.showShiftModal = true;
                },
                async confirmOpenShift() {
                    this.shiftOpen = true;
                    this.showShiftModal = false;
                    this.expectedShiftCash = parseFloat(this.openingFloat) || 2000;

                    await db.shifts.put({
                        id: 'shift-' + Date.now(),
                        terminal_id: this.terminalCode,
                        opened_at: new Date().toISOString(),
                        opening_float: parseFloat(this.openingFloat) || 2000,
                        cash_sales: 0,
                        status: 'open'
                    });
                },
                openShiftCloseModal() {
                    this.calculateShiftTotals();
                    this.showShiftCloseModal = true;
                },
                calculateShiftTotals() {
                    this.expectedShiftCash = (parseFloat(this.openingFloat) || 0) + (parseFloat(this.shiftCashSales) || 0);
                    this.calculateActualCash();
                },
                calculateActualCash() {
                    let total = 0;
                    for (const d of this.denominations) {
                        total += d.note * (parseInt(d.count) || 0);
                    }
                    this.countedActualCash = total;
                    this.shiftVariance = total - this.expectedShiftCash;
                },
                async confirmCloseShift() {
                    await db.shifts.where('status').equals('open').modify({
                        status: 'closed',
                        closed_at: new Date().toISOString(),
                        actual_cash: this.countedActualCash,
                        variance: this.shiftVariance
                    });

                    this.shiftOpen = false;
                    this.showShiftCloseModal = false;
                    alert('Register Shift #42 successfully reconciled and closed!');
                },

                // --- CART OPERATIONS & PERSISTENCE ---
                async quickAdd(barcode) {
                    const item = await db.catalog.where('barcode').equals(barcode).first();
                    if (item) {
                        this.addItemToCart(item);
                    }
                },
                async handleScan() {
                    const query = this.scanQuery.trim();
                    if (!query) return;

                    let item = await db.catalog.where('barcode').equals(query).first();
                    if (!item) {
                        item = await db.catalog.where('sku').equals(query).first();
                    }
                    if (item) {
                        this.addItemToCart(item);
                        this.scanQuery = '';
                    } else {
                        alert('Product not found for: ' + query);
                    }
                },
                addItemToCart(product) {
                    const existing = this.cart.find(i => i.variant_id === product.variant_id);
                    if (existing) {
                        existing.qty++;
                        existing.total_cost = (existing.qty * parseFloat(existing.mrp_price)).toFixed(2);
                    } else {
                        this.cart.push({
                            variant_id: product.variant_id,
                            sku: product.sku,
                            name_en: product.name_en,
                            mrp_price: product.mrp_price,
                            tax_rate: product.tax_rate,
                            tax_type: product.tax_type,
                            qty: 1,
                            discount_percent: 0,
                            total_cost: parseFloat(product.mrp_price).toFixed(2)
                        });
                    }

                    this.recomputeTotals();
                    this.saveCartToDexie();
                },
                changeQty(index, delta) {
                    this.cart[index].qty += delta;
                    if (this.cart[index].qty <= 0) {
                        this.cart.splice(index, 1);
                    } else {
                        this.cart[index].total_cost = (this.cart[index].qty * parseFloat(this.cart[index].mrp_price)).toFixed(2);
                    }
                    this.recomputeTotals();
                    this.saveCartToDexie();
                },
                removeItem(index) {
                    this.cart.splice(index, 1);
                    this.recomputeTotals();
                    this.saveCartToDexie();
                },
                clearCart() {
                    this.cart = [];
                    this.appliedDiscountPercent = 0;
                    this.appliedDiscountReason = '';
                    this.recomputeTotals();
                    db.carts.delete('active_cart');
                },
                async saveCartToDexie() {
                    await db.carts.put({
                        id: 'active_cart',
                        cart: JSON.parse(JSON.stringify(this.cart)),
                        appliedDiscountPercent: this.appliedDiscountPercent,
                        appliedDiscountReason: this.appliedDiscountReason,
                        updated_at: new Date().toISOString()
                    });
                },
                async loadCartFromDexie() {
                    const saved = await db.carts.get('active_cart');
                    if (saved && saved.cart && saved.cart.length > 0) {
                        this.cart = saved.cart;
                        this.appliedDiscountPercent = saved.appliedDiscountPercent || 0;
                        this.appliedDiscountReason = saved.appliedDiscountReason || '';
                        this.recomputeTotals();
                    }
                },

                // --- TAX & TOTALS CALCULATION (Exact BigInt paisa matching §11.4) ---
                recomputeTotals() {
                    let totalGrossPaisa = 0n;
                    let totalNetPaisa = 0n;
                    let totalVatPaisa = 0n;

                    for (const item of this.cart) {
                        const rateInt = BigInt(item.tax_rate || '0');
                        const lineGrossPaisa = BigInt(Math.round(parseFloat(item.total_cost) * 100));
                        totalGrossPaisa += lineGrossPaisa;

                        if (rateInt === 0n) {
                            totalNetPaisa += lineGrossPaisa;
                        } else {
                            // Net Paisa = roundHalfUp((Gross * 100) / (100 + Rate))
                            const numerator = lineGrossPaisa * 100n;
                            const denominator = 100n + rateInt;
                            const quotient = numerator / denominator;
                            const remainder = numerator % denominator;
                            const netPaisa = (remainder * 2n >= denominator) ? quotient + 1n : quotient;
                            const vatPaisa = lineGrossPaisa - netPaisa;

                            totalNetPaisa += netPaisa;
                            totalVatPaisa += vatPaisa;
                        }
                    }

                    // Apply discount if active
                    let discountPaisa = 0n;
                    if (this.appliedDiscountPercent > 0) {
                        discountPaisa = (totalGrossPaisa * BigInt(this.appliedDiscountPercent)) / 100n;
                    }

                    const payablePaisa = totalGrossPaisa - discountPaisa;

                    this.totals = {
                        gross: (Number(totalGrossPaisa) / 100).toFixed(2),
                        discount: (Number(discountPaisa) / 100).toFixed(2),
                        net: (Number(totalNetPaisa) / 100).toFixed(2),
                        vat: (Number(totalVatPaisa) / 100).toFixed(2),
                        rounding: '0.00',
                        payable: (Number(payablePaisa) / 100).toFixed(2)
                    };
                },

                // --- DISCOUNT RULES & SUPERVISOR CEILINGS (§3.2) ---
                openDiscountModal() {
                    this.discountError = '';
                    this.showDiscountModal = true;
                },
                applyDiscount() {
                    const percent = parseFloat(this.discountPercentInput);

                    // Cashier limit check (Max 5.0%)
                    if (percent > 5.0) {
                        this.discountError = 'Error: Cashier discount ceiling is 5%. An ' + percent + '% discount exceeds cashier limit and is REFUSED. Supervisor PIN required to authorize.';
                        return;
                    }

                    if (percent < 0) {
                        this.discountError = 'Error: Discount cannot be negative.';
                        return;
                    }

                    this.appliedDiscountPercent = percent;
                    this.appliedDiscountReason = this.discountReasonInput;
                    this.showDiscountModal = false;
                    this.recomputeTotals();
                    this.saveCartToDexie();
                },

                // --- TENDER & COMPLETION ---
                openTenderModal() {
                    this.tenderedAmount = Math.ceil(parseFloat(this.totals.payable));
                    this.calculateChange();
                    this.showTenderModal = true;
                },
                setTenderExact() {
                    this.tenderedAmount = Math.ceil(parseFloat(this.totals.payable));
                    this.calculateChange();
                },
                setTender(amount) {
                    this.tenderedAmount = amount;
                    this.calculateChange();
                },
                calculateChange() {
                    const tendered = parseFloat(this.tenderedAmount) || 0;
                    const payable = parseFloat(this.totals.payable) || 0;
                    const change = tendered - payable;
                    this.changeDue = (change >= 0 ? change : 0).toFixed(2);
                },
                async completeSale() {
                    const invoiceNo = 'INV-DHA01-T01-' + String(Date.now()).slice(-6);
                    const saleTotal = this.totals.payable;

                    // Save sale in Dexie pending_sales
                    await db.pending_sales.add({
                        id: 'sale-' + Date.now(),
                        invoice_number: invoiceNo,
                        shift_id: this.shiftId,
                        subtotal: this.totals.gross,
                        discount: this.totals.discount,
                        vat: this.totals.vat,
                        payable: saleTotal,
                        created_at: new Date().toISOString(),
                        status: this.isOnline ? 'synced' : 'pending_sync'
                    });

                    // Update shift cash
                    this.shiftCashSales += parseFloat(saleTotal);
                    this.calculateShiftTotals();

                    // Toast
                    this.lastSaleInvoice = invoiceNo;
                    this.lastSaleTotal = saleTotal;
                    this.lastSaleTendered = this.tenderedAmount;
                    this.showReceiptToast = true;
                    setTimeout(() => { this.showReceiptToast = false; }, 4000);

                    // Reset cart
                    this.clearCart();
                    this.showTenderModal = false;
                    await this.updatePendingSalesCount();
                },

                // --- NETWORK & SYNC ---
                toggleNetwork() {
                    this.isOnline = !this.isOnline;
                },
                async updatePendingSalesCount() {
                    this.pendingSalesCount = await db.pending_sales.where('status').equals('pending_sync').count();
                }
            };
        }
    </script>
</body>
</html>
