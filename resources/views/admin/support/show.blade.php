<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800">{{ $supportCase->reference }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ $types[$supportCase->type] ?? ucfirst(str_replace('_', ' ', $supportCase->type)) }} from {{ $supportCase->user?->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm p-6">
                    <h3 class="font-semibold text-gray-900">Conversation</h3>
                    <div class="mt-6 space-y-4">
                        @foreach($supportCase->messages as $message)
                            <div class="rounded-2xl p-4 {{ $message->sender_type === 'support' ? 'bg-sky-50 border border-sky-100' : 'bg-gray-50 border border-gray-100' }}">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $message->user?->name ?? ucfirst($message->sender_type) }}</p>
                                        <p class="text-xs text-gray-500">{{ ucfirst($message->sender_type) }}{{ $message->is_internal ? ' • Internal note' : '' }}</p>
                                    </div>
                                    <p class="text-xs text-gray-500">{{ $message->created_at->format('M d, Y H:i') }}</p>
                                </div>
                                <div class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $message->message }}</div>
                                @if(!empty($message->attachments))
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($message->attachments as $attachment)
                                            <a href="{{ $attachment }}" target="_blank" class="text-xs text-[#1FA774] hover:text-[#0D8B5E]">{{ $attachment }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @can('support.manage')
                        <form method="POST" action="{{ route('admin.support.message', $supportCase) }}" class="mt-6 space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Reply</label>
                                <textarea name="message" rows="5" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">{{ old('message') }}</textarea>
                                @error('message')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="is_internal" name="is_internal" value="1" @checked(old('is_internal'))>
                                <label for="is_internal" class="text-sm text-gray-600">Internal note only</label>
                            </div>
                            <button class="px-5 py-2.5 rounded-lg bg-[#1FA774] text-white hover:bg-[#0D8B5E] transition">Send Reply</button>
                        </form>
                    @endcan
                </div>

                <div class="bg-white rounded-2xl shadow-sm p-6 space-y-5">
                    <div>
                        <p class="text-sm text-gray-500">Subject</p>
                        <p class="font-medium text-gray-900 mt-1">{{ $supportCase->subject }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Description</p>
                        <div class="text-sm text-gray-700 whitespace-pre-line mt-1">{{ $supportCase->description }}</div>
                    </div>

                    @can('support.manage')
                        <form method="POST" action="{{ route('admin.support.update', $supportCase) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    @foreach($statuses as $value => $label)
                                        <option value="{{ $value }}" @selected($supportCase->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                                <select name="priority" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    @foreach($priorities as $value => $label)
                                        <option value="{{ $value }}" @selected($supportCase->priority === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Assign To</label>
                                <select name="assigned_to" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    <option value="">Unassigned</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" @selected($supportCase->assigned_to === $agent->id)>
                                            {{ $agent->name }} ({{ ucfirst(str_replace('_', ' ', $agent->role)) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button class="w-full px-5 py-2.5 rounded-lg bg-gray-900 text-white hover:bg-gray-800 transition">Update Case</button>
                        </form>
                    @endcan

                    <div class="border-t pt-5 space-y-3">
                        <div>
                            <p class="text-sm text-gray-500">Customer</p>
                            <p class="font-medium text-gray-900 mt-1">{{ $supportCase->user?->name }}</p>
                            <p class="text-sm text-gray-500">{{ $supportCase->user?->email }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Current Assignee</p>
                            <p class="font-medium text-gray-900 mt-1">{{ $supportCase->assignee?->name ?? 'Unassigned' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Source</p>
                            <p class="font-medium text-gray-900 mt-1">{{ ucfirst($supportCase->source) }}</p>
                        </div>
                        @if(!empty($supportCase->attachments))
                            <div>
                                <p class="text-sm text-gray-500">Attachments</p>
                                <div class="mt-2 space-y-2">
                                    @foreach($supportCase->attachments as $attachment)
                                        <a href="{{ $attachment }}" target="_blank" class="block text-sm text-[#1FA774] hover:text-[#0D8B5E]">{{ $attachment }}</a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
