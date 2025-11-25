<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Tidak Ditemukan | Konfirmasi PRO App</title>
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

        /* Animasi "pencarian" untuk ikon */
        @keyframes sway {
            0% {
                transform: translateX(-5px) rotate(-1deg);
            }

            50% {
                transform: translateX(5px) rotate(1deg);
            }

            100% {
                transform: translateX(-5px) rotate(-1deg);
            }
        }

        .swaying-icon {
            animation: sway 3s ease-in-out infinite;
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
                <svg class="w-20 h-20 sm:w-24 sm:h-24 mx-auto text-blue-600 swaying-icon"
                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6" />
                </svg>
            </div>

            <h1 class="text-7xl sm:text-8xl font-extrabold text-slate-400 mb-2 leading-none">404</h1>
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-800 mb-4">Halaman Tidak Ditemukan</h2>

            <p class="text-base text-slate-600 mb-8">
                Kami tidak dapat menemukan halaman yang Anda cari. Kemungkinan tautan yang Anda akses salah atau halaman
                telah dipindahkan.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <button onclick="window.history.back();"
                    class="inline-flex items-center justify-center w-full sm:w-auto px-6 py-3 rounded-xl
                           bg-gradient-to-r from-green-700 to-blue-900 text-white font-semibold text-lg
                           shadow-xl shadow-green-500/40
                           hover:from-green-800 hover:to-blue-900
                           transition-all duration-300 transform
                           hover:scale-[1.03] active:scale-[0.98]
                           focus:outline-none focus:ring-4 focus:ring-green-300 gap-2">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                    Kembali ke Halaman Sebelumnya
                </button>

                <a href="{{ route('scan') }}"
                    class="inline-flex items-center justify-center w-full sm:w-auto px-6 py-3 rounded-xl
                           bg-slate-100 text-slate-700 font-semibold text-lg border border-slate-300
                           hover:bg-slate-200 active:scale-[0.98] transition-all duration-300 gap-2">
                    Ke Halaman Utama
                </a>
            </div>

            <p class="mt-6 text-xs text-slate-500/80">
                Gunakan menu navigasi untuk menemukan halaman yang benar.
            </p>
        </div>

    </div>
</body>

</html>
