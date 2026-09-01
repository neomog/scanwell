<aside
    :class="sidebarOpen ? 'w-64' : 'w-20'"
    class="bg-white border-r border-gray-200 h-screen fixed transition-all duration-300"
>
    <div class="flex items-center justify-between p-4">
        <span x-show="sidebarOpen" class="text-lg font-bold">LabelWise</span>

        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded hover:bg-gray-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <nav class="mt-4 space-y-2 px-2">
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('dashboard') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10l9-7 9 7v11a2 2 0 01-2 2h-4a2 2 0 01-2-2V12H9v9a2 2 0 01-2 2H3z"/>
            </svg>
            <span x-show="sidebarOpen">Dashboard</span>
        </a>

        @can('users.view')
            <a href="{{ route('admin.users.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.users.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.485 0 4.78.64 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span x-show="sidebarOpen">Users</span>
            </a>
        @endcan

        @can('support.view')
            <a href="{{ route('admin.support.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.support.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5m-9 7h16a2 2 0 002-2V7a2 2 0 00-2-2h-3l-2-2H9L7 5H4a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span x-show="sidebarOpen">Support</span>
            </a>
        @endcan

        @can('products.view')
            <a href="{{ route('admin.products.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.products.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V7a2 2 0 00-2-2h-4l-2-2H8a2 2 0 00-2 2v6m14 0l-8 5-8-5"/>
                </svg>
                <span x-show="sidebarOpen">Products</span>
            </a>
        @endcan

        @can('submissions.view')
            <a href="{{ route('admin.contributions.index') }}"
               class="flex items-center justify-between px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.contributions.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span x-show="sidebarOpen" x-transition>Contributions</span>
                </div>

                @if(pendingContributions() > 0)
                    <span class="ml-2 text-xs font-bold px-2 py-1 rounded-full bg-red-500 text-white" :class="sidebarOpen ? '' : 'absolute right-2'">
                        {{ pendingContributions() }}
                    </span>
                @endif
            </a>
        @endcan

        @can('leaderboard.view')
            <a href="{{ route('admin.leaderboard.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.leaderboard.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 21h8M12 17v4M7 4h10l-1 6a4 4 0 01-4 3 4 4 0 01-4-3L7 4z"/>
                </svg>
                <span x-show="sidebarOpen">Leaderboard</span>
            </a>
        @endcan

        @can('notifications.view')
            <a href="{{ route('admin.notifications.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.notifications.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/>
                </svg>
                <span x-show="sidebarOpen">Notifications</span>
            </a>
        @endcan

        @can('scanning.manage')
            <a href="{{ route('admin.scanning.providers.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.scanning.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3v2.25M14.25 3v2.25M4.5 9.75h15M6.75 6h10.5A2.25 2.25 0 0119.5 8.25v9A2.25 2.25 0 0117.25 19.5H6.75A2.25 2.25 0 014.5 17.25v-9A2.25 2.25 0 016.75 6zm1.5 7.5h7.5m-7.5 3h4.5"/>
                </svg>
                <span x-show="sidebarOpen">Scanning</span>
            </a>
        @endcan

        @can('plans.manage')
            <a href="{{ route('admin.plans.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.plans.*') || request()->routeIs('admin.prices.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 .79-4 3v1h8v-1c0-2.21-1.79-3-4-3zm0-4a2 2 0 100 4 2 2 0 000-4zm7 9h2v7h-18v-7h2"/>
                </svg>
                <span x-show="sidebarOpen">Plans</span>
            </a>
        @endcan

        @can('subscriptions.manage')
            <a href="{{ route('admin.subscriptions.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.subscriptions.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a5 5 0 00-10 0v2M5 9h14l1 11H4L5 9zm3 4h.01M12 13h.01M16 13h.01"/>
                </svg>
                <span x-show="sidebarOpen">Subscriptions</span>
            </a>
        @endcan

        @can('billing.view')
            <a href="{{ route('admin.billing.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.billing.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.761 0-5 1.343-5 3v7h10v-7c0-1.657-2.239-3-5-3zm0-5a2 2 0 100 4 2 2 0 000-4zM5 18h14M4 21h16"/>
                </svg>
                <span x-show="sidebarOpen">Billing</span>
            </a>
        @endcan

        @can('roles.manage')
            <a href="{{ route('admin.roles.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.roles.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14c2.761 0 5-2.239 5-5S14.761 4 12 4 7 6.239 7 9s2.239 5 5 5zm0 0c-4.418 0-8 1.79-8 4v2h16v-2c0-2.21-3.582-4-8-4z"/>
                </svg>
                <span x-show="sidebarOpen">Roles</span>
            </a>
        @endcan

        @can('permissions.manage')
            <a href="{{ route('admin.permissions.index') }}"
               class="flex items-center gap-3 px-3 py-2 rounded-lg transition {{ request()->routeIs('admin.permissions.*') ? 'bg-gray-200 text-gray-900 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 .79-4 3s1.79 3 4 3 4-.79 4-3-1.79-3-4-3zm0-5l2.09 2.26 3.05-.17.53 3.01 2.74 1.33-1.33 2.74 1.33 2.74-2.74 1.33-.53 3.01-3.05-.17L12 21l-2.09-2.26-3.05.17-.53-3.01-2.74-1.33 1.33-2.74-1.33-2.74 2.74-1.33.53-3.01 3.05.17L12 3z"/>
                </svg>
                <span x-show="sidebarOpen">Permissions</span>
            </a>
        @endcan
    </nav>
</aside>
