<x-guest-layout>
    @if($hasValidInvite)
        <div class="mb-4 p-3 bg-accent-green/10 border border-accent-green/20 rounded-md">
            <p class="text-sm text-accent-green font-semibold">{{ __('beta.register_with_invite') }}</p>
        </div>
    @else
        <div class="mb-4 p-3 bg-accent-blue/10 border border-accent-blue/20 rounded-md">
            <p class="text-sm text-accent-blue">{{ __('beta.register_free') }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        @if($inviteCode)
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

        <p class="mt-4 text-sm text-text-tertiary">
            {{ __('auth.activation_register_hint') }}
        </p>

        @if($errors->has('invite_code'))
            <x-input-error :messages="$errors->get('invite_code')" class="mt-4" />
        @endif

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-text-secondary hover:text-text-primary rounded-md focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-accent-blue" href="{{ route('login') }}">
                {{ __('auth.Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('auth.Create Account') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
