<x-guest-layout>
    @if($betaMode)
        <div class="mb-4 p-3 bg-accent-gold/10 border border-accent-gold/20 rounded-md">
            <p class="text-sm text-accent-gold font-semibold">{{ __('beta.badge') }}</p>
            <p class="text-xs text-accent-gold mt-1">{{ __('beta.register_notice') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        @if($betaMode && $inviteCode)
            <input type="hidden" name="invite_code" value="{{ $inviteCode }}">
        @endif

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('auth.Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('auth.Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="$email ?? old('email')" required autocomplete="email" :readonly="$email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('auth.Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('auth.Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        @if($errors->has('invite_code'))
            <x-input-error :messages="$errors->get('invite_code')" class="mt-4" />
        @endif

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-text-secondary hover:text-text-primary rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-accent-blue" href="{{ route('login') }}">
                {{ __('auth.Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('auth.Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
