<aside
    :class="sidebarOpen ? 'w-64' : 'w-20'"
    class="bg-white border-r border-gray-200 h-screen fixed transition-all duration-300"
>
    <!-- Toggle Button -->
    <div class="flex items-center justify-between p-4">
        <span x-show="sidebarOpen" class="text-lg font-bold">LiveWise</span>

        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded hover:bg-gray-100">
            <!-- Menu Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="mt-4 space-y-2 px-2">

        <!-- Dashboard -->
        <a href="{{ route('dashboard') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition
           {{ request()->routeIs('dashboard')
                ? 'bg-gray-200 text-gray-900 font-semibold'
                : 'text-gray-600 hover:bg-gray-100' }}">

            <!-- Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 10l9-7 9 7v11a2 2 0 01-2 2h-4a2 2 0 01-2-2V12H9v9a2 2 0 01-2 2H3z"/>
            </svg>

            <!-- Label -->
            <span x-show="sidebarOpen">Dashboard</span>
        </a>

        <!-- Users -->
        <a href="{{ route('admin.users.index') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition
   {{ request()->routeIs('admin.users.*')
        ? 'bg-gray-200 text-gray-900 font-semibold'
        : 'text-gray-600 hover:bg-gray-100' }}">

            <!-- Icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M5.121 17.804A13.937 13.937 0 0112 15c2.485 0 4.78.64 6.879 1.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>

            <!-- Label -->
            <span x-show="sidebarOpen">Users</span>
        </a>

        <!-- Products -->
        <a href="{{ route('admin.products.index') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition
           {{ request()->routeIs('admin.products.*')
                ? 'bg-gray-200 text-gray-900 font-semibold'
                : 'text-gray-600 hover:bg-gray-100' }}">

            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M20 13V7a2 2 0 00-2-2h-4l-2-2H8a2 2 0 00-2 2v6m14 0l-8 5-8-5"/>
            </svg>

            <span x-show="sidebarOpen">Products</span>
        </a>

        <!-- Contributions -->
{{--        <a href="#"--}}
{{--           class="flex items-center gap-3 px-3 py-2 rounded-lg transition--}}
{{--           {{ request()->routeIs('contributions.*')--}}
{{--                ? 'bg-gray-200 text-gray-900 font-semibold'--}}
{{--                : 'text-gray-600 hover:bg-gray-100' }}">--}}

{{--            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">--}}
{{--                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"--}}
{{--                      d="M12 4v16m8-8H4"/>--}}
{{--            </svg>--}}

{{--            <span x-show="sidebarOpen">Contributions</span>--}}
{{--        </a>--}}
        <a href="{{ route('admin.contributions.index') }}"
           class="flex items-center justify-between px-3 py-2 rounded-lg transition
   {{ request()->routeIs('admin.contributions.*')
        ? 'bg-gray-200 text-gray-900 font-semibold'
        : 'text-gray-600 hover:bg-gray-100' }}">

            <!-- Left (Icon + Label) -->
            <div class="flex items-center gap-3">
                <!-- Icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 4v16m8-8H4"/>
                </svg>

                <span x-show="sidebarOpen" x-transition>Contributions</span>
            </div>

            <!-- Badge -->
            @if(pendingContributions() > 0)
                <span
                    class="ml-2 text-xs font-bold px-2 py-1 rounded-full bg-red-500 text-white"
                    :class="sidebarOpen ? '' : 'absolute right-2'"
                >
            {{ pendingContributions() }}
        </span>
            @endif
        </a>

        <a href="{{ route('admin.leaderboard.index') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition
           {{ request()->routeIs('admin.leaderboard.*')
                ? 'bg-gray-200 text-gray-900 font-semibold'
                : 'text-gray-600 hover:bg-gray-100' }}">

            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 21h8M12 17v4M7 4h10l-1 6a4 4 0 01-4 3 4 4 0 01-4-3L7 4z"/>
            </svg>

            <span x-show="sidebarOpen">Leaderboard</span>
        </a>

        <a href="{{ route('admin.plans.index') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition
           {{ request()->routeIs('admin.plans.*') || request()->routeIs('admin.prices.*')
                ? 'bg-gray-200 text-gray-900 font-semibold'
                : 'text-gray-600 hover:bg-gray-100' }}">

            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8c-2.21 0-4 .79-4 3v1h8v-1c0-2.21-1.79-3-4-3zm0-4a2 2 0 100 4 2 2 0 000-4zm7 9h2v7h-18v-7h2"/>
            </svg>

            <span x-show="sidebarOpen">Plans</span>
        </a>

        <a href="{{ route('admin.subscriptions.index') }}"
           class="flex items-center gap-3 px-3 py-2 rounded-lg transition
           {{ request()->routeIs('admin.subscriptions.*')
                ? 'bg-gray-200 text-gray-900 font-semibold'
                : 'text-gray-600 hover:bg-gray-100' }}">

            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 9V7a5 5 0 00-10 0v2M5 9h14l1 11H4L5 9zm3 4h.01M12 13h.01M16 13h.01"/>
            </svg>

            <span x-show="sidebarOpen">Subscriptions</span>
        </a>

    </nav>
</aside>
