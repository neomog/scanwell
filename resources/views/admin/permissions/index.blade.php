<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800">Permissions</h2>
            <a href="{{ route('admin.roles.index') }}" class="text-sm text-[#1FA774] hover:text-[#0D8B5E]">Back to roles</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Create Permission</h3>
                <form method="POST" action="{{ route('admin.permissions.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @csrf
                    <input type="text" name="name" placeholder="Permission name" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                    <input type="text" name="slug" placeholder="permission.slug" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    <input type="text" name="group" placeholder="Group" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    <input type="text" name="description" placeholder="Description" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                    <div class="md:col-span-2 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-[#1FA774] text-white rounded-lg hover:bg-[#0D8B5E] transition">Create Permission</button>
                    </div>
                </form>
            </div>

            @foreach($permissions as $group => $groupPermissions)
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">{{ ucfirst($group ?: 'general') }}</h3>
                    <div class="space-y-4">
                        @foreach($groupPermissions as $permission)
                            <form method="POST" action="{{ route('admin.permissions.update', $permission) }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                                @csrf
                                <input type="text" name="name" value="{{ $permission->name }}" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                                <input type="text" name="slug" value="{{ $permission->slug }}" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                                <input type="text" name="group" value="{{ $permission->group }}" class="rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                <div class="flex gap-3">
                                    <input type="text" name="description" value="{{ $permission->description }}" class="flex-1 rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                                    <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-700 transition">Save</button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
