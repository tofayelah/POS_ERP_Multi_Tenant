<div class="bg-red-700 text-white px-4 py-2 text-sm font-semibold flex justify-between items-center sticky top-0 z-50 shadow-md">
    <div class="flex items-center space-x-2">
        <span>⚠️</span>
        <span>Active Support Access Session (Ticket #{{ $access->ticket_reference }}) &mdash; {{ $access->is_read_only ? 'Read-Only Mode' : 'Read-Write Mode' }}</span>
    </div>
    <div class="text-xs opacity-90">
        Expires: {{ $access->expires_at->toIso8601String() }}
    </div>
</div>
