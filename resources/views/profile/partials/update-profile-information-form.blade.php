<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}" class="p-5 space-y-5"
      x-data="{
          name: '{{ old('name', $user->name) }}',
          bio: '{{ old('bio', $user->bio ?? '') }}',
          get initials() {
              let parts = this.name.trim().split(/\s+/).map(w => w.charAt(0)).join('');
              return parts.length > 2 ? parts.charAt(0) + parts.charAt(parts.length - 1) : parts;
          }
      }">
    @csrf
    @method('patch')

    {{-- Avatar preview --}}
    <div class="flex justify-center">
        <div class="w-20 h-20 rounded-full bg-linear-to-br from-blue-500 to-blue-700 flex items-center justify-center shrink-0">
            <span class="font-heading font-bold text-2xl text-white" x-text="initials">{{ $user->getInitials() }}</span>
        </div>
    </div>

    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" x-model="name" required autofocus autocomplete="name" />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="username" :value="__('profile.username')" />
        <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $user->username)" required autocomplete="username" />
        <p class="mt-1 text-xs text-text-muted">{{ str_replace(':username', old('username', $user->username ?? '...'), __('profile.username_hint')) }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('username')" />
    </div>

    <div>
        <x-input-label for="email" :value="__('Email')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="email" />
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
        <x-input-label for="bio" :value="__('profile.bio')" />
        <textarea id="bio" name="bio" rows="3"
                  class="mt-1 block w-full bg-surface-700 border border-border-strong text-text-primary placeholder-text-muted focus:border-accent-blue/50 focus:ring-accent-blue rounded-lg shadow-xs text-sm"
                  maxlength="160"
                  x-model="bio"
                  placeholder="{{ __('profile.bio_hint') }}">{{ old('bio', $user->bio) }}</textarea>
        <p class="mt-1 text-xs text-text-muted text-right"><span x-text="bio.length">{{ strlen(old('bio', $user->bio ?? '')) }}</span>/160</p>
        <x-input-error class="mt-2" :messages="$errors->get('bio')" />
    </div>

    <div>
        <x-input-label for="locale" :value="__('Language')" />
        <select id="locale" name="locale" class="mt-1 block w-full bg-surface-700 border border-border-strong text-text-primary focus:border-accent-blue focus:ring-accent-blue rounded-lg shadow-xs text-sm min-h-[44px]">
            @foreach (config('app.supported_locales') as $locale)
                <option value="{{ $locale }}" {{ old('locale', $user->locale) === $locale ? 'selected' : '' }}>
                    {{ $locale === 'es' ? 'Español' : 'English' }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('locale')" />
    </div>

    <div class="flex items-center gap-3">
        <label for="is_profile_public" class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_profile_public" value="0">
            <input type="checkbox" id="is_profile_public" name="is_profile_public" value="1"
                   class="rounded border-border-strong bg-surface-700 text-accent-blue focus:ring-accent-blue focus:ring-offset-surface-800"
                   {{ old('is_profile_public', $user->is_profile_public ?? true) ? 'checked' : '' }}>
            <span class="text-sm text-text-primary">{{ __('profile.public_profile') }}</span>
        </label>
    </div>
    <p class="text-xs text-text-muted -mt-3">{{ __('profile.public_profile_description') }}</p>

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
