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
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'], // Changed from email
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // 1. Convert the input username to the searchable hash
        $usernameHash = hash('sha256', strtolower($this->input('username')));

        // 2. Prepare the credentials - ADD is_active HERE
        $credentials = [
            'username_hash' => $usernameHash, 
            'password'      => $this->input('password'),
            'is_active'     => 1, // <--- Only allow Active users
        ];

        // 3. Attempt authentication
        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Check if the user exists but is simply inactive to give a better error
            $userExists = \App\Models\User::where('username_hash', $usernameHash)->first();
            
            if ($userExists && $userExists->is_active == 0) {
                throw ValidationException::withMessages([
                    'username' => 'This account has been deactivated. Please contact the administrator.',
                ]);
            }

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
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
        // Updated to use 'username' for the throttle key
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}