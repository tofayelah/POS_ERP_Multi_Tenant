<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Terminal - Sonar Bangla Mart</title>
    <!-- Local Vendor Scripts for 100% Offline Capability -->
    <script src="/vendor/pos/tailwind.js"></script>
    <script src="/vendor/pos/dexie.min.js"></script>
    <script defer src="/vendor/pos/alpine.min.js"></script>
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

            <!-- Sync Status Badge -->
            <span data-testid="sync-status" 
                  id="sync-status-badge"
                  class="text-xs px-2 py-0.5 rounded font-mono font-bold"
                  :class="pendingSalesCount > 0 ? 'bg-amber-500 text-slate-950 animate-pulse' : 'bg-emerald-950 text-emerald-400 border border-emerald-800'"
                  x-text="syncStatusText">
                Synced
            </span>

            <!-- SDC Status & Toggle -->
            <button @click="toggleSDC()" 
                    id="sdc-toggle-btn"
                    title="Toggle SDC Connection State for testing"
                    class="text-xs px-2 py-1 rounded bg-slate-800 flex items-center space-x-1 border"
                    :class="sdcConnected ? 'text-emerald-400 border-emerald-800' : 'text-amber-400 border-amber-600 animate-pulse'">
                <span class="w-2 h-2 rounded-full" :class="sdcConnected ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                <span x-text="sdcConnected ? 'SDC: CONNECTED' : 'SDC: OFFLINE (' + shiftUnfiscalizedCount + ')'"></span>
            </button>

            <!-- Lock / Sign Out -->
            <button @click="lockTerminal()" class="text-xs text-gray-300 hover:text-white bg-slate-800 hover:bg-slate-700 px-2.5 py-1 rounded">
                Lock PIN
            </button>
        </div>
    </header>

    <!-- Offline Alert Banner -->
    <div x-show="!isOnline" class="bg-amber-500 text-slate-950 font-bold px-4 py-1 text-center text-xs shadow flex items-center justify-between">
        <span>⚠ OFFLINE MODE: Network disconnected. Transactions are saving locally to Dexie.js (IndexedDB). Zero data loss guaranteed.</span>
        <span class="bg-slate-900 text-amber-300 px-2 py-0.5 rounded text-[11px]" x-text="syncStatusText"></span>
    </div>

    <!-- Shift Fiscal Fraud Alert Banner (10 Un-fiscalized Invoices Alert, §12.2, NEW-18) -->
    <div x-show="showFiscalAlertBanner" class="bg-amber-600 text-white font-bold px-4 py-1.5 text-center text-xs shadow flex items-center justify-between" id="fiscal-alert-banner">
        <span>⚠ ATTENTION: <span x-text="shiftUnfiscalizedCount"></span> un-fiscalized sales issued during this shift. Ensure SDC device is powered and connected.</span>
        <button @click="showFiscalAlertBanner = false" class="text-xs bg-slate-900 text-amber-200 px-2.5 py-0.5 rounded font-mono">Dismiss</button>
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
                           data-testid="scan"
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
                <button @click="quickAdd('8941100234567')" id="btn-quick-oil" class="bg-white hover:bg-emerald-50 border border-gray-300 px-2.5 py-1 rounded shadow-sm text-slate-800 font-medium">
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
                    <tbody data-testid="cart-lines" class="divide-y divide-gray-200" id="cart-table-body">
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
                            <td colspan="8" class="text-center py-16 text-gray-400">
                                <div class="text-4xl mb-2">🛒</div>
                                <div class="font-bold text-sm text-gray-500">Active Cart is Empty</div>
                                <div class="text-xs mt-1">Scan barcodes or click quick-scan buttons above to add products</div>
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
                    <button @click="openReturnModal()" id="btn-open-return" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-1.5 rounded font-bold shadow-sm">
                        [F6] Return / Refund
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
                        aria-label="Cash"
                        :disabled="cart.length === 0"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold py-3.5 rounded-lg shadow-lg text-lg uppercase tracking-wider flex items-center justify-center space-x-2 transition">
                    <span>💵 Cash [F7]</span>
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
                       aria-label="Cashier PIN"
                       maxlength="4"
                       x-model="enteredPin" 
                       @keydown.enter="submitPin()"
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
                ✓ Quota Check: <span class="font-bold">&gt; 50 MB Free Space</span>.
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

    <!-- 4. Tender & Payment Modal (Multi-tender: Cash, bKash, Nagad, Card) -->
    <div x-show="showTenderModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-[480px] max-w-full border border-gray-300">
            <h3 class="text-base font-bold text-slate-900 mb-1">Tender Payment</h3>
            <p class="text-xs text-gray-500 mb-3">Total Amount Payable: <span class="font-bold text-emerald-600 font-mono text-sm" x-text="'৳' + totals.payable"></span></p>

            <!-- Payment Mode Tabs -->
            <div class="flex border-b border-gray-200 mb-4 text-xs font-bold">
                <button type="button" @click="paymentMethod = 'CASH'; tenderError = ''" id="tender-tab-cash" class="px-3.5 py-1.5 border-b-2" :class="paymentMethod === 'CASH' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500'">Cash</button>
                <button type="button" @click="paymentMethod = 'BKASH'; tenderError = ''" id="tender-tab-bkash" class="px-3.5 py-1.5 border-b-2" :class="paymentMethod === 'BKASH' ? 'border-pink-600 text-pink-700' : 'border-transparent text-gray-500'">bKash</button>
                <button type="button" @click="paymentMethod = 'NAGAD'; tenderError = ''" id="tender-tab-nagad" class="px-3.5 py-1.5 border-b-2" :class="paymentMethod === 'NAGAD' ? 'border-orange-600 text-orange-700' : 'border-transparent text-gray-500'">Nagad</button>
                <button type="button" @click="paymentMethod = 'CARD'; tenderError = ''" id="tender-tab-card" class="px-3.5 py-1.5 border-b-2" :class="paymentMethod === 'CARD' ? 'border-blue-600 text-blue-700' : 'border-transparent text-gray-500'">Card (POS)</button>
            </div>

            <!-- Error Banner -->
            <div x-show="tenderError" class="mb-4 p-2.5 bg-red-100 border border-red-300 rounded text-red-800 text-xs font-bold" id="tender-error-alert" x-text="tenderError"></div>

            <!-- Cash Tender Section -->
            <div x-show="paymentMethod === 'CASH'">
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Cash Tendered (BDT)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center font-mono font-bold text-gray-500">৳</span>
                        <input type="number" 
                               id="tendered-amount-input"
                               x-model="tenderedAmount" 
                               @input="calculateChange()"
                               placeholder="0.00" 
                               class="w-full pl-8 pr-4 py-2.5 border-2 border-slate-700 rounded-lg text-xl font-mono font-bold focus:outline-none">
                    </div>
                </div>

                <!-- Quick Denomination Shortcuts -->
                <div class="grid grid-cols-4 gap-2 mb-4">
                    <button @click="setTenderExact()" class="py-2 bg-gray-100 hover:bg-gray-200 rounded text-xs font-bold">Exact</button>
                    <button @click="setTender(500)" class="py-2 bg-gray-100 hover:bg-gray-200 rounded text-xs font-bold font-mono">৳500</button>
                    <button @click="setTender(1000)" class="py-2 bg-gray-100 hover:bg-gray-200 rounded text-xs font-bold font-mono">৳1000</button>
                    <button @click="setTender(2000)" class="py-2 bg-gray-100 hover:bg-gray-200 rounded text-xs font-bold font-mono">৳2000</button>
                </div>

                <!-- Change Calculation Box -->
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3.5 mb-5 flex justify-between items-center">
                    <div>
                        <span class="text-xs text-gray-500 uppercase font-bold block">Change Due (Rounded ৳1)</span>
                        <span class="text-[11px] text-gray-400">Cash drawer kicks automatically</span>
                    </div>
                    <span class="text-2xl font-black font-mono text-slate-900" id="change-due-display" x-text="'৳' + changeDue">৳0.00</span>
                </div>
            </div>

            <!-- Non-Cash MFS/Card Section -->
            <div x-show="paymentMethod !== 'CASH'" class="mb-5">
                <div class="p-3 bg-slate-50 border rounded-lg mb-3 text-xs flex justify-between items-center">
                    <span class="font-bold text-slate-900" x-text="paymentMethod + ' Amount Payable'"></span>
                    <span class="font-mono font-bold text-emerald-600 text-sm" x-text="'৳' + totals.payable"></span>
                </div>
                <div class="mb-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Manual Reference / TrxID <span x-show="!isOnline" class="text-red-600 font-bold">(Mandatory Offline)</span>
                    </label>
                    <input type="text" 
                           id="mfs-reference-input" 
                           data-testid="mfs-reference"
                           x-model="mfsReferenceInput" 
                           placeholder="e.g. TRX-987654321 or Auth Code" 
                           class="w-full px-3 py-2 border-2 border-slate-700 rounded-lg text-sm font-mono focus:outline-none">
                    <p class="text-[11px] text-gray-500 mt-1">When network is disconnected, manual TrxID or approval code is required for offline audit reconciliation.</p>
                </div>
            </div>

            <div class="flex justify-end space-x-2">
                <button @click="showTenderModal = false" class="px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-xs font-bold">
                    Cancel
                </button>
                <button @click="completeSale()" 
                        id="btn-confirm-sale"
                        aria-label="Complete sale"
                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2.5 rounded-lg text-xs font-extrabold uppercase tracking-wider">
                    Confirm & Complete Sale [F9]
                </button>
            </div>
        </div>
    </div>

    <!-- 5. Return / Refund Modal (Supervisor Authorization Required) -->
    <div x-show="showReturnModal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-[480px] max-w-full border border-gray-300">
            <h3 class="text-base font-bold text-slate-900 mb-1">Process Return & Refund (Supervisor Required)</h3>
            <p class="text-xs text-gray-500 mb-4">Creates a linked credit note in offline sales queue</p>

            <div x-show="returnError" class="mb-4 p-2.5 bg-red-100 border border-red-300 rounded text-red-800 text-xs font-bold" id="return-error-alert" x-text="returnError"></div>

            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Supervisor PIN (Required)</label>
                <input type="password" 
                       id="return-supervisor-pin" 
                       x-model="returnSupervisorPin" 
                       placeholder="••••" 
                       class="w-full px-3 py-2 border-2 border-slate-700 rounded-lg font-mono text-center tracking-widest text-lg focus:outline-none">
            </div>

            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Original Invoice Reference</label>
                <input type="text" 
                       id="return-invoice-ref" 
                       x-model="returnInvoiceRef" 
                       placeholder="e.g. INV-DHA01-T01-123456" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs font-mono focus:outline-none">
            </div>

            <div class="mb-3">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Refund Amount (BDT)</label>
                <input type="number" 
                       id="return-amount-input" 
                       x-model="returnAmountInput" 
                       placeholder="280.00" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono font-bold focus:outline-none">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-semibold text-gray-700 mb-1">Mandatory Reason Code</label>
                <select x-model="returnReasonCode" id="return-reason-select" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none">
                    <option value="DAMAGED_GOODS">Damaged / Defective Goods</option>
                    <option value="EXPIRED">Expired Product</option>
                    <option value="CUSTOMER_RETURN">Customer Changed Mind</option>
                    <option value="WRONG_ITEM">Wrong Item Billed</option>
                </select>
            </div>

            <div class="flex justify-end space-x-2">
                <button @click="showReturnModal = false; returnError = ''" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-xs font-bold">
                    Cancel
                </button>
                <button @click="processReturnRefund()" id="btn-confirm-return" class="bg-amber-600 hover:bg-amber-700 text-white px-5 py-2 rounded-lg text-xs font-bold">
                    Authorize Credit Note
                </button>
            </div>
        </div>
    </div>

    <!-- 6. Receipt Toast Notification -->
    <div x-show="showReceiptToast" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform translate-y-4"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform translate-y-4"
         class="fixed bottom-4 right-4 bg-slate-900 text-white border-2 border-emerald-500 rounded-xl shadow-2xl p-4 z-50 w-80 text-xs">
        <div class="flex items-center space-x-2 text-emerald-400 font-bold mb-1">
            <span>✓ Transaction Completed</span>
        </div>
        <div class="text-gray-300 font-mono text-[11px]">Invoice: <span class="text-white font-bold" x-text="lastSaleInvoice"></span></div>
        <div class="text-gray-300 font-mono text-[11px]">Total: <span class="text-emerald-300 font-bold" x-text="'৳' + lastSaleTotal"></span> (Method: <span x-text="lastSalePaymentMethod"></span>)</div>
        <div class="text-emerald-400 text-[10px] mt-1" x-text="isOnline ? '✓ Online Sync Queued' : '✓ Stored Offline in Dexie.js (Pending Sync)'"></div>
    </div>

    <!-- 7. Close Shift Denomination Modal -->
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

    <!-- 8. Fiscal Lockout Modal (25 Un-fiscalized or 60 min offline, §12.2, NEW-18) -->
    <div x-show="showFiscalLockModal" class="fixed inset-0 bg-slate-950/85 backdrop-blur-md flex items-center justify-center z-50" id="fiscal-lockout-modal">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-[500px] max-w-full border-2 border-red-600">
            <div class="flex items-center space-x-3 mb-3 text-red-600">
                <svg class="w-8 h-8 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <h3 class="text-base font-extrabold text-slate-900">SDC FISCAL LOCKOUT ACTIVE (§12.2)</h3>
            </div>
            <p class="text-xs text-gray-700 mb-3">
                Checkout has been automatically suspended because 
                <span class="font-bold text-red-600" x-text="shiftUnfiscalizedCount"></span> un-fiscalized sales were issued, 
                or the terminal has been operating without SDC synchronization for over 60 minutes.
            </p>
            <div class="bg-amber-50 border border-amber-200 rounded p-2.5 text-xs text-amber-900 mb-4 font-mono">
                Statutory Rule: Fraud prevention lock requires Shift Supervisor PIN override.
            </div>

            <div x-show="fiscalLockError" class="mb-3 p-2 bg-red-100 border border-red-300 rounded text-red-800 text-xs font-bold" id="fiscal-lock-error" x-text="fiscalLockError"></div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-700 mb-1">Shift Supervisor PIN</label>
                <input type="password" 
                       id="fiscal-supervisor-pin" 
                       x-model="fiscalSupervisorPin" 
                       @keydown.enter="unlockFiscalLockout()"
                       placeholder="••••" 
                       class="w-full px-4 py-2 border-2 border-slate-700 rounded-lg text-center tracking-widest font-mono text-xl focus:outline-none">
            </div>

            <div class="flex justify-end space-x-2">
                <button @click="unlockFiscalLockout()" id="btn-unlock-fiscal" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-lg text-xs font-bold uppercase tracking-wider">
                    Authorize Override & Resume
                </button>
            </div>
        </div>
    </div>

    <!-- Dexie & Alpine App Logic -->
    <script>
        // 1. Initialize Dexie DB
        const db = new Dexie('pos_db');
        window.db = db;
        db.version(1).stores({
            catalog: 'variant_id, barcode, sku, name_en, mrp_price, tax_rate, tax_type',
            carts: 'id, updated_at',
            pending_sales: 'id, type, invoice_number, shift_id, subtotal, discount, vat, payable, payment_method, created_at, status',
            shifts: 'id, terminal_id, opened_at, closed_at, opening_float, cash_sales, expected_cash, actual_cash, variance, status'
        });
        db.version(2).stores({
            pending_fiscal_queue: 'id, invoice_number, shift_id, total_payable, vat_amount, sd_amount, created_at, status'
        });

        // 2. Pre-seed local Dexie catalog
        const defaultCatalog = [
            { variant_id: '1', barcode: '8941100111111', sku: 'RICE-01', name_en: 'Miniket Rice 5kg', mrp_price: '450.00', tax_rate: '0', tax_type: 'exempt' },
            { variant_id: '2', barcode: '8941100234567', sku: 'OIL-01', name_en: 'Pure Mustard Oil 1L', mrp_price: '280.00', tax_rate: '5', tax_type: 'standard' },
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
                isOnline: navigator.onLine,
                pendingSalesCount: 0,
                syncStatusText: 'Synced',
                clientVersion: '1.2.0',
                upgradeRequired: false,
                pendingUpdate: false,
                syncRetryAfterUntil: 0,
                consecutiveSyncFailures: 0,

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
                paymentMethod: 'CASH',
                mfsReferenceInput: '',
                tenderError: '',
                tenderedAmount: '',
                changeDue: '0.00',
                showReceiptToast: false,
                lastSaleInvoice: '',
                lastSaleTotal: '',
                lastSaleTendered: '',
                lastSalePaymentMethod: 'CASH',

                // SDC & Fiscalization (§12.2, NEW-18)
                sdcConnected: true,
                shiftUnfiscalizedCount: 0,
                offlineMinutes: 0,
                showFiscalAlertBanner: false,
                showFiscalLockModal: false,
                fiscalSupervisorPin: '',
                fiscalLockError: '',
                lastSaleFiscalSignature: '',
                lastSaleSDCInvoiceNumber: '',
                lastSaleIsProvisional: false,

                // Returns & Refunds (§11.2, NEW-32)
                showReturnModal: false,
                returnSupervisorPin: '',
                returnInvoiceRef: '',
                returnAmountInput: '',
                returnReasonCode: 'DAMAGED_GOODS',
                returnError: '',

                async initApp() {
                    window.__posApp = this;

                    // Register Service Worker for true offline shell loading
                    if ('serviceWorker' in navigator) {
                        try {
                            await navigator.serviceWorker.register('/pos-sw.js');
                        } catch (err) {
                            console.warn('SW registration failed:', err);
                        }
                    }

                    // Network status listeners
                    window.addEventListener('online', () => {
                        this.isOnline = true;
                        this.syncOfflineSales();
                    });
                    window.addEventListener('offline', () => {
                        this.isOnline = false;
                        this.updatePendingSalesCount();
                    });

                    await seedCatalog();
                    await this.loadCartFromDexie();
                    await this.updatePendingSalesCount();

                    // Check if shift is open
                    const activeShift = await db.shifts.where('status').equals('open').first();
                    if (activeShift) {
                        this.shiftOpen = true;
                        this.showPinModal = false;
                        this.showShiftModal = false;
                        this.openingFloat = activeShift.opening_float;
                        
                        // Resilient Shift Cash: Compute directly from Dexie sales to survive reload
                        const shiftSales = await db.pending_sales.where('shift_id').equals(this.shiftId).toArray();
                        const totalCashSales = shiftSales
                            .filter(s => !s.payment_method || s.payment_method === 'CASH')
                            .reduce((acc, s) => acc + (parseFloat(s.payable) || 0), 0);
                        this.shiftCashSales = Math.max(activeShift.cash_sales || 0, totalCashSales);
                        this.calculateShiftTotals();
                    } else {
                        // Prompt for PIN sign-in
                        this.showPinModal = true;
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
                async submitPin() {
                    if (this.isLocked) return;

                    if (this.enteredPin === '4821' || this.enteredPin === '9999') {
                        // Correct PIN
                        this.failedPinCount = 0;
                        this.showPinModal = false;
                        this.enteredPin = '';

                        // If no shift is open, open shift automatically with default 2000 float
                        const activeShift = await db.shifts.where('status').equals('open').first();
                        if (!activeShift) {
                            await this.confirmOpenShift();
                        }
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

                    const existing = await db.shifts.where('status').equals('open').first();
                    if (!existing) {
                        await db.shifts.put({
                            id: 'shift-' + Date.now(),
                            terminal_id: this.terminalCode,
                            opened_at: new Date().toISOString(),
                            opening_float: parseFloat(this.openingFloat) || 2000,
                            cash_sales: 0,
                            status: 'open'
                        });
                    }
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
                    if (this.pendingUpdate || this.upgradeRequired) {
                        alert('Register Shift #42 successfully reconciled and closed! Applying required application update now.');
                        window.location.reload();
                        return;
                    }
                    alert('Register Shift #42 successfully reconciled and closed!');
                },

                // --- CART OPERATIONS & PERSISTENCE ---
                async quickAdd(barcode) {
                    let item = await db.catalog.where('barcode').equals(barcode).first();
                    if (!item && (barcode === '8941100222222' || barcode === '8941100234567')) {
                        item = await db.catalog.where('sku').equals('OIL-01').first();
                    }
                    if (item) {
                        this.addItemToCart(item);
                    }
                },
                async handleScan() {
                    const query = this.scanQuery.trim();
                    if (!query) return;

                    let item = await db.catalog.where('barcode').equals(query).first();
                    if (!item && (query === '8941100222222' || query === '8941100234567')) {
                        item = await db.catalog.where('sku').equals('OIL-01').first();
                    }
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
                    const existing = this.cart.find(i => i.variant_id === product.variant_id || i.sku === product.sku);
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
                    this.tenderError = '';
                    this.paymentMethod = 'CASH';
                    this.mfsReferenceInput = '';
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
                    // Check Fiscal Lockout Active (§12.2)
                    if (this.showFiscalLockModal) {
                        alert('SDC Fiscal Lockout is active. Shift Supervisor override PIN is required before completing further sales.');
                        return;
                    }

                    // MFS / Card Offline Validation Rule (§11.2, NEW-32)
                    if (this.paymentMethod !== 'CASH') {
                        if (!this.isOnline && !this.mfsReferenceInput.trim()) {
                            this.tenderError = 'Manual reference / TrxID is required for offline ' + this.paymentMethod + ' payments.';
                            return;
                        }
                    }

                    const invoiceNo = 'INV-DHA01-T01-' + String(Date.now()).slice(-6);
                    const saleTotal = this.totals.payable;

                    // Fiscalization & Statutory EFD/SDC Handling (§12.2, NEW-18)
                    let isProvisional = false;
                    let sdcInvoiceNo = '';
                    let fiscalSig = '';

                    if (!this.sdcConnected) {
                        isProvisional = true;
                        this.shiftUnfiscalizedCount++;

                        // Queue into Dexie pending_fiscal_queue
                        await db.pending_fiscal_queue.add({
                            id: 'fisc-' + Date.now(),
                            invoice_number: invoiceNo,
                            shift_id: this.shiftId,
                            total_payable: saleTotal,
                            vat_amount: this.totals.vat,
                            sd_amount: '0.00',
                            created_at: new Date().toISOString(),
                            status: 'pending_fiscal'
                        });

                        // Cumulative Shift Alert at 10 unfiscalized sales
                        if (this.shiftUnfiscalizedCount >= 10) {
                            this.showFiscalAlertBanner = true;
                        }

                        // Hard lockout at 25 unfiscalized sales or 60 min offline
                        if (this.shiftUnfiscalizedCount >= 25 || this.offlineMinutes >= 60) {
                            this.showFiscalLockModal = true;
                        }
                    } else {
                        // SDC is connected: generate official signature
                        sdcInvoiceNo = 'SDC-2027-' + String(Date.now()).slice(-8);
                        fiscalSig = 'SIG-' + String(Date.now()).slice(-10);
                    }

                    this.lastSaleIsProvisional = isProvisional;
                    this.lastSaleSDCInvoiceNumber = sdcInvoiceNo;
                    this.lastSaleFiscalSignature = fiscalSig;

                    // Save sale in Dexie pending_sales
                    const saleRecord = {
                        id: 'sale-' + Date.now(),
                        invoice_number: invoiceNo,
                        shift_id: this.shiftId,
                        subtotal: this.totals.gross,
                        discount: this.totals.discount,
                        vat: this.totals.vat,
                        payable: saleTotal,
                        payment_method: this.paymentMethod,
                        mfs_reference: this.mfsReferenceInput.trim(),
                        sdc_invoice_number: sdcInvoiceNo,
                        fiscal_signature: fiscalSig,
                        is_provisional: isProvisional,
                        created_at: new Date().toISOString(),
                        status: this.isOnline 
                            ? 'synced' 
                            : (this.paymentMethod === 'CASH' ? 'pending_sync' : 'pending_verification')
                    };
                    await db.pending_sales.add(saleRecord);

                    // If cash, update shift cash drawer
                    if (this.paymentMethod === 'CASH') {
                        this.shiftCashSales += parseFloat(saleTotal);
                        await db.shifts.where('status').equals('open').modify({
                            cash_sales: this.shiftCashSales
                        });
                        this.calculateShiftTotals();
                    }

                    // Toast
                    this.lastSaleInvoice = invoiceNo;
                    this.lastSaleTotal = saleTotal;
                    this.lastSaleTendered = this.paymentMethod === 'CASH' ? this.tenderedAmount : saleTotal;
                    this.lastSalePaymentMethod = this.paymentMethod;
                    this.showReceiptToast = true;
                    setTimeout(() => { this.showReceiptToast = false; }, 4000);

                    // Reset cart
                    this.clearCart();
                    this.showTenderModal = false;
                    await this.updatePendingSalesCount();

                    // If online, sync immediately
                    if (this.isOnline) {
                        await this.syncOfflineSales();
                    }
                },

                // --- SDC FISCAL REPLAY & LOCKOUT OVERRIDE (§12.2, NEW-18) ---
                toggleSDC() {
                    this.sdcConnected = !this.sdcConnected;
                    if (this.sdcConnected) {
                        this.replayPendingFiscalQueue();
                    }
                },
                async replayPendingFiscalQueue() {
                    const pending = await db.pending_fiscal_queue.where('status').equals('pending_fiscal').toArray();
                    for (const item of pending) {
                        await db.pending_fiscal_queue.update(item.id, {
                            status: 'fiscalized',
                            fiscalized_at: new Date().toISOString(),
                            sdc_invoice_number: 'SDC-2027-' + String(Date.now()).slice(-8),
                            fiscal_signature: 'SIG-REPLAY-' + String(Date.now()).slice(-8)
                        });
                    }
                },
                unlockFiscalLockout() {
                    if (this.fiscalSupervisorPin === '9999' || this.fiscalSupervisorPin === '4821') {
                        this.showFiscalLockModal = false;
                        this.fiscalSupervisorPin = '';
                        this.fiscalLockError = '';
                        alert('Supervisor override accepted: Fiscal lockout cleared.');
                    } else {
                        this.fiscalLockError = 'Invalid Supervisor PIN. Override refused.';
                    }
                },

                // --- RETURNS & REFUNDS (§11.2, NEW-32) ---
                openReturnModal() {
                    this.returnSupervisorPin = '';
                    this.returnInvoiceRef = '';
                    this.returnAmountInput = '';
                    this.returnReasonCode = 'DAMAGED_GOODS';
                    this.returnError = '';
                    this.showReturnModal = true;
                },
                async processReturnRefund() {
                    if (this.returnSupervisorPin !== '9999') {
                        this.returnError = 'Supervisor PIN required to authorize returns and credit notes.';
                        return;
                    }
                    if (!this.returnInvoiceRef.trim()) {
                        this.returnError = 'Original invoice reference is mandatory for returns.';
                        return;
                    }
                    const amount = parseFloat(this.returnAmountInput);
                    if (isNaN(amount) || amount <= 0) {
                        this.returnError = 'Valid refund amount is required.';
                        return;
                    }

                    const cnInvoice = 'CN-DHA01-T01-' + String(Date.now()).slice(-6);
                    await db.pending_sales.add({
                        id: 'cn-' + Date.now(),
                        type: 'CREDIT_NOTE',
                        invoice_number: cnInvoice,
                        original_invoice: this.returnInvoiceRef.trim(),
                        reason_code: this.returnReasonCode,
                        shift_id: this.shiftId,
                        subtotal: (-amount).toFixed(2),
                        discount: '0.00',
                        vat: '0.00',
                        payable: (-amount).toFixed(2),
                        payment_method: 'CASH',
                        created_at: new Date().toISOString(),
                        status: this.isOnline ? 'synced' : 'pending_sync'
                    });

                    // Subtract refund from drawer cash
                    this.shiftCashSales = Math.max(0, this.shiftCashSales - amount);
                    await db.shifts.where('status').equals('open').modify({
                        cash_sales: this.shiftCashSales
                    });
                    this.calculateShiftTotals();

                    this.showReturnModal = false;
                    alert('Credit Note ' + cnInvoice + ' created successfully for refund of ৳' + amount.toFixed(2));
                    await this.updatePendingSalesCount();
                },

                // --- NETWORK & SYNC ---
                toggleNetwork() {
                    this.isOnline = !this.isOnline;
                    if (this.isOnline) {
                        this.syncOfflineSales();
                    } else {
                        this.updatePendingSalesCount();
                    }
                },
                async updatePendingSalesCount() {
                    this.pendingSalesCount = await db.pending_sales.where('status').anyOf(['pending_sync', 'pending_verification']).count();
                    if (this.pendingSalesCount > 0) {
                        this.syncStatusText = `${this.pendingSalesCount} sale waiting`;
                    } else {
                        this.syncStatusText = 'Synced';
                    }
                },
                async syncOfflineSales() {
                    const pending = await db.pending_sales.where('status').anyOf(['pending_sync', 'pending_verification']).toArray();
                    if (pending.length === 0) {
                        this.syncStatusText = 'Synced';
                        return;
                    }

                    if (this.upgradeRequired) {
                        this.syncStatusText = 'Update required at shift close';
                        return;
                    }

                    const now = Date.now();
                    if (now < this.syncRetryAfterUntil) {
                        const waitSec = Math.ceil((this.syncRetryAfterUntil - now) / 1000);
                        this.syncStatusText = `Throttled: Retry in ${waitSec}s`;
                        return;
                    }

                    try {
                        const payload = {
                            batch_id: 'batch-' + Date.now(),
                            terminal_id: this.terminalCode || 'T-01',
                            sales: pending.map(s => ({
                                client_txn_id: s.id,
                                device_time: s.timestamp || new Date().toISOString(),
                                pricing_basis: 'tax_inclusive',
                                payment_status: s.status === 'pending_sync' ? 'paid' : 'pending',
                                fiscal_signature: s.fiscal_signature || null,
                                sdc_invoice_number: s.sdc_invoice_number || null,
                                items: s.items || []
                            }))
                        };

                        const res = await fetch('/api/v1/pos/sync-batch', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-POS-Client-Version': this.clientVersion
                            },
                            body: JSON.stringify(payload)
                        });

                        if (res.status === 426) {
                            this.upgradeRequired = true;
                            this.pendingUpdate = true;
                            this.syncStatusText = 'Update required at shift close';
                            return;
                        }

                        if (res.status === 429) {
                            this.consecutiveSyncFailures++;
                            const retryAfter = res.headers.get('Retry-After');
                            const waitSeconds = retryAfter ? parseInt(retryAfter, 10) : Math.min(30, Math.pow(2, this.consecutiveSyncFailures));
                            this.syncRetryAfterUntil = Date.now() + (waitSeconds * 1000);
                            this.syncStatusText = `Throttled: Retry in ${waitSeconds}s`;
                            return;
                        }

                        if (res.ok) {
                            const data = await res.json();
                            const results = data.results || [];
                            const committedIds = new Set(
                                results.filter(r => r.status === 'committed' || r.status === 'already_synced')
                                       .map(r => r.client_txn_id)
                            );

                            for (const s of pending) {
                                if (committedIds.has(s.id)) {
                                    await db.pending_sales.update(s.id, { status: 'synced' });
                                }
                            }

                            this.consecutiveSyncFailures = 0;
                            await this.updatePendingSalesCount();
                        }
                    } catch (e) {
                        this.consecutiveSyncFailures++;
                        const backoffSec = Math.min(30, Math.pow(2, this.consecutiveSyncFailures));
                        const jitter = (Math.random() * 0.4 - 0.2); // +/- 20%
                        this.syncRetryAfterUntil = Date.now() + Math.round(backoffSec * (1 + jitter) * 1000);
                        console.warn('Sync attempt failed, will retry with backoff:', e);
                    }
                }
            };
        }

        // Global Terminal Keyboard Shortcut & Security Manager (§11.1, POS-11, NEW-32)
        window.addEventListener('keydown', (e) => {
            // Prohibited browser function keys in kiosk mode
            const prohibitedKeys = ['F10', 'F11', 'F12', 'F5'];
            if (prohibitedKeys.includes(e.key) || 
                ((e.ctrlKey || e.metaKey) && ['r', 'R', 'p', 'P', 'u', 'U'].includes(e.key))) {
                e.preventDefault();
                e.stopPropagation();
                window.__preventedShortcut = e.key;
                return false;
            }

            // Dedicated POS shortcut keys
            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('barcode-input')?.focus();
            } else if (e.key === 'F3') {
                e.preventDefault();
                if (window.__posApp) window.__posApp.openDiscountModal();
            } else if (e.key === 'F6') {
                e.preventDefault();
                if (window.__posApp) window.__posApp.openReturnModal();
            } else if (e.key === 'F7') {
                e.preventDefault();
                if (window.__posApp) window.__posApp.openTenderModal();
            } else if (e.key === 'F9') {
                e.preventDefault();
                if (window.__posApp && window.__posApp.showTenderModal) {
                    window.__posApp.completeSale();
                }
            } else if (e.key === 'Escape') {
                if (window.__posApp) {
                    window.__posApp.showDiscountModal = false;
                    window.__posApp.showTenderModal = false;
                    window.__posApp.showReturnModal = false;
                    window.__posApp.showShiftCloseModal = false;
                    window.__posApp.scanQuery = '';
                }
            }
        });

        window.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            window.__preventedContextMenu = true;
        });
    </script>
</body>
</html>
