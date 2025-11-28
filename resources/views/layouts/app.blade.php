<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Custom Animations */
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -1000px 0;
            }

            100% {
                background-position: 1000px 0;
            }
        }

        .animate-slide-down {
            animation: slideDown 0.3s ease-out;
        }

        .animate-scale-in {
            animation: scaleIn 0.2s ease-out;
        }

        .nav-link-active {
            position: relative;
        }

        .nav-link-active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80%;
            height: 4px;
            background: linear-gradient(90deg, #84cc16, #a3e635);
            border-radius: 2px 2px 0 0;
            box-shadow: 0 0 10px rgba(132, 204, 22, 0.5);
        }

        .shimmer-effect {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }

        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
        }

        /* Dropdown Animation */
        .dropdown-content {
            transform-origin: top right;
            transition: all 0.2s ease-out;
        }

        /* Notification Badge */
        @keyframes pulse-ring {
            0% {
                transform: scale(0.8);
                opacity: 1;
            }

            50% {
                transform: scale(1.2);
                opacity: 0.5;
            }

            100% {
                transform: scale(0.8);
                opacity: 1;
            }
        }

        .pulse-ring {
            animation: pulse-ring 2s ease-in-out infinite;
        }

        /* Hover glow effect */
        .hover-glow {
            transition: all 0.3s ease;
        }

        .hover-glow:hover {
            box-shadow: 0 0 20px rgba(132, 204, 22, 0.5);
        }

        /* Mobile menu slide */
        .mobile-menu-enter {
            animation: slideDown 0.3s ease-out;
        }
    </style>
</head>

<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen bg-gray-50">
        <!-- NAVBAR - ULTRA MODERN -->
        <nav x-data="{ open: false, scrolled: false }" @scroll.window="scrolled = window.pageYOffset > 10"
            :class="scrolled ? 'shadow-2xl' : 'shadow-md'"
            class="bg-gradient-to-r from-emerald-800 via-emerald-700 to-emerald-800 border-b border-emerald-900 sticky top-0 z-50 transition-all duration-300">

            <!-- Animated background shimmer -->
            <div class="absolute inset-0 shimmer-effect opacity-30 pointer-events-none"></div>

            <div class="w-full relative">
                <div class="flex justify-between h-20 ps-6 lg:ps-8">
                    <!-- LEFT SECTION: Logo & Brand -->
                    <div class="flex items-center space-x-8">
                        <!-- Brand with Animation -->
                        <div class="shrink-0 flex items-center group">
                            <a href="{{ route('dashboard') }}"
                                class="flex items-center space-x-3 transform transition-all duration-300 hover:scale-105">
                                <!-- Logo with glow effect -->
                                <div class="relative">
                                    <div
                                        class="absolute inset-0 bg-lime-400 rounded-full blur-md opacity-50 group-hover:opacity-75 transition-opacity">
                                    </div>
                                    <img src="{{ asset('Images/KMI.png') }}" alt="Logo"
                                        class="relative w-12 h-12 rounded-full shadow-lg ring-2 ring-white/30 group-hover:ring-lime-400 transition-all duration-300">
                                </div>
                                <!-- Brand text with gradient -->
                                <div class="flex flex-col">
                                    <span
                                        class="text-white text-2xl font-extrabold tracking-wider bg-gradient-to-r from-white to-lime-200 bg-clip-text text-transparent">
                                        Monitoring KPI
                                    </span>
                                    <span class="text-emerald-200 text-xs font-medium tracking-wide">
                                        Performance Dashboard
                                    </span>
                                </div>
                            </a>
                        </div>

                        <!-- Desktop Navigation Links -->
                        <div class="hidden lg:flex space-x-2 items-center">
                            <!-- Dashboard Link -->
                            <a href="{{ route('dashboard') }}"
                                class="relative group px-6 py-3 rounded-xl text-sm font-bold tracking-wide transition-all duration-300 overflow-hidden
                                {{ request()->routeIs('dashboard')
                                    ? 'bg-emerald-600/50 text-white nav-link-active'
                                    : 'text-emerald-100 hover:bg-emerald-600/30 hover:text-white' }}">

                                <!-- Hover effect background -->
                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent transform -translate-x-full group-hover:translate-x-full transition-transform duration-700">
                                </div>

                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-5 h-5 transform group-hover:rotate-12 transition-transform duration-300"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    <span>DASHBOARD</span>
                                </div>
                            </a>

                            <!-- WC Person Link -->
                            <a href="{{ route('wc-person') }}"
                                class="relative group px-6 py-3 rounded-xl text-sm font-bold tracking-wide transition-all duration-300 overflow-hidden
                                {{ request()->routeIs('wc-person')
                                    ? 'bg-emerald-600/50 text-white nav-link-active'
                                    : 'text-emerald-100 hover:bg-emerald-600/30 hover:text-white' }}">

                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent transform -translate-x-full group-hover:translate-x-full transition-transform duration-700">
                                </div>

                                <div class="relative flex items-center space-x-2">
                                    <svg class="w-5 h-5 transform group-hover:scale-110 transition-transform duration-300"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span>WC PERSON</span>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- RIGHT SECTION: Search, Notifications & User -->
                    @auth
                        <div class="hidden lg:flex items-center space-x-4 pe-6 lg:pe-8">
                            <!-- User Dropdown -->
                            <div x-data="{ userOpen: false }" class="relative">
                                <button @click="userOpen = !userOpen"
                                    class="flex items-center space-x-3 px-4 py-2 rounded-full bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-500 hover:to-emerald-600 border-2 border-emerald-500/50 hover:border-lime-400 shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300 group">

                                    <!-- Avatar -->
                                    <div class="relative">
                                        <div
                                            class="absolute inset-0 bg-lime-400 rounded-full blur-sm opacity-50 group-hover:opacity-75 transition-opacity">
                                        </div>
                                        <div
                                            class="relative w-10 h-10 rounded-full bg-gradient-to-br from-lime-400 to-emerald-400 flex items-center justify-center font-bold text-emerald-900 ring-2 ring-white/30">
                                            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                                        </div>
                                    </div>

                                    <!-- User Info -->
                                    <div class="flex flex-col items-start">
                                        <span
                                            class="text-white font-bold text-sm leading-tight">{{ Auth::user()->name }}</span>
                                        <span class="text-emerald-200 text-xs">Administrator</span>
                                    </div>

                                    <!-- Dropdown Icon -->
                                    <svg class="w-5 h-5 text-white transform transition-transform duration-300"
                                        :class="userOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Dropdown Menu -->
                                <div x-show="userOpen" @click.away="userOpen = false"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 transform scale-95"
                                    x-transition:enter-end="opacity-100 transform scale-100"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 transform scale-100"
                                    x-transition:leave-end="opacity-0 transform scale-95"
                                    class="absolute right-0 mt-3 w-64 rounded-2xl shadow-2xl bg-white border border-gray-100 overflow-hidden z-50 dropdown-content"
                                    style="display: none;">

                                    <!-- User Info Header -->
                                    <div class="px-6 py-4 bg-gradient-to-r from-emerald-600 to-emerald-700 text-white">
                                        <p class="text-sm font-semibold">Signed in as</p>
                                        <p class="text-lg font-bold truncate">{{ Auth::user()->email }}</p>
                                    </div>

                                    <!-- Menu Items -->
                                    <div class="py-2">
                                        <a href="{{ route('profile.edit') }}"
                                            class="flex items-center space-x-3 px-6 py-3 text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition-all duration-200 group">
                                            <svg class="w-5 h-5 transform group-hover:scale-110 transition-transform"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            <span class="font-medium">My Profile</span>
                                        </a>

                                        <a href="#"
                                            class="flex items-center space-x-3 px-6 py-3 text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 transition-all duration-200 group">
                                            <svg class="w-5 h-5 transform group-hover:scale-110 transition-transform"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <span class="font-medium">Settings</span>
                                        </a>

                                        <div class="border-t border-gray-100 my-2"></div>

                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit"
                                                class="flex items-center space-x-3 w-full px-6 py-3 text-red-600 hover:bg-red-50 transition-all duration-200 group">
                                                <svg class="w-5 h-5 transform group-hover:scale-110 transition-transform"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                                </svg>
                                                <span class="font-medium">Log Out</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endauth

                    <!-- Mobile Menu Button -->
                    <div class="flex items-center lg:hidden pe-4">
                        <button @click="open = !open"
                            class="relative p-3 rounded-xl bg-emerald-700/50 hover:bg-emerald-600/50 text-white transition-all duration-300 group">
                            <svg class="h-6 w-6 transform transition-transform duration-300"
                                :class="open ? 'rotate-90' : ''" stroke="currentColor" fill="none"
                                viewBox="0 0 24 24">
                                <path :class="{ 'hidden': open, 'inline-flex': !open }" stroke-linecap="round"
                                    stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{ 'hidden': !open, 'inline-flex': open }" stroke-linecap="round"
                                    stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div x-show="open" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="lg:hidden bg-emerald-700/95 backdrop-blur-lg border-t border-emerald-600/50"
                style="display: none;">

                <!-- Mobile Nav Links -->
                <div class="px-4 pt-4 pb-3 space-y-2">
                    <a href="{{ route('dashboard') }}"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-white font-semibold transition-all duration-300
                       {{ request()->routeIs('dashboard') ? 'bg-emerald-600 shadow-lg' : 'hover:bg-emerald-600/50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Dashboard</span>
                    </a>

                    <a href="{{ route('wc-person') }}"
                        class="flex items-center space-x-3 px-4 py-3 rounded-xl text-white font-semibold transition-all duration-300
                       {{ request()->routeIs('wc-person') ? 'bg-emerald-600 shadow-lg' : 'hover:bg-emerald-600/50' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>WC Person</span>
                    </a>
                </div>

                <!-- Mobile User Section -->
                @auth
                    <div class="border-t border-emerald-600/50 px-4 py-4">
                        <div class="flex items-center space-x-3 mb-4">
                            <div
                                class="w-12 h-12 rounded-full bg-gradient-to-br from-lime-400 to-emerald-400 flex items-center justify-center font-bold text-emerald-900 ring-2 ring-white/30">
                                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-white font-bold">{{ Auth::user()->name }}</p>
                                <p class="text-emerald-200 text-sm">{{ Auth::user()->email }}</p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <a href="{{ route('profile.edit') }}"
                                class="flex items-center space-x-3 px-4 py-3 rounded-xl text-white hover:bg-emerald-600/50 transition-all duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>Profile</span>
                            </a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="flex items-center space-x-3 w-full px-4 py-3 rounded-xl text-white hover:bg-red-600/50 transition-all duration-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <span>Log Out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>
        </nav>

        <!-- Optional Header slot -->
        @isset($header)
            <header class="bg-white shadow-sm border-b border-gray-200 animate-slide-down">
                <div class="w-full py-6 ps-6 lg:ps-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main class="animate-slide-down">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
    @vite(['resources/css/app.css', 'resources/js/app.js'], 'build')
    @stack('scripts')
</body>

</html>
