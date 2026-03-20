<x-guest-layout>

    <form method="POST" action="{{ route('register.tournament-mode') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('auth.Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('auth.Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <p class="mt-4 text-sm text-text-tertiary">
            {{ __('auth.activation_register_hint') }}
        </p>

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
