<div class="bg-red-600 text-white px-4 py-2 text-sm font-semibold flex items-center justify-between shadow-md">
    <div class="flex items-center space-x-2">
        <svg class="w-5 h-5 inline-block shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span>
            <strong>TENANT SUSPENDED:</strong> {{ $tenant->name }} is currently suspended. Data entry, changes, and new POS shifts are blocked. Data viewing and statutory exports remain available (TEN-11).
        </span>
    </div>
    <span class="text-xs bg-red-800 px-2 py-0.5 rounded uppercase tracking-wider font-bold">Read-Only Mode</span>
</div>
