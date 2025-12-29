<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 1 field untuk email / username
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('login'));
        $password = (string) $this->input('password');
        $remember = $this->boolean('remember');

        // Jika formatnya email, coba email dulu. Kalau bukan, coba name.
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        $attempted = false;

        if ($isEmail) {
            $attempted = Auth::attempt(
                ['email' => $login, 'password' => $password],
                $remember
            );
        } else {
            $attempted = Auth::attempt(
                ['name' => $login, 'password' => $password],
                $remember
            );
        }

        // Optional: kalau user isi sesuatu yang bukan email tapi ternyata email tersimpan, atau sebaliknya
        // Kamu bisa fallback attempt (biar lebih fleksibel)
        if (! $attempted) {
            // fallback: coba kebalikannya
            $attempted = Auth::attempt(
                $isEmail
                    ? ['name' => $login, 'password' => $password]
                    : ['email' => $login, 'password' => $password],
                $remember
            );
        }

        if (! $attempted) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
