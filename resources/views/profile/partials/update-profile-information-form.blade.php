<form method="post" action="{{ route('profile.update') }}" class="p-5 space-y-5"
      x-data="{
          name: @js(old('name', $user->name)),
          bio: @js(old('bio', $user->bio ?? '')),
          country: @js(old('country', $user->country ?? '')),
          province: @js(old('province', $user->province ?? '')),
          avatar: @js(old('avatar', $user->avatar ?? Arr::random(\App\Models\User::AVATARS))),
          avatarBase: @js(Storage::disk('assets')->url('managers')),
      }">
    @csrf
    @method('patch')

    {{-- Avatar picker --}}
    <div>
        <div class="flex justify-center mb-4">
            <div class="size-32 rounded-full ring-3 ring-accent-blue ring-offset-3 ring-offset-surface-800 overflow-hidden flex items-start justify-center">
                <img :src="avatarBase + '/' + avatar + '.png'" :alt="avatar"
                     class="size-40 max-w-none -mt-2 transition-all duration-300">
            </div>
        </div>
        <p class="text-center text-xs text-text-muted mb-4">{{ __('profile.choose_avatar') }}</p>
        <div class="flex flex-wrap justify-center gap-3">
            @foreach($avatars as $av)
                <label class="cursor-pointer group">
                    <input type="radio" name="avatar" value="{{ $av }}" x-model="avatar" class="sr-only peer">
                    <div class="size-14 rounded-full overflow-hidden ring-2 ring-transparent peer-checked:ring-accent-blue transition-all duration-200 opacity-50 peer-checked:opacity-100 group-hover:opacity-80 group-hover:scale-110 flex items-start justify-center">
                        <img src="{{ Storage::disk('assets')->url('managers/'.$av.'.png') }}" alt="{{ $av }}"
                             class="size-18 max-w-none -mt-1">
                    </div>
                </label>
            @endforeach
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
    </div>

    <div>
        <x-input-label for="name" :value="__('profile.name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" x-model="name" required autofocus autocomplete="name" />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div x-data="{ username: '{{ old('username', $user->username ?? '') }}' }">
        <x-input-label for="username" :value="__('profile.username')" />
        <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" x-model="username" required autocomplete="username" />
        <div class="mt-1 flex justify-between">
            <p class="text-xs text-text-muted">{{ str_replace(':username', old('username', $user->username ?? '...'), __('profile.username_hint')) }}</p>
            <p class="text-xs text-text-muted"><span x-text="username.length">{{ strlen(old('username', $user->username ?? '')) }}</span>/30</p>
        </div>
        <x-input-error class="mt-2" :messages="$errors->get('username')" />
    </div>

    <div>
        <x-input-label for="email" :value="__('profile.email')" />
        <x-text-input id="email" type="email" class="mt-1 block w-full opacity-60 cursor-not-allowed" :value="$user->email" disabled />
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-input-label for="country" :value="__('profile.country')" />
                <select id="country" name="country" x-model="country"
                        class="mt-1 block w-full bg-surface-700 border border-border-strong text-text-primary focus:border-accent-blue focus:ring-accent-blue rounded-lg shadow-xs text-sm min-h-[44px]">
                    <option value="">{{ __('profile.country_placeholder') }}</option>
                    @foreach(\App\Support\ProfileCountries::all() as $code => $name)
                        <option value="{{ $code }}">{{ $name }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('country')" />
            </div>

            <div x-show="country === 'ES'" x-cloak>
                <x-input-label for="province" :value="__('profile.province')" />
                <select id="province" name="province" x-model="province"
                        class="mt-1 block w-full bg-surface-700 border border-border-strong text-text-primary focus:border-accent-blue focus:ring-accent-blue rounded-lg shadow-xs text-sm min-h-[44px]">
                    <option value="">{{ __('profile.province_placeholder') }}</option>
                    @foreach(\App\Support\SpanishProvinces::all() as $province)
                        <option value="{{ $province }}">{{ $province }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('province')" />
            </div>
        </div>
        <p class="mt-1 text-xs text-text-muted">{{ __('profile.location_hint') }}</p>
    </div>

    <div>
        <x-input-label for="locale" :value="__('profile.language')" />
        <select id="locale" name="locale" class="mt-1 block w-full bg-surface-700 border border-border-strong text-text-primary focus:border-accent-blue focus:ring-accent-blue rounded-lg shadow-xs text-sm min-h-[44px]">
            @foreach (config('app.supported_locales') as $locale)
                <option value="{{ $locale }}" {{ old('locale', $user->locale) === $locale ? 'selected' : '' }}>
                    {{ $locale === 'es' ? 'Español' : 'English' }}
                </option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('locale')" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ __('profile.save') }}</x-primary-button>

        @if (session('status') === 'profile-updated')
            <p
                x-data="{ show: true }"
                x-show="show"
                x-transition
                x-init="setTimeout(() => show = false, 2000)"
                class="text-sm text-text-secondary"
            >{{ __('profile.saved') }}</p>
        @endif
    </div>
</form>
