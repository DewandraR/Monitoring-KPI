<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesi Kedaluwarsa | Konfirmasi PRO App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            /* Pastikan body mengisi seluruh viewport */
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Animasi Pulsing untuk logo */
        @keyframes pulse-once {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            }

            70% {
                transform: scale(1.05);
                box-shadow: 0 0 0 10px rgba(255, 255, 255, 0);
            }

            100% {
                transform: scale(1);
                box-shadow: none;
            }
        }

        .logo-pulse {
            animation: pulse-once 1.5s ease-out;
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
            class="relative z-10 w-full max-w-lg bg-white rounded-3xl shadow-2xl border-4 border-white/20 backdrop-blur-sm p-6 sm:p-10 text-center transform transition-all duration-300 hover:shadow-3xl hover:border-white/30">

            <div class="mb-6">
                <img src="{{ asset('Images/KMI.png') }}" alt="Company Logo"
                    class="logo-pulse mx-auto w-20 h-20 sm:w-24 sm:h-24 object-contain rounded-xl p-1 bg-white ring-4 ring-red-500/50 shadow-lg">
            </div>

            <h1 class="text-7xl sm:text-8xl font-extrabold text-red-600 mb-2 leading-none">419</h1>
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-800 mb-4">Sesi Anda Telah Kedaluwarsa</h2>

            <p class="text-base text-slate-600 mb-8">
                Demi keamanan data, sesi Anda telah berakhir karena tidak ada aktivitas.
                Silakan masuk kembali untuk melanjutkan pekerjaan.
            </p>

            <a href="{{ route('login') }}"
                class="inline-flex items-center justify-center w-full sm:w-auto px-8 py-3 rounded-xl 
                       bg-gradient-to-r from-red-600 to-rose-700 text-white font-semibold text-lg 
                       shadow-xl shadow-red-500/40 
                       hover:from-red-700 hover:to-rose-800 
                       transition-all duration-300 transform 
                       hover:scale-[1.03] active:scale-[0.98] 
                       focus:outline-none focus:ring-4 focus:ring-red-300 gap-2">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                </svg>
                Masuk Kembali
            </a>

            <p class="mt-6 text-xs text-slate-500/80">
                Tekan tombol di atas untuk kembali ke halaman login.
            </p>
        </div>

    </div>
</body>

</html>
