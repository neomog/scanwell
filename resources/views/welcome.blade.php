<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ScanWell - Smart Product Scanner & Health Analyzer</title>
    <meta name="description" content="Scan product barcodes, analyze ingredients, get health scores, and make informed purchasing decisions with ScanWell.">

    <!-- Fonts & Tailwind CDN (fully standalone, no build deps needed) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Override Tailwind default config to match brand colors and smooth scroll -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
        }
        /* custom brand utilities */
        .bg-brand {
            background-color: #1FA774;
        }
        .bg-brand-dark {
            background-color: #0D8B5E;
        }
        .text-brand {
            color: #1FA774;
        }
        .border-brand {
            border-color: #1FA774;
        }
        .hover\:bg-brand-dark:hover {
            background-color: #0D8B5E;
        }
        .hover\:text-brand:hover {
            color: #1FA774;
        }
        .focus\:ring-brand:focus {
            --tw-ring-color: #1FA774;
        }
        .shadow-brand {
            box-shadow: 0 10px 25px -5px rgba(31, 167, 116, 0.2);
        }
        /* custom placeholder for star ratings */
        .star-filled {
            color: #1FA774;
            fill: currentColor;
        }
        .star-empty {
            color: #e5e7eb;
            fill: currentColor;
        }
        /* gradient custom */
        .bg-gradient-brand {
            background: linear-gradient(135deg, #1FA774 0%, #0D8B5E 100%);
        }
        .hero-blur {
            background: radial-gradient(circle at 70% 30%, rgba(31,167,116,0.08) 0%, rgba(255,255,255,0) 70%);
        }
        @keyframes soft-pulse {
            0%, 100% { opacity: 0.6; transform: scale(1);}
            50% { opacity: 1; transform: scale(1.05);}
        }
        .animate-soft-pulse {
            animation: soft-pulse 2s ease-in-out infinite;
        }
        /* fix for mobile tap highlight */
        button, a {
            -webkit-tap-highlight-color: transparent;
        }
    </style>
</head>
<body class="antialiased overflow-x-hidden">

<!-- Navigation - fully responsive sticky with backdrop blur -->
<nav class="fixed w-full bg-white/95 backdrop-blur-md shadow-sm z-50 transition-all duration-300 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
        <div class="flex justify-between items-center h-16 md:h-18">
            <!-- Logo -->
            <div class="flex items-center space-x-2.5">
                <div class="w-9 h-9 bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] rounded-xl flex items-center justify-center shadow-md">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <span class="text-xl font-extrabold tracking-tight text-gray-900">ScanWell</span>
            </div>

            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex items-center space-x-8">
                <a href="#features" class="text-gray-600 hover:text-[#1FA774] transition font-medium text-[15px]">Features</a>
                <a href="#how-it-works" class="text-gray-600 hover:text-[#1FA774] transition font-medium text-[15px]">How It Works</a>
                <a href="#pricing" class="text-gray-600 hover:text-[#1FA774] transition font-medium text-[15px]">Pricing</a>
                <a href="#testimonials" class="text-gray-600 hover:text-[#1FA774] transition font-medium text-[15px]">Testimonials</a>
            </div>

            <!-- Auth Buttons (static demo ready) -->
            <div class="flex items-center space-x-3">
                @if (Route::has('login'))
                    <nav class="flex items-center justify-end gap-4">
                        @auth
                            <a
                                href="{{ url('/dashboard') }}"
                                class="text-gray-700 hover:text-gray-900 font-semibold text-sm md:text-base transition"
                            >
                                Dashboard
                            </a>
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="text-gray-700 hover:text-gray-900 font-semibold text-sm md:text-base transition">Sign In</a>

                            @if (Route::has('register'))
                                <a
                                    href="{{ route('register') }}"
                                    class="bg-[#1FA774] text-white px-5 py-2.5 rounded-xl hover:bg-[#0D8B5E] transition-all duration-200 font-semibold text-sm shadow-sm hover:shadow-md">
                                    Get Started
                                </a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </div>
        </div>
    </div>
</nav>

<!-- Hero Section - fixed alignment and image placeholder -->
<section class="relative pt-32 pb-20 md:pt-44 md:pb-28 bg-white overflow-hidden">
    <div class="absolute inset-0 hero-blur pointer-events-none"></div>
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10 relative z-2">
        <div class="grid md:grid-cols-2 gap-12 lg:gap-16 items-center">
            <!-- Hero Content -->
            <div>
                <div class="inline-flex items-center px-4 py-1.5 rounded-full bg-green-50 text-[#1FA774] text-sm font-semibold mb-6 border border-green-100">
                    <span class="w-2.5 h-2.5 bg-[#1FA774] rounded-full mr-2 animate-pulse"></span>
                    Trusted by 50,000+ users
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-gray-900 leading-tight mb-6">
                    Scan. Analyze.
                    <span class="text-[#1FA774] bg-gradient-to-r from-[#1FA774] to-[#0D8B5E] bg-clip-text text-transparent">Decide Better.</span>
                </h1>

                <p class="text-lg text-gray-600 mb-8 leading-relaxed max-w-lg">
                    Instantly analyze product ingredients, get comprehensive health scores, and make informed purchasing decisions with ScanWell's advanced scanning technology.
                </p>

                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="#" class="inline-flex items-center justify-center px-6 py-3.5 bg-[#1FA774] text-white font-bold rounded-xl hover:bg-[#0D8B5E] transition-all shadow-lg hover:shadow-xl text-base">
                        Start Free Trial
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </a>
                    <a href="#how-it-works" class="inline-flex items-center justify-center px-6 py-3.5 border-2 border-gray-300 text-gray-700 font-bold rounded-xl hover:border-[#1FA774] hover:text-[#1FA774] transition duration-200">
                        Watch Demo
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                        </svg>
                    </a>
                </div>

                <!-- Trust Badges -->
                <div class="flex items-center space-x-5 mt-10 pt-5 border-t border-gray-200">
                    <div class="flex -space-x-2">
                        <div class="w-9 h-9 rounded-full bg-gray-200 border-2 border-white flex items-center justify-center text-xs font-bold text-gray-600">JD</div>
                        <div class="w-9 h-9 rounded-full bg-gray-300 border-2 border-white flex items-center justify-center text-xs font-bold text-gray-700">MK</div>
                        <div class="w-9 h-9 rounded-full bg-gray-400 border-2 border-white flex items-center justify-center text-xs font-bold text-white">SR</div>
                        <div class="w-9 h-9 rounded-full bg-[#1FA774] border-2 border-white flex items-center justify-center text-white text-xs font-bold">+</div>
                    </div>
                    <p class="text-sm text-gray-600">Join <span class="font-bold text-gray-900">50,000+</span> health-conscious users</p>
                </div>
            </div>

            <!-- Hero Image - replaced placeholder with better design / phone mockup style -->
            <div class="relative mt-8 md:mt-0">
                <div class="absolute inset-0 bg-gradient-to-r from-[#1FA774]/20 to-[#0D8B5E]/20 rounded-3xl blur-3xl"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100">
                    <div class="bg-gradient-to-br from-gray-50 to-white p-5 flex flex-col items-center">
                        <div class="w-full bg-gray-900 rounded-3xl overflow-hidden shadow-xl">
                            <div class="bg-[#1FA774] px-4 py-3 flex items-center space-x-2">
                                <div class="w-3 h-3 rounded-full bg-white/30"></div>
                                <div class="w-3 h-3 rounded-full bg-white/30"></div>
                                <div class="w-3 h-3 rounded-full bg-white/30"></div>
                                <span class="text-white text-xs font-mono ml-2">ScanWell Scanner</span>
                            </div>
                            <div class="p-5 bg-white">
                                <div class="flex items-center space-x-3 mb-4">
                                    <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center border">
                                        <svg class="w-6 h-6 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg>
                                    </div>
                                    <div><p class="font-bold text-gray-900">Organic Granola Bar</p><p class="text-xs text-gray-500">Barcode: 890123456789</p></div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-xl mb-3"><div class="flex justify-between"><span class="text-sm font-semibold">Health Score</span><span class="text-[#1FA774] font-extrabold">92/100</span></div><div class="w-full bg-gray-200 rounded-full h-2 mt-1"><div class="bg-[#1FA774] h-2 rounded-full w-[92%]"></div></div></div>
                                <div class="flex justify-between text-xs text-gray-600"><span>✔ No preservatives</span><span>✔ Low sugar</span><span>✔ High fiber</span></div>
                            </div>
                        </div>
                        <p class="text-center text-gray-500 text-xs mt-4">Scan any product → get instant insights</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-14 bg-white border-y border-gray-100">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div><div class="text-3xl md:text-4xl font-extrabold text-[#1FA774]">500K+</div><p class="text-gray-600 mt-1 font-medium">Products Scanned</p></div>
            <div><div class="text-3xl md:text-4xl font-extrabold text-[#1FA774]">50K+</div><p class="text-gray-600 mt-1 font-medium">Active Users</p></div>
            <div><div class="text-3xl md:text-4xl font-extrabold text-[#1FA774]">10K+</div><p class="text-gray-600 mt-1 font-medium">Daily Scans</p></div>
            <div><div class="text-3xl md:text-4xl font-extrabold text-[#1FA774]">98%</div><p class="text-gray-600 mt-1 font-medium">Satisfaction Rate</p></div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
        <div class="text-center mb-14"><h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-4">Powerful Features for Smart Choices</h2><p class="text-lg text-gray-600 max-w-2xl mx-auto">Everything you need to make informed decisions about the products you buy</p></div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-lg transition-all border border-gray-100 group"><div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-5 group-hover:bg-green-100 transition"><svg class="w-6 h-6 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path></svg></div><h3 class="text-xl font-bold text-gray-900 mb-2">Instant Barcode Scan</h3><p class="text-gray-600">Simply point your camera at any product barcode for instant analysis and detailed insights.</p></div>
            <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-lg transition-all border border-gray-100"><div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-5"><svg class="w-6 h-6 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg></div><h3 class="text-xl font-bold text-gray-900 mb-2">Health Score Analysis</h3><p class="text-gray-600">Get comprehensive nutritional scores and ingredient analysis with clear, easy-to-understand ratings.</p></div>
            <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-lg transition-all border border-gray-100"><div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-5"><svg class="w-6 h-6 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></div><h3 class="text-xl font-bold text-gray-900 mb-2">Community Contributions</h3><p class="text-gray-600">Join thousands of users contributing to a healthier world by sharing product insights and reviews.</p></div>
            <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-lg transition-all border border-gray-100"><div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-5"><svg class="w-6 h-6 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></div><h3 class="text-xl font-bold text-gray-900 mb-2">Advanced Analytics</h3><p class="text-gray-600">Track your scanning history, see trends, and get personalized recommendations based on your preferences.</p></div>
            <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-lg transition-all border border-gray-100"><div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-5"><svg class="w-6 h-6 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg></div><h3 class="text-xl font-bold text-gray-900 mb-2">Product Database</h3><p class="text-gray-600">Access millions of products with detailed information including ingredients, nutrition, and allergens.</p></div>
            <div class="bg-white rounded-2xl p-7 shadow-sm hover:shadow-lg transition-all border border-gray-100"><div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-5"><svg class="w-6 h-6 text-[#1FA774]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div><h3 class="text-xl font-bold text-gray-900 mb-2">Lightning Fast</h3><p class="text-gray-600">Get results in seconds with our optimized scanning technology and real-time database queries.</p></div>
        </div>
    </div>
</section>

<!-- How It Works -->
<section id="how-it-works" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
        <div class="text-center mb-16"><h2 class="text-3xl md:text-4xl font-extrabold text-gray-900">How ScanWell Works</h2><p class="text-lg text-gray-600 max-w-2xl mx-auto mt-3">Get started in three simple steps and start making healthier choices today</p></div>
        <div class="grid md:grid-cols-3 gap-12 text-center">
            <div><div class="relative"><div class="w-20 h-20 bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg"><span class="text-white text-2xl font-bold">1</span></div><div class="hidden md:block absolute top-10 left-1/2 w-full h-0.5 bg-gray-200 -z-0"></div></div><h3 class="text-xl font-bold text-gray-900 mb-2">Create Account</h3><p class="text-gray-600">Sign up for free and join our community of health-conscious consumers</p></div>
            <div><div class="w-20 h-20 bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg"><span class="text-white text-2xl font-bold">2</span></div><h3 class="text-xl font-bold text-gray-900 mb-2">Scan Any Product</h3><p class="text-gray-600">Simply point your camera at any product barcode to get instant analysis</p></div>
            <div><div class="w-20 h-20 bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg"><span class="text-white text-2xl font-bold">3</span></div><h3 class="text-xl font-bold text-gray-900 mb-2">Make Informed Choices</h3><p class="text-gray-600">View health scores, ingredient analysis, and make better purchasing decisions</p></div>
        </div>
    </div>
</section>

<!-- Pricing Section -->
<section id="pricing" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
        <div class="text-center mb-14"><h2 class="text-3xl md:text-4xl font-extrabold text-gray-900">Simple, Transparent Pricing</h2><p class="text-gray-600 text-lg mt-2">Choose the plan that works best for you</p></div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="bg-white rounded-2xl shadow-md overflow-hidden border border-gray-200 transition hover:shadow-xl"><div class="p-8"><h3 class="text-2xl font-bold text-gray-900">Free</h3><p class="text-gray-500 mt-1">Perfect for getting started</p><div class="mt-5 mb-6"><span class="text-5xl font-extrabold text-gray-900">$0</span><span class="text-gray-500">/month</span></div><ul class="space-y-3 mb-8"><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Up to 50 scans/month</li><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Basic health scores</li><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Community support</li></ul><a href="#" class="block text-center py-3 border-2 border-gray-300 rounded-xl font-bold text-gray-700 hover:border-[#1FA774] hover:text-[#1FA774] transition">Get Started</a></div></div>
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden border-2 border-[#1FA774] relative"><div class="absolute top-0 right-0 bg-[#1FA774] text-white px-4 py-1 rounded-bl-xl text-sm font-bold">POPULAR</div><div class="p-8"><h3 class="text-2xl font-bold text-gray-900">Pro</h3><p class="text-gray-500 mt-1">For health-conscious individuals</p><div class="mt-5 mb-6"><span class="text-5xl font-extrabold text-gray-900">$9.99</span><span class="text-gray-500">/month</span></div><ul class="space-y-3 mb-8"><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Unlimited scans</li><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Advanced health analytics</li><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Personalized recommendations</li><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Priority support</li></ul><a href="#" class="block text-center py-3 bg-[#1FA774] text-white font-bold rounded-xl hover:bg-[#0D8B5E] transition shadow-md">Start Free Trial</a></div></div>
            <div class="bg-white rounded-2xl shadow-md overflow-hidden border border-gray-200"><div class="p-8"><h3 class="text-2xl font-bold text-gray-900">Family</h3><p class="text-gray-500 mt-1">For families up to 5 members</p><div class="mt-5 mb-6"><span class="text-5xl font-extrabold text-gray-900">$24.99</span><span class="text-gray-500">/month</span></div><ul class="space-y-3 mb-8"><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Up to 5 family members</li><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Shared insights</li><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>Family dashboard</li><li class="flex items-center"><svg class="w-5 h-5 text-[#1FA774] mr-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>All Pro features</li></ul><a href="#" class="block text-center py-3 border-2 border-gray-300 rounded-xl font-bold text-gray-700 hover:border-[#1FA774] hover:text-[#1FA774] transition">Get Started</a></div></div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section id="testimonials" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
        <div class="text-center mb-14"><h2 class="text-3xl md:text-4xl font-extrabold text-gray-900">What Our Users Say</h2><p class="text-gray-600 text-lg mt-2">Join thousands of satisfied users who transformed their shopping habits</p></div>
        <div class="grid md:grid-cols-3 gap-8">
            <div class="bg-gray-50 rounded-2xl p-7 shadow-sm"><div class="flex text-[#1FA774] mb-4">★★★★★</div><p class="text-gray-700 mb-5 leading-relaxed">"ScanWell has completely changed how I shop for groceries. I can now easily identify products with harmful ingredients and make healthier choices for my family."</p><div class="flex items-center"><div class="w-10 h-10 rounded-full bg-[#1FA774]/20 flex items-center justify-center font-bold text-[#1FA774]">JD</div><div class="ml-3"><p class="font-bold text-gray-900">John Doe</p><p class="text-sm text-gray-500">Health Enthusiast</p></div></div></div>
            <div class="bg-gray-50 rounded-2xl p-7 shadow-sm"><div class="flex text-[#1FA774] mb-4">★★★★☆</div><p class="text-gray-700 mb-5 leading-relaxed">"The ingredient analysis feature is incredibly detailed. I love how it highlights potential allergens and gives clear explanations about each ingredient."</p><div class="flex items-center"><div class="w-10 h-10 rounded-full bg-[#1FA774]/20 flex items-center justify-center font-bold text-[#1FA774]">JS</div><div class="ml-3"><p class="font-bold text-gray-900">Jane Smith</p><p class="text-sm text-gray-500">Nutrition Coach</p></div></div></div>
            <div class="bg-gray-50 rounded-2xl p-7 shadow-sm"><div class="flex text-[#1FA774] mb-4">★★★★★</div><p class="text-gray-700 mb-5 leading-relaxed">"Fast, accurate, and incredibly useful. The community contribution feature makes it even better as we all help each other make informed decisions."</p><div class="flex items-center"><div class="w-10 h-10 rounded-full bg-[#1FA774]/20 flex items-center justify-center font-bold text-[#1FA774]">MB</div><div class="ml-3"><p class="font-bold text-gray-900">Mike Brown</p><p class="text-sm text-gray-500">Regular User</p></div></div></div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-20 bg-gradient-to-r from-[#1FA774] to-[#0D8B5E]">
    <div class="max-w-4xl mx-auto text-center px-5"><h2 class="text-3xl md:text-4xl font-extrabold text-white mb-4">Ready to Start Your Health Journey?</h2><p class="text-lg text-white/90 mb-8">Join thousands of users who make smarter choices every day</p><div class="flex flex-col sm:flex-row gap-5 justify-center"><a href="#" class="inline-flex items-center justify-center px-8 py-3.5 bg-white text-[#1FA774] font-extrabold rounded-xl hover:bg-gray-50 transition shadow-lg">Create Free Account<svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg></a><a href="#how-it-works" class="inline-flex items-center justify-center px-8 py-3.5 border-2 border-white text-white font-extrabold rounded-xl hover:bg-white/10 transition">Learn More</a></div></div>
</section>

<!-- Footer -->
<footer class="bg-gray-900 text-gray-400 py-12">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 lg:px-10">
        <div class="grid md:grid-cols-4 gap-8 mb-8"><div><div class="flex items-center space-x-2 mb-4"><div class="w-8 h-8 bg-gradient-to-br from-[#1FA774] to-[#0D8B5E] rounded-lg flex items-center justify-center"><svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg></div><span class="text-xl font-bold text-white">ScanWell</span></div><p class="text-sm">Making healthy choices easier, one scan at a time.</p></div><div><h3 class="text-white font-bold mb-4">Product</h3><ul class="space-y-2 text-sm"><li><a href="#features" class="hover:text-white transition">Features</a></li><li><a href="#pricing" class="hover:text-white transition">Pricing</a></li><li><a href="#" class="hover:text-white transition">Download</a></li></ul></div><div><h3 class="text-white font-bold mb-4">Company</h3><ul class="space-y-2 text-sm"><li><a href="#" class="hover:text-white transition">About Us</a></li><li><a href="#" class="hover:text-white transition">Blog</a></li><li><a href="#" class="hover:text-white transition">Careers</a></li></ul></div><div><h3 class="text-white font-bold mb-4">Legal</h3><ul class="space-y-2 text-sm"><li><a href="#" class="hover:text-white transition">Privacy Policy</a></li><li><a href="#" class="hover:text-white transition">Terms of Service</a></li><li><a href="#" class="hover:text-white transition">Cookie Policy</a></li></ul></div></div>
        <div class="border-t border-gray-800 pt-8 text-center text-sm"><p>&copy; 2025 ScanWell. All rights reserved.</p></div>
    </div>
</footer>
</body>
</html>
