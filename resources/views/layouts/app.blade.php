<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
{{--    <body class="font-sans antialiased">--}}
{{--        <div class="min-h-screen bg-gray-100">--}}
{{--            @include('layouts.navigation')--}}

{{--            <!-- Page Heading -->--}}
{{--            @isset($header)--}}
{{--                <header class="bg-white shadow">--}}
{{--                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">--}}
{{--                        {{ $header }}--}}
{{--                    </div>--}}
{{--                </header>--}}
{{--            @endisset--}}

{{--            <!-- Page Content -->--}}
{{--            <main>--}}
{{--                {{ $slot }}--}}
{{--            </main>--}}
{{--        </div>--}}
{{--    </body>--}}

    <body class="font-sans antialiased">

    <div x-data="{ sidebarOpen: true }" class="min-h-screen flex bg-gray-100">

        <!-- Sidebar -->
        @include('layouts.sidebar')

        <!-- Main Content -->
        <div
            :class="sidebarOpen ? 'ml-64' : 'ml-20'"
            class="flex-1 flex flex-col transition-all duration-300"
        >
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

{{--            <main class="flex-1 p-6">--}}
{{--                {{ $slot }}--}}
{{--            </main>--}}

            <main class="flex-1 p-6">

                <!-- Flash Messages -->
                <div class="max-w-7xl mx-auto mb-4 space-y-3">

                    @if (session('success'))
                        <div
                            x-data="{ show: true }"
                            x-init="setTimeout(() => show = false, 4000)"
                            x-show="show"
                            x-transition
                            class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded"
                        >
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div
                            x-data="{ show: true }"
                            x-init="setTimeout(() => show = false, 5000)"
                            x-show="show"
                            x-transition
                            class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"
                        >
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div
                            x-data="{ show: true }"
                            x-init="setTimeout(() => show = false, 6000)"
                            x-show="show"
                            x-transition
                            class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded"
                        >
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                </div>

                <!-- Page Content -->
                {{ $slot }}

            </main>
        </div>

    </div>
    </body>
</html>
