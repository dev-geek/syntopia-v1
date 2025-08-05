@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Free Plan Attempts Management</h1>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-blue-100 p-4 rounded-lg">
                <div class="text-2xl font-bold text-blue-600">{{ $stats['total_attempts'] }}</div>
                <div class="text-sm text-blue-800">Total Attempts</div>
            </div>
            <div class="bg-red-100 p-4 rounded-lg">
                <div class="text-2xl font-bold text-red-600">{{ $stats['blocked_attempts'] }}</div>
                <div class="text-sm text-red-800">Blocked Attempts</div>
            </div>
            <div class="bg-green-100 p-4 rounded-lg">
                <div class="text-2xl font-bold text-green-600">{{ $stats['unique_ips'] }}</div>
                <div class="text-sm text-green-800">Unique IPs</div>
            </div>
            <div class="bg-yellow-100 p-4 rounded-lg">
                <div class="text-2xl font-bold text-yellow-600">{{ $stats['unique_emails'] }}</div>
                <div class="text-sm text-yellow-800">Unique Emails</div>
            </div>
            <div class="bg-purple-100 p-4 rounded-lg">
                <div class="text-2xl font-bold text-purple-600">{{ $stats['recent_attempts'] }}</div>
                <div class="text-sm text-purple-800">Last 7 Days</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">IP Address</label>
                    <input type="text" name="ip" value="{{ request('ip') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" value="{{ request('email') }}" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="blocked" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All</option>
                        <option value="1" {{ request('blocked') === '1' ? 'selected' : '' }}>Blocked</option>
                        <option value="0" {{ request('blocked') === '0' ? 'selected' : '' }}>Not Blocked</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Days Back</label>
                    <select name="days" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="7" {{ request('days') == '7' ? 'selected' : '' }}>Last 7 days</option>
                        <option value="30" {{ request('days') == '30' ? 'selected' : '' }}>Last 30 days</option>
                        <option value="90" {{ request('days') == '90' ? 'selected' : '' }}>Last 90 days</option>
                        <option value="" {{ !request('days') ? 'selected' : '' }}>All time</option>
                    </select>
                </div>
                <div class="md:col-span-4 flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Filter
                    </button>
                    <a href="{{ route('admin.free-plan-attempts.index') }}" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Bulk Actions -->
        <div class="mb-4 flex gap-2">
            <button onclick="bulkAction('unblock')" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                Unblock Selected
            </button>
            <button onclick="bulkAction('block')" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                Block Selected
            </button>
            <button onclick="bulkAction('delete')" class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700">
                Delete Selected
            </button>
        </div>

        <!-- Attempts Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2">
                            <input type="checkbox" id="select-all" class="rounded">
                        </th>
                        <th class="px-4 py-2 text-left">ID</th>
                        <th class="px-4 py-2 text-left">IP Address</th>
                        <th class="px-4 py-2 text-left">Email</th>
                        <th class="px-4 py-2 text-left">User Agent</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attempts as $attempt)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <input type="checkbox" name="selected_ids[]" value="{{ $attempt->id }}" class="attempt-checkbox rounded">
                        </td>
                        <td class="px-4 py-2">{{ $attempt->id }}</td>
                        <td class="px-4 py-2 font-mono text-sm">{{ $attempt->ip_address }}</td>
                        <td class="px-4 py-2">{{ $attempt->email ?? 'N/A' }}</td>
                        <td class="px-4 py-2 text-xs text-gray-600 max-w-xs truncate" title="{{ $attempt->user_agent }}">
                            {{ Str::limit($attempt->user_agent, 50) }}
                        </td>
                        <td class="px-4 py-2">
                            @if($attempt->is_blocked)
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs">Blocked</span>
                            @else
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Active</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-600">
                            {{ $attempt->created_at->format('Y-m-d H:i:s') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            No attempts found matching your criteria.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6">
            {{ $attempts->links() }}
        </div>
    </div>
</div>

<!-- Bulk Action Forms -->
<form id="bulk-unblock-form" method="POST" action="{{ route('admin.free-plan-attempts.unblock') }}" style="display: none;">
    @csrf
    <input type="hidden" name="ids" id="unblock-ids">
</form>

<form id="bulk-block-form" method="POST" action="{{ route('admin.free-plan-attempts.block') }}" style="display: none;">
    @csrf
    <input type="hidden" name="ids" id="block-ids">
    <input type="hidden" name="reason" id="block-reason">
</form>

<form id="bulk-delete-form" method="POST" action="{{ route('admin.free-plan-attempts.destroy') }}" style="display: none;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="ids" id="delete-ids">
</form>

<script>
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.attempt-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

function bulkAction(action) {
    const selectedIds = Array.from(document.querySelectorAll('.attempt-checkbox:checked'))
        .map(checkbox => checkbox.value);

    if (selectedIds.length === 0) {
        alert('Please select at least one attempt.');
        return;
    }

    const idsJson = JSON.stringify(selectedIds);

    switch (action) {
        case 'unblock':
            document.getElementById('unblock-ids').value = idsJson;
            if (confirm('Are you sure you want to unblock the selected attempts?')) {
                document.getElementById('bulk-unblock-form').submit();
            }
            break;
        case 'block':
            const reason = prompt('Enter a reason for blocking (optional):');
            document.getElementById('block-ids').value = idsJson;
            document.getElementById('block-reason').value = reason || '';
            if (confirm('Are you sure you want to block the selected attempts?')) {
                document.getElementById('bulk-block-form').submit();
            }
            break;
        case 'delete':
            document.getElementById('delete-ids').value = idsJson;
            if (confirm('Are you sure you want to delete the selected attempts? This action cannot be undone.')) {
                document.getElementById('bulk-delete-form').submit();
            }
            break;
    }
}
</script>
@endsection 