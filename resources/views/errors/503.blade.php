<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode Pemeliharaan | Konfirmasi PRO App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Animasi berputar lambat untuk ikon wrench */
        @keyframes spin-slow {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .spin-slow {
            animation: spin-slow 15s linear infinite;
        }
    </style>
</head>

<body class="bg-slate-50">

    <div class="relative flex items-center justify-center min-h-screen w-full p-4 sm:p-6 lg:p-8 overflow-hidden">

        <div class="absolute inset-0 bg-gradient-to-br from-green-700 via-green-800 to-blue-900 opacity-95">
        </div>

        <div
            class="absolute inset-0
            bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiNmZmZmZmYiIGZpbGwtb3BhY2l0eT0iMC41Ij48Y2lyY2xlIGN4PSIzMCIgY3k9IjMwIiByPSI0Ii8+PC9nPjwvZz48L3N2ZyUz')%']
            opacity-20">
        </div>

        <div
            class="relative z-10 w-full max-w-lg bg-white rounded-3xl shadow-2xl border-4 border-white/20 backdrop-blur-sm p-6 sm:p-10 text-center transform transition-all duration-300">

            <div class="mb-6">
                <img src="{{ asset('Images/KMI.png') }}" alt="Company Logo"
                    class="w-20 h-20 sm:w-24 sm:h-24 mx-auto text-amber-500 animate-pulse-smooth" />
            </div>

            <h1 class="text-7xl sm:text-8xl font-extrabold text-slate-400 mb-2 leading-none">503</h1>
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-800 mb-4">Aplikasi Sedang Dalam Pemeliharaan</h2>

            <p class="text-base text-slate-600 mb-8">
                Maaf atas ketidaknyamanannya. Saat ini kami sedang melakukan **pemeliharaan sistem terjadwal**
                untuk peningkatan dan optimasi kualitas layanan.
            </p>

            <div class="p-4 bg-amber-50 rounded-xl border border-amber-300 text-amber-900 mb-8">
                <p class="font-semibold text-sm mb-1 flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.007z"
                            clip-rule="evenodd" />
                    </svg>
                    Akses akan kembali normal segera.
                </p>
                <p class="text-xs">
                    Silakan coba kembali dalam beberapa menit. Terima kasih atas pengertiannya.
                </p>
            </div>


            <a href="#" onclick="window.location.reload(); return false;"
                class="inline-flex items-center justify-center w-full sm:w-auto px-8 py-3 rounded-xl
                       bg-gradient-to-r from-emerald-600 to-blue-900 text-white font-semibold text-lg
                       shadow-xl shadow-green-500/40
                       hover:from-emerald-700 hover:to-blue-900
                       transition-all duration-300 transform
                       hover:scale-[1.03] active:scale-[0.98]
                       focus:outline-none focus:ring-4 focus:ring-green-300 gap-2">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m15.356-2H15v-5m-6 0v5H4.582m15.356 2A8.001 8.001 0 0119.418 15m-15.356 2H9v5m6-5v5h.582m-15.356-2A8.001 8.001 0 0119.418 15" />
                </svg>
                Coba Akses Lagi
            </a>

        </div>

    </div>
</body>

</html>
