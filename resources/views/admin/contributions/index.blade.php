<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Contributions</h2>
                <p class="text-sm text-gray-500">{{ $pendingCount }} pending reviews</p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        <form method="GET" class="bg-white rounded-xl shadow-sm p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search barcode, product, contributor" class="rounded-lg border-gray-300">
            <select name="status" class="rounded-lg border-gray-300">
                <option value="">All statuses</option>
                @foreach(['pending', 'approved', 'rejected', 'flagged'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <select name="change_type" class="rounded-lg border-gray-300">
                <option value="">All types</option>
                @foreach(['add', 'update', 'correct', 'report_issue'] as $type)
                    <option value="{{ $type }}" @selected(request('change_type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 rounded-lg bg-gray-900 text-white text-sm">Filter</button>
        </form>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                <tr>
                    <th class="px-6 py-3 text-left">Product</th>
                    <th class="px-6 py-3 text-left">Contributor</th>
                    <th class="px-6 py-3 text-left">Type</th>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3 text-left">Submitted</th>
                    <th class="px-6 py-3 text-right">Action</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($contributions as $contribution)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $contribution->product_name ?: 'Unnamed submission' }}</div>
                            <div class="text-xs text-gray-500">{{ $contribution->barcode }}</div>
                        </td>
                        <td class="px-6 py-4">{{ $contribution->user?->name ?: 'Unknown' }}</td>
                        <td class="px-6 py-4">{{ ucfirst(str_replace('_', ' ', $contribution->change_type)) }}</td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 rounded-full text-xs {{ $contribution->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ($contribution->status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700') }}">
                                {{ ucfirst($contribution->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500">{{ $contribution->created_at?->diffForHumans() }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('admin.contributions.show', $contribution) }}" class="text-[#1FA774] font-medium">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">No contributions found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{ $contributions->links() }}
    </div>
</x-app-layout>
