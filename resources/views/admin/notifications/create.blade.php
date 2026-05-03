<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800">Create Notification</h2>
            <p class="text-sm text-gray-500 mt-1">Send an announcement or campaign to the app inbox, email, and push channels.</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.notifications.store') }}" class="bg-white rounded-2xl shadow-sm p-6 space-y-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                        <select name="type" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', 'announcement') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Schedule</label>
                        <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to send immediately after creation.</p>
                        @error('scheduled_at')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        @error('title')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Subject</label>
                        <input type="text" name="subject" value="{{ old('subject') }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                        <p class="text-xs text-gray-500 mt-1">Optional. Falls back to the title if omitted.</p>
                        @error('subject')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea name="body" rows="8" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">{{ old('body') }}</textarea>
                    @error('body')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CTA Label</label>
                        <input type="text" name="cta_label" value="{{ old('cta_label') }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">CTA URL</label>
                        <input type="url" name="cta_url" value="{{ old('cta_url') }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Channels</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        @foreach($channels as $value => $label)
                            <label class="flex items-center gap-3 rounded-xl border border-gray-200 p-4">
                                <input type="checkbox" name="channels[]" value="{{ $value }}" @checked(in_array($value, old('channels', ['in_app']), true))>
                                <span class="text-sm text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('channels')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    @error('channels.*')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Audience</label>
                        <select name="audience_type" id="audience_type" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                            @foreach($audiences as $value => $label)
                                <option value="{{ $value }}" @selected(old('audience_type', 'all_users') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="roles_audience" class="{{ old('audience_type') === 'roles' ? '' : 'hidden' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Roles</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($roles as $role)
                                <label class="flex items-center gap-3 rounded-xl border border-gray-200 p-4">
                                    <input type="checkbox" name="audience[role_slugs][]" value="{{ $role->slug }}" @checked(in_array($role->slug, old('audience.role_slugs', []), true))>
                                    <span class="text-sm text-gray-700">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('audience.role_slugs')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div id="users_audience" class="{{ old('audience_type') === 'users' ? '' : 'hidden' }}">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Users</label>
                        <select name="audience[user_ids][]" multiple size="8" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(in_array($user->id, old('audience.user_ids', []), true))>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Showing the first 100 users for manual targeting.</p>
                        @error('audience.user_ids')<p class="text-sm text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.notifications.index') }}" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">Cancel</a>
                    <button class="px-5 py-2.5 rounded-lg bg-[#1FA774] text-white hover:bg-[#0D8B5E] transition">Create Notification</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const audienceSelect = document.getElementById('audience_type');
            const rolesSection = document.getElementById('roles_audience');
            const usersSection = document.getElementById('users_audience');

            function syncAudienceSections() {
                rolesSection.classList.toggle('hidden', audienceSelect.value !== 'roles');
                usersSection.classList.toggle('hidden', audienceSelect.value !== 'users');
            }

            audienceSelect.addEventListener('change', syncAudienceSections);
            syncAudienceSections();
        });
    </script>
</x-app-layout>
