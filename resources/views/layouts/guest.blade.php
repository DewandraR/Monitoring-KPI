<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-gray-900 antialiased">

    <div class="min-h-screen flex flex-col md:flex-row antialiased bg-gray-100">

        <div
            class="hidden md:flex md:w-1/2 bg-gradient-to-br from-lime-500 via-emerald-700 to-green-900 items-center justify-center p-16 relative overflow-hidden shadow-2xl">

            <svg class="absolute inset-0 w-full h-full opacity-15 transform scale-150" viewBox="0 0 100 100"
                preserveAspectRatio="none">
                <polygon points="0,100 50,0 100,100" fill="rgba(0,0,0,0.15)"></polygon>
                <circle cx="20" cy="80" r="30" fill="rgba(255,255,255,0.08)"></circle>
                <circle cx="80" cy="30" r="40" fill="rgba(255,255,255,0.06)"></circle>
            </svg>

            <div
                class="text-center z-10 p-12 rounded-3xl bg-white bg-opacity-20 backdrop-blur-md shadow-[0_25px_50px_-12px_rgba(0,0,0,0.5)] border border-white/30 transform transition duration-500 hover:scale-[1.03]">
                <h1 class="text-5xl font-extrabold text-white mb-4 tracking-wider drop-shadow-lg">
                    WC-Person
                </h1>
                <h2 class="text-2xl font-semibold text-lime-100 mb-6 drop-shadow-md">
                    Sistem Personalia
                </h2>
                <p class="text-lg text-white/90 italic">
                    "Lingkungan kerja terintegrasi. Modern, Cepat, dan Aman."
                </p>
            </div>
        </div>

        <div class="w-full md:w-1/2 flex items-center justify-center p-6 sm:p-10 lg:p-16">
            {{ $slot }}
        </div>
    </div>
</body>

</html>
