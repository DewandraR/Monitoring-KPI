<x-guest-layout>
    {{-- Memastikan variabel $status didefinisikan, default-nya null. Ini mencegah error. --}}
    @props(['status' => null])

    <div
        class="w-full max-w-md bg-white p-8 sm:p-10 md:p-12 shadow-[0_25px_50px_-12px_rgba(0,0,0,0.25)] rounded-3xl border border-gray-100/50">

        <div class="flex flex-col items-center justify-center mb-8">
            <img src="{{ asset('Images/KMI.png') }}" alt="Logo Perusahaan KMI"
                class="w-20 h-auto rounded-xl shadow-md mb-4 transform transition duration-300 hover:scale-105">
            <h2 class="text-3xl font-bold text-gray-800 tracking-tight">
                Selamat Datang
            </h2>
            <p class="text-base text-gray-500 mt-1">Masuk ke Dashboard Personalia Anda</p>
        </div>

        @if ($status)
            <div
                class="mb-4 font-medium text-sm text-emerald-600 bg-emerald-50 p-4 rounded-lg border border-emerald-100">
                {{ $status }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-6" id="loginForm">
            @csrf

            <div class="relative">
                {{-- Catatan: Menggunakan 'peer' dan 'floating-input' untuk styling floating label --}}
                <x-text-input id="email"
                    class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50"
                    type="email" name="email" :value="old('email')" required autofocus autocomplete="username"
                    placeholder=" " />
                <x-input-label for="email" :value="__('Alamat Email')"
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="relative">
                <x-text-input id="password"
                    class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50"
                    type="password" name="password" required autocomplete="current-password" placeholder=" " />
                <x-input-label for="password" :value="__('Kata Sandi')"
                    class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex justify-between items-center pt-2">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox"
                        class="rounded border-gray-300 text-emerald-600 shadow-sm focus:ring-emerald-500"
                        name="remember">
                    <span class="ms-2 text-sm text-gray-600">{{ __('Ingat Saya') }}</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="text-sm text-emerald-600 hover:text-emerald-800 font-medium transition duration-150 ease-in-out"
                        href="{{ route('password.request') }}">
                        {{ __('Lupa Password?') }}
                    </a>
                @endif
            </div>

            <div class="mt-8">
                <button type="submit"
                    class="w-full justify-center bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold py-4 px-4 rounded-xl shadow-2xl shadow-emerald-500/50 transform transition duration-300 ease-in-out hover:scale-[1.01] focus:outline-none focus:ring-4 focus:ring-emerald-600 focus:ring-opacity-50 flex items-center tracking-wider text-lg">
                    <svg class="w-6 h-6 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3v-5m6-10V4a3 3 0 00-3-3H6a3 3 0 00-3 3v5">
                        </path>
                    </svg>
                    {{ __('MASUK KE SISTEM') }}
                </button>
            </div>
        </form>

        @if (Route::has('register'))
            <div class="mt-4 text-center">
                <a href="{{ route('register') }}"
                    class="w-full inline-flex justify-center bg-gray-100 hover:bg-gray-200 text-emerald-700 font-semibold py-3 px-4 rounded-xl border border-gray-300 transform transition duration-300 ease-in-out hover:scale-[1.01]">
                    {{ __('Belum Punya Akun? Registrasi Disini') }}
                </a>
            </div>
        @endif
        <footer class="mt-12 text-center text-xs text-gray-400">
            <strong>PT Kayu Mabel Indonesia</strong> &copy; {{ date('Y') }}
        </footer>
    </div>

</x-guest-layout>
