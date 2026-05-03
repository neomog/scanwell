<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">{{ $campaign->title }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ ucfirst($campaign->type) }} targeting {{ ucfirst(str_replace('_', ' ', $campaign->audience_type)) }}</p>
            </div>
            <div class="flex items-center gap-3">
                @can('notifications.manage')
                    <form method="POST" action="{{ route('admin.notifications.send', $campaign) }}">
                        @csrf
                        <button class="px-4 py-2 rounded-lg bg-[#1FA774] text-white hover:bg-[#0D8B5E] transition">Send Again</button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-6">
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach($campaign->channels ?? [] as $channel)
                            <span class="px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium">{{ strtoupper($channel) }}</span>
                        @endforeach
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-medium">{{ strtoupper($campaign->status) }}</span>
                    </div>
                    <p class="text-sm text-gray-500 mb-2">Message</p>
                    <div class="text-gray-800 whitespace-pre-line leading-7">{{ $campaign->body }}</div>

                    @if($campaign->cta_url)
                        <div class="mt-6">
                            <a href="{{ $campaign->cta_url }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-900 text-white">
                                {{ $campaign->cta_label ?: 'Open Link' }}
                            </a>
                        </div>
                    @endif
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Created By</p>
                        <p class="font-medium text-gray-900 mt-1">{{ $campaign->creator?->name ?? 'System' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Audience Size</p>
                        <p class="font-medium text-gray-900 mt-1">{{ number_format($audienceCount) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Recipients Created</p>
                        <p class="font-medium text-gray-900 mt-1">{{ number_format($campaign->recipients_count) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Read Count</p>
                        <p class="font-medium text-gray-900 mt-1">{{ number_format($campaign->read_count) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Scheduled At</p>
                        <p class="font-medium text-gray-900 mt-1">{{ $campaign->scheduled_at?->format('M d, Y H:i') ?? 'Immediate' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Sent At</p>
                        <p class="font-medium text-gray-900 mt-1">{{ $campaign->sent_at?->format('M d, Y H:i') ?? 'Not sent' }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Delivery Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($campaign->delivery_summary ?? [] as $channel => $stats)
                        <div class="rounded-xl border border-gray-200 p-4">
                            <p class="text-sm font-medium text-gray-800 uppercase">{{ $channel }}</p>
                            <div class="mt-3 space-y-1 text-sm text-gray-600">
                                @foreach($stats as $label => $count)
                                    <p>{{ ucfirst($label) }}: {{ $count }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Recipients</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs">
                        <tr>
                            <th class="px-6 py-3 text-left">User</th>
                            <th class="px-6 py-3 text-left">Read Status</th>
                            <th class="px-6 py-3 text-left">Delivered</th>
                            <th class="px-6 py-3 text-left">Channel Results</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse($recipients as $recipient)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-medium text-gray-900">{{ $recipient->user?->name ?? 'Unknown user' }}</p>
                                    <p class="text-xs text-gray-500">{{ $recipient->user?->email }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    @if($recipient->read_at)
                                        <span class="px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium">Read</span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-medium">Unread</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-500">{{ $recipient->delivered_at?->diffForHumans() ?? 'Pending' }}</td>
                                <td class="px-6 py-4">
                                    <div class="space-y-1">
                                        @foreach($recipient->channel_statuses ?? [] as $channel => $status)
                                            <div class="text-xs text-gray-600">
                                                <span class="font-semibold uppercase">{{ $channel }}</span>: {{ ucfirst($status['status'] ?? 'pending') }}
                                                @if(!empty($status['message']))
                                                    <span class="text-gray-400">({{ $status['message'] }})</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-gray-500">No recipients generated yet.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @if($recipients->hasPages())
                    <div class="px-6 py-4 border-t border-gray-100">
                        {{ $recipients->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
