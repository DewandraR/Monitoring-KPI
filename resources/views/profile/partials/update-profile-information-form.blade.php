<section>
    <header>
        <h2 class="text-2xl font-extrabold text-emerald-800 tracking-wide">
            {{ __('Informasi Profil') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Perbarui nama profil dan alamat email akun Anda.') }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <!-- Name (Floating Label Style) -->
        <div class="relative">
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus
                autocomplete="name" placeholder=" "
                class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50" />
            <x-input-label for="name" :value="__('Nama')"
                class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <!-- Email (Floating Label Style) -->
        <div class="relative">
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required
                autocomplete="username" placeholder=" "
                class="floating-input peer block w-full p-4 border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 rounded-xl shadow-inner bg-gray-50" />
            <x-input-label for="email" :value="__('Email')"
                class="floating-label absolute text-gray-500 duration-300 transform -translate-y-4 scale-75 top-4 left-4 z-10 origin-[0] peer-placeholder-shown:scale-100 peer-placeholder-shown:translate-y-0 peer-focus:scale-75 peer-focus:-translate-y-4" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Alamat email Anda belum terverifikasi.') }}
                        <button form="send-verification"
                            class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Klik di sini untuk mengirim ulang email verifikasi.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('Tautan verifikasi baru telah dikirimkan ke alamat email Anda.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4 pt-3">
            <button type="submit"
                class="justify-center bg-emerald-700 hover:bg-emerald-800 text-white font-extrabold py-3 px-6 rounded-xl shadow-xl shadow-emerald-500/50 transform transition duration-300 ease-in-out hover:scale-[1.01] focus:outline-none focus:ring-4 focus:ring-emerald-600 focus:ring-opacity-50 flex items-center tracking-wider text-base">
                {{ __('SIMPAN PERUBAHAN') }}
            </button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-green-600 font-semibold">{{ __('Tersimpan.') }}</p>
            @endif
        </div>
    </form>
</section>
