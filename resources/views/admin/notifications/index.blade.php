<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">Notifications</h2>
                <p class="text-sm text-gray-500 mt-1">Manage announcements, campaigns, push delivery, and email broadcasts.</p>
            </div>
            @can('notifications.manage')
                <a href="{{ route('admin.notifications.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#1FA774] text-white hover:bg-[#0D8B5E] transition">
                    <span>New Notification</span>
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-[#1FA774]">
                    <p class="text-sm text-gray-500">Total Campaigns</p>
                    <p class="text-2xl font-bold text-gray-900 mt-2">{{ $stats['total'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-blue-500">
                    <p class="text-sm text-gray-500">Scheduled</p>
                    <p class="text-2xl font-bold text-blue-600 mt-2">{{ $stats['scheduled'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-orange-500">
                    <p class="text-sm text-gray-500">Sent Today</p>
                    <p class="text-2xl font-bold text-orange-600 mt-2">{{ $stats['sent_today'] }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 border-l-4 border-purple-500">
                    <p class="text-sm text-gray-500">Announcements</p>
                    <p class="text-2xl font-bold text-purple-600 mt-2">{{ $stats['announcements'] }}</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title or message" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    <select name="type" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="">All types</option>
                        @foreach($types as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <option value="">All statuses</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2">
                        <button class="px-4 py-2 rounded-lg bg-[#1FA774] text-white hover:bg-[#0D8B5E] transition">Filter</button>
                        <a href="{{ route('admin.notifications.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Clear</a>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3 text-left">Notification</th>
                            <th class="px-6 py-3 text-left">Audience</th>
                            <th class="px-6 py-3 text-left">Channels</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3 text-left">Sent</th>
                            <th class="px-6 py-3 text-right">Action</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($campaigns as $campaign)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $campaign->title }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ ucfirst($campaign->type) }} by {{ $campaign->creator?->name ?? 'System' }}</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    {{ ucfirst(str_replace('_', ' ', $campaign->audience_type)) }}
                                    <div class="text-xs text-gray-500 mt-1">{{ number_format($campaign->recipients_count) }} recipients</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($campaign->channels ?? [] as $channel)
                                            <span class="px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">{{ strtoupper($channel) }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-medium
                                        {{ in_array($campaign->status, ['sent', 'partially_sent'], true) ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $campaign->status === 'scheduled' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $campaign->status === 'failed' ? 'bg-red-100 text-red-700' : '' }}
                                        {{ in_array($campaign->status, ['draft', 'processing'], true) ? 'bg-yellow-100 text-yellow-700' : '' }}">
                                        {{ ucfirst(str_replace('_', ' ', $campaign->status)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-500">
                                    {{ $campaign->sent_at?->diffForHumans() ?? ($campaign->scheduled_at?->format('M d, Y H:i') ?? 'Not sent') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.notifications.show', $campaign) }}" class="text-[#1FA774] hover:text-[#0D8B5E] font-medium">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">No notifications created yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if($campaigns->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $campaigns->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
