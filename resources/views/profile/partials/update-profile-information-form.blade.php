<section>
    <header>
        <h2 class="text-lg font-medium text-text-primary">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-text-secondary">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-text-primary">
                        {{ __('Your email address is unverified.') }}

                        <x-ghost-button form="send-verification" type="submit" color="slate" size="xs">
                            {{ __('Click here to re-send the verification email.') }}
                        </x-ghost-button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-accent-green">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="locale" :value="__('Language')" />
            <select id="locale" name="locale" class="mt-1 block w-full border-border-strong focus:border-accent-blue focus:ring-accent-blue rounded-md shadow-xs min-h-[44px]">
                @foreach (config('app.supported_locales') as $locale)
                    <option value="{{ $locale }}" {{ old('locale', $user->locale) === $locale ? 'selected' : '' }}>
                        {{ $locale === 'es' ? 'Español' : 'English' }}
                    </option>
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('locale')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-text-secondary"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
