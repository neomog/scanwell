<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Support Center</h2>
                <p class="text-sm text-gray-500 mt-1">Manage complaints, tickets, chat support conversations, and bug reports.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-[#1FA774]">
                    <p class="text-sm text-gray-500">Total Cases</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-orange-500">
                    <p class="text-sm text-gray-500">Open Queue</p>
                    <p class="text-2xl font-bold text-orange-600 mt-2">{{ $stats['open'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-rose-500">
                    <p class="text-sm text-gray-500">Bug Reports</p>
                    <p class="text-2xl font-bold text-rose-600 mt-2">{{ $stats['bugs'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-sky-500">
                    <p class="text-sm text-gray-500">Unassigned</p>
                    <p class="text-2xl font-bold text-sky-600 mt-2">{{ $stats['unassigned'] }}</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search reference, subject, user" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774] md:col-span-2">
                    <select name="type" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="">All types</option>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="">All statuses</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="priority" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="">All priorities</option>
                        @foreach($priorities as $value => $label)
                            <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="assigned" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="">All assignments</option>
                        <option value="me" @selected(request('assigned') === 'me')>Assigned to me</option>
                        <option value="unassigned" @selected(request('assigned') === 'unassigned')>Unassigned</option>
                    </select>
                    <div class="flex gap-2 md:col-span-6">
                        <button class="px-4 py-2 rounded-lg bg-[#1FA774] text-white hover:bg-[#0D8B5E] transition">Filter</button>
                        <a href="{{ route('admin.support.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Clear</a>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3 text-left">Case</th>
                            <th class="px-6 py-3 text-left">User</th>
                            <th class="px-6 py-3 text-left">Type</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3 text-left">Priority</th>
                            <th class="px-6 py-3 text-left">Assigned</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($cases as $case)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $case->reference }}</p>
                                        <p class="text-sm text-gray-700 mt-1">{{ $case->subject }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ $case->last_message_at?->diffForHumans() ?? $case->created_at->diffForHumans() }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-gray-900">{{ $case->user?->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $case->user?->email }}</p>
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $types[$case->type] ?? ucfirst(str_replace('_', ' ', $case->type)) }}</td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                        {{ in_array($case->status, ['resolved', 'closed'], true) ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                        {{ $statuses[$case->status] ?? ucfirst(str_replace('_', ' ', $case->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">
                                        {{ $priorities[$case->priority] ?? ucfirst($case->priority) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $case->assignee?->name ?? 'Unassigned' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.support.show', $case) }}" class="text-[#1FA774] hover:text-[#0D8B5E] font-medium">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">No support cases found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($cases->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $cases->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
