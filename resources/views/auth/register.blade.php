<x-guest-layout>

    <!-- Konten Form diletakkan di dalam container di tengah-tengah slot guest-layout -->
    <div
        class="w-full max-w-md bg-white p-8 sm:p-10 md:p-12 shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] rounded-3xl border border-gray-100/50">

        <!-- Logo Perusahaan KMI -->
        <div class="flex flex-col items-center justify-center mb-8">
            <!-- Pastikan path logo ini benar: public/Images/KMI.png -->
            <img src="{{ asset('Images/KMI.png') }}" alt="Logo Perusahaan KMI"
                class="w-20 h-auto rounded-xl shadow-md mb-4 transform transition duration-300 hover:scale-105">
            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">
                Daftar Akun Baru
            </h2>
            <p class="text-base text-gray-500 mt-1">Bergabung dengan Sistem Personalia WC-Person</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-6">
            @csrf

            <!-- Name (Floating Label Style) -->
            <div class="relative">
                <x-text-input id="name"
                    class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50"
                    type="text" name="name" :value="old('name')" required autofocus autocomplete="name"
                    placeholder=" " />
                <x-input-label for="name" :value="__('Nama Lengkap')"
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <!-- Email Address (Floating Label Style) -->
            <div class="relative mt-4">
                <x-text-input id="email"
                    class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50"
                    type="email" name="email" :value="old('email')" required autocomplete="username" placeholder=" " />
                <x-input-label for="email" :value="__('Alamat Email')"
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <!-- Password (Floating Label Style) -->
            <div class="relative mt-4">
                <x-text-input id="password"
                    class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50"
                    type="password" name="password" required autocomplete="new-password" placeholder=" " />
                <x-input-label for="password" :value="__('Kata Sandi (Min. 8 Karakter)')"
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <!-- Confirm Password (Floating Label Style) -->
            <div class="relative mt-4">
                <x-text-input id="password_confirmation"
                    class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50"
                    type="password" name="password_confirmation" required autocomplete="new-password" placeholder=" " />
                <x-input-label for="password_confirmation" :value="__('Konfirmasi Kata Sandi')"
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end mt-6">
                <!-- Link ke Halaman Login -->
                <a class="text-sm text-emerald-600 hover:text-emerald-800 font-medium transition duration-150 ease-in-out"
                    href="{{ route('login') }}">
                    {{ __('Sudah terdaftar?') }}
                </a>

                <!-- Register Button -->
                <button type="submit"
                    class="ms-4 justify-center bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold py-3 px-6 rounded-xl shadow-xl shadow-emerald-500/50 transform transition duration-300 ease-in-out hover:scale-[1.01] focus:outline-none focus:ring-4 focus:ring-emerald-600 focus:ring-opacity-50 flex items-center tracking-wider text-base">
                    <svg class="w-5 h-5 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                        </path>
                    </svg>
                    {{ __('DAFTAR SEKARANG') }}
                </button>
            </div>
        </form>

        <!-- FOOTER: Ditempatkan di dalam kontainer form agar tetap di bawah form di berbagai ukuran layar -->
        <footer class="mt-12 text-center text-xs text-gray-400">
            <strong>PT Kayu Mabel Indonesia</strong> &copy; {{ date('Y') }}
        </footer>

    </div>
</x-guest-layout>
