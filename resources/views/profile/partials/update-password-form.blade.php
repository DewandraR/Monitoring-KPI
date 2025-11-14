<section>
    <header>
        <h2 class="text-2xl font-extrabold text-emerald-800 tracking-wide">
            {{ __('Perbarui Kata Sandi') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Pastikan akun Anda menggunakan kata sandi yang panjang dan acak agar tetap aman.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('put')

        <!-- Current Password (Floating Label Style) -->
        <div class="relative">
            <x-text-input id="current_password" name="current_password" type="password" autocomplete="current-password"
                placeholder=" "
                class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50" />
            <x-input-label for="current_password" :value="__('Kata Sandi Saat Ini')"
                class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <!-- New Password (Floating Label Style) -->
        <div class="relative">
            <x-text-input id="password" name="password" type="password" autocomplete="new-password" placeholder=" "
                class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50" />
            <x-input-label for="password" :value="__('Kata Sandi Baru')"
                class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password (Floating Label Style) -->
        <div class="relative">
            <x-text-input id="password_confirmation" name="password_confirmation" type="password"
                autocomplete="new-password" placeholder=" "
                class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50" />
            <x-input-label for="password_confirmation" :value="__('Konfirmasi Kata Sandi')"
                class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4 pt-3">
            <button type="submit"
                class="justify-center bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold py-3 px-6 rounded-xl shadow-xl shadow-emerald-500/50 transform transition duration-300 ease-in-out hover:scale-[1.01] focus:outline-none focus:ring-4 focus:ring-emerald-600 focus:ring-opacity-50 flex items-center tracking-wider text-base">
                {{ __('SIMPAN KATA SANDI BARU') }}
            </button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-green-600 font-semibold">{{ __('Tersimpan.') }}</p>
            @endif
        </div>
    </form>
</section>
