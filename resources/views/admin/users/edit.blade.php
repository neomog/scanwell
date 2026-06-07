<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-[#1FA774] transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800">Edit User</h2>
            </div>
            <span class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">{{ substr($user->id, 0, 8) }}...</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-xl shadow-sm p-6 space-y-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] flex items-center justify-center text-white text-xl font-bold">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div>
                        <p class="text-xl font-semibold text-gray-900">{{ $user->name }}</p>
                        <p class="text-sm text-gray-500">{{ $user->email }}</p>
                        <p class="text-xs text-gray-500 mt-1">Current role: {{ $user->roleDefinition?->name ?? ucfirst(str_replace('_', ' ', $user->role)) }}</p>
                    </div>
                </div>

                @if($errors->any())
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                        <select name="role" class="w-full rounded-lg border-gray-300 focus:border-[#1FA774] focus:ring-[#1FA774]">
                            @foreach($roles as $role)
                                <option value="{{ $role->slug }}" {{ old('role', $user->role) === $role->slug ? 'selected' : '' }}>{{ $role->name }}</option>
                            @endforeach
                        </select>
                        @if(auth()->id() === $user->id)
                            <p class="mt-2 text-xs text-amber-700">Your own role cannot be changed from this screen.</p>
                        @endif
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.users.show', $user) }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">Cancel</a>
                        <button type="submit" class="px-4 py-2 bg-[#1FA774] text-white rounded-lg hover:bg-[#0D8B5E] transition">Update User</button>
                    </div>
                </form>

                @if(auth()->id() !== $user->id)
                    <div class="pt-4 border-t border-gray-200 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.users.ban', $user) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 {{ $user->is_banned ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} text-white rounded-lg transition">
                                {{ $user->is_banned ? 'Unban User' : 'Ban User' }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition">Send Reset Link</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
