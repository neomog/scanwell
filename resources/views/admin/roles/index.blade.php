<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">Roles</h2>
            <a href="{{ route('admin.permissions.index') }}" class="text-sm text-[#1FA774] hover:text-[#0D8B5E]">Manage permissions</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Create Role</h3>
                <form method="POST" action="{{ route('admin.roles.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @csrf
                    <input type="text" name="name" placeholder="Role name" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                    <input type="text" name="slug" placeholder="role_slug" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    <textarea name="description" placeholder="Description" class="md:col-span-2 rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" rows="2"></textarea>

                    <div class="md:col-span-2">
                        <p class="text-sm font-medium text-gray-700 mb-3">Permissions</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($permissions as $group => $groupPermissions)
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <p class="text-sm font-semibold text-gray-800 mb-2">{{ ucfirst($group ?: 'general') }}</p>
                                    <div class="space-y-2">
                                        @foreach($groupPermissions as $permission)
                                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                                <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="mt-1 rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]">
                                                <span>{{ $permission->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-[#1FA774] text-white rounded-lg hover:bg-[#0D8B5E] transition">Create Role</button>
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                @foreach($roles as $role)
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <input type="text" name="name" value="{{ $role->name }}" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                                <input type="text" name="slug" value="{{ $role->slug }}" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                                <input type="text" name="description" value="{{ $role->description }}" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                @foreach($permissions as $group => $groupPermissions)
                                    <div class="rounded-lg border border-gray-200 p-4">
                                        <p class="text-sm font-semibold text-gray-800 mb-2">{{ ucfirst($group ?: 'general') }}</p>
                                        <div class="space-y-2">
                                            @foreach($groupPermissions as $permission)
                                                <label class="flex items-start gap-2 text-sm text-gray-700">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" {{ $role->permissions->contains('id', $permission->id) ? 'checked' : '' }} class="mt-1 rounded border-gray-300 text-[#1FA774] focus:ring-[#1FA774]">
                                                    <span>{{ $permission->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-500">{{ $role->is_system ? 'System role' : 'Custom role' }}</span>
                                <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition">Save Role</button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
