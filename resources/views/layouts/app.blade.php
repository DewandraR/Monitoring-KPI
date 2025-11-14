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
</head>

<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen bg-gray-50">
        <!-- NAVBAR -->
        <nav x-data="{ open: false }" class="bg-emerald-800 border-b border-emerald-900 shadow-md">
            <div class="w-full">
                <div class="flex justify-between h-16 ps-6 lg:ps-8">
                    <div class="flex">
                        <!-- Brand -->
                        <div class="shrink-0 flex items-center">
                            <a href="{{ route('dashboard') }}" class="flex items-center">
                                <img src="{{ asset('Images/KMI.png') }}" alt="Logo"
                                    class="w-8 h-8 me-3 rounded-full shadow-md">
                                <span class="text-white text-2xl font-extrabold tracking-wider">WC-Person</span>
                            </a>
                        </div>

                        <!-- Main Nav -->
                        <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex items-stretch">
                            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')"
                                class="inline-flex items-center px-4 pt-1 border-b-4 text-sm font-extrabold leading-5 transition duration-150 ease-in-out
                                {{ request()->routeIs('dashboard') ? 'text-white bg-emerald-700 border-lime-400' : 'text-gray-300 border-transparent hover:bg-emerald-700 hover:border-gray-500 hover:text-white' }}">
                                {{ __('DASHBOARD') }}
                            </x-nav-link>

                            <x-nav-link :href="route('wc-person')" :active="request()->routeIs('wc-person')"
                                class="inline-flex items-center px-4 pt-1 border-b-4 text-sm font-extrabold leading-5 transition duration-150 ease-in-out
                                {{ request()->routeIs('wc-person') ? 'text-white bg-emerald-700 border-lime-400' : 'text-gray-300 border-transparent hover:bg-emerald-700 hover:border-gray-500 hover:text-white' }}">
                                {{ __('WC PERSON') }}
                            </x-nav-link>
                        </div>
                    </div>

                    <!-- User Dropdown (desktop) -->
                    @auth
                        <div class="hidden sm:flex sm:items-center sm:ms-6 pe-6 lg:pe-8">
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm leading-4 font-extrabold rounded-full
                                           text-white bg-emerald-700 hover:bg-emerald-600 shadow-lg hover:shadow-xl
                                           focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500
                                           transition ease-in-out duration-300 transform hover:scale-105">
                                        <div>{{ Auth::user()->name }}</div>
                                        <div class="ms-2">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <div class="rounded-xl shadow-2xl p-2 bg-white border border-gray-100/50">
                                        <x-dropdown-link :href="route('profile.edit')"
                                            class="block px-4 py-2 text-md text-gray-700 hover:bg-emerald-50 hover:text-emerald-800 rounded-lg transition duration-200">
                                            {{ __('Profile') }}
                                        </x-dropdown-link>

                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <x-dropdown-link :href="route('logout')"
                                                onclick="event.preventDefault(); this.closest('form').submit();"
                                                class="block px-4 py-2 text-md text-gray-700 hover:bg-red-50 hover:text-red-700 rounded-lg transition duration-200">
                                                {{ __('Log Out') }}
                                            </x-dropdown-link>
                                        </form>
                                    </div>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endauth

                    <!-- Mobile burger -->
                    <div class="-me-2 flex items-center sm:hidden">
                        <button @click="open = ! open"
                            class="inline-flex items-center justify-center p-2 rounded-md text-emerald-400 hover:text-emerald-500 hover:bg-emerald-700 focus:outline-none focus:bg-emerald-700 focus:text-emerald-500 transition duration-150 ease-in-out">
                            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16" />
                                <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden"
                                    stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu -->
            <div :class="{ 'block': open, 'hidden': !open }" class="hidden sm:hidden bg-emerald-700">
                <div class="pt-2 pb-3 space-y-1">
                    <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" class="text-white hover:bg-emerald-600">
                        {{ __('Dashboard') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('wc-person')" :active="request()->routeIs('wc-person')" class="text-white hover:bg-emerald-600">
                        {{ __('WC Person') }}
                    </x-responsive-nav-link>
                </div>

                @auth
                    <div class="pt-4 pb-1 border-t border-emerald-600">
                        <div class="px-4">
                            <div class="font-medium text-base text-white">{{ Auth::user()->name }}</div>
                            <div class="font-medium text-sm text-gray-300">{{ Auth::user()->email }}</div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <x-responsive-nav-link :href="route('profile.edit')" class="text-white hover:bg-emerald-600">
                                {{ __('Profile') }}
                            </x-responsive-nav-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-responsive-nav-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();"
                                    class="text-white hover:bg-emerald-600">
                                    {{ __('Log Out') }}
                                </x-responsive-nav-link>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>
        </nav>

        <!-- Optional Header slot -->
        @isset($header)
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="w-full py-6 ps-6 lg:ps-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
    @vite(['resources/css/app.css', 'resources/js/app.js'], 'build')
    @stack('scripts')
</body>

</html>
