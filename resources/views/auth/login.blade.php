<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-6">
            <x-input-label for="username" :value="__('Username')" class="glass-label" />
            
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <i class="fas fa-user-shield"></i>
                </span>
                <x-text-input 
                    id="username" 
                    class="block pl-10 w-full glass-input" 
                    type="text" 
                    name="username" 
                    :value="old('username')" 
                    required 
                    autofocus 
                    autocomplete="username" 
                    placeholder="Enter your username"
                />
            </div>
            <x-input-error :messages="$errors->get('username')" class="mt-2 text-xs font-bold text-red-500" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" class="glass-label" />

            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
                    <i class="fas fa-key"></i>
                </span>
                <x-text-input 
                    id="password" 
                    class="block pl-10 w-full glass-input"
                    type="password"
                    name="password"
                    required 
                    autocomplete="current-password" 
                    placeholder="••••••••"
                />
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2 text-xs font-bold text-red-500" />
        </div>

        <div class="flex flex-col items-center justify-end mt-8">
            <button type="submit" class="w-full py-4 btn-premium flex items-center justify-center space-x-2">
                <span class="tracking-widest">Sign in</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </button>
            
            {{-- <p class="mt-4 text-[10px] text-slate-400 uppercase tracking-tighter">
                Secure encrypted connection established
            </p> --}}
        </div>
    </form>
</x-guest-layout>