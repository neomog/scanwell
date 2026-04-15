<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="{{ route('admin.users.index') }}" class="text-gray-500 hover:text-[#1FA774] transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800">Edit User</h2>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500">User ID:</span>
                <span class="text-sm font-mono bg-gray-100 px-2 py-1 rounded">{{ substr($user->id, 0, 8) }}...</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- User Preview Card -->
            <div class="bg-gradient-to-r from-[#1FA774] to-[#0D8B5E] rounded-t-xl p-6">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 rounded-full bg-white p-1 shadow-lg">
                        <div class="w-full h-full rounded-full bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] flex items-center justify-center text-white text-xl font-bold">
                            {{ strtoupper(substr($user->name, 0, 2)) }}
                        </div>
                    </div>
                    <div class="text-white">
                        <h3 class="text-xl font-bold">{{ $user->name }}</h3>
                        <p class="text-white/80 text-sm">{{ $user->email }}</p>
                        <div class="flex items-center space-x-2 mt-1">
                            @if($user->is_banned)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/20 text-white">
                                    Banned
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-500/20 text-white">
                                    Active
                                </span>
                            @endif
                            @if($user->role === 'admin')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-500/20 text-white">
                                    Administrator
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="bg-white rounded-b-xl shadow-sm p-6">
                @if(session('success'))
                    <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-r-lg">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-green-700">{{ session('success') }}</p>
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-red-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="flex-1">
                                <p class="text-red-700 font-medium mb-1">Please fix the following errors:</p>
                                <ul class="text-sm text-red-600 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('POST')

                    <!-- Name Field -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Full Name
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <input type="text"
                                   name="name"
                                   value="{{ old('name', $user->name) }}"
                                   class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1FA774] focus:border-transparent @error('name') border-red-500 @enderror"
                                   placeholder="Enter full name"
                                   required>
                        </div>
                        @error('name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email Field -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Email Address
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <input type="email"
                                   name="email"
                                   value="{{ old('email', $user->email) }}"
                                   class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1FA774] focus:border-transparent @error('email') border-red-500 @enderror"
                                   placeholder="user@example.com"
                                   required>
                        </div>
                        @error('email')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                        @if($user->email_verified_at)
                            <p class="mt-1 text-xs text-green-600">
                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Email verified
                            </p>
                        @else
                            <p class="mt-1 text-xs text-yellow-600">
                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Email not verified
                            </p>
                        @endif
                    </div>

                    <!-- Role Field -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            User Role
                            <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <select name="role"
                                    class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#1FA774] focus:border-transparent appearance-none">
                                <option value="user" {{ old('role', $user->role) == 'user' ? 'selected' : '' }}>👤 Regular User</option>
                                <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>👑 Administrator</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                        @if(auth()->id() === $user->id)
                            <p class="mt-1 text-xs text-yellow-600">
                                <svg class="w-3 h-3 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                Warning: Changing your own role may lock you out of admin access
                            </p>
                        @endif
                    </div>

                    <!-- Password Reset Option -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700">Reset Password</h4>
                                <p class="text-xs text-gray-500 mt-1">Send password reset link to user's email</p>
                            </div>
                            <a href="{{ route('admin.users.reset-password', $user) }}"
                               onclick="event.preventDefault(); document.getElementById('reset-password-form').submit();"
                               class="text-sm text-[#1FA774] hover:text-[#0D8B5E] font-medium">
                                Send Reset Link
                            </a>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200">
                        <a href="{{ route('admin.users.show', $user) }}"
                           class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-[#1FA774] text-white rounded-lg hover:bg-[#0D8B5E] transition shadow-sm">
                            Update User
                        </button>
                    </div>
                </form>

                <form id="reset-password-form" method="POST" action="{{ route('admin.users.reset-password', $user) }}" class="hidden">
                    @csrf
                </form>
            </div>

            <!-- Danger Zone (Optional) -->
            @if(auth()->id() !== $user->id)
                <div class="mt-6 bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="border-l-4 border-red-500 p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">Danger Zone</h3>
                                <p class="text-sm text-gray-500 mt-1">Irreversible actions for this user account</p>
                            </div>
                            @if($user->is_banned)
                                <form method="POST" action="{{ route('admin.users.unban', $user) }}">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                        Unban User
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.users.ban', $user) }}">
                                    @csrf
                                    <button type="submit"
                                            onclick="return confirm('Are you sure you want to ban this user? They will lose access to the platform.')"
                                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                        Ban User
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
