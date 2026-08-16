@props([
    'game',
    'area',
    'tier' => 0,
])

@unless($game->isTournamentMode())
    @php
        $maxed = $tier >= 4;
        $storageKey = "invest_notice_{$area}_{$game->id}";
        $prefix = 'finances.invest_notice_'.$area;
        // One emblematic icon per area (colour conveys the funded/under-funded state).
        $iconPath = match ($area) {
            'medical' => 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z',
            'scouting' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
            default => 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5',
        };
    @endphp

    <div
        x-data="{ dismissed: localStorage.getItem('{{ $storageKey }}') === '1' }"
        x-show="!dismissed"
        x-cloak
        {{ $attributes }}
    >
        <x-status-banner
            :color="$maxed ? 'green' : 'blue'"
            :title="__($prefix.($maxed ? '_maxed_title' : '_title'))"
            :description="__($prefix.($maxed ? '_maxed_body' : '_body'))"
        >
            <x-slot name="icon">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" /></svg>
            </x-slot>

            <div class="flex items-center gap-2 md:gap-3">
                @unless($maxed)
                    <x-primary-button-link color="blue" :href="route('game.club.investment', $game->id)" size="sm">
                        {{ __('finances.invest_notice_cta') }}
                    </x-primary-button-link>
                @endunless
                <button
                    type="button"
                    @click="dismissed = true; localStorage.setItem('{{ $storageKey }}', '1')"
                    class="shrink-0 opacity-60 hover:opacity-100 transition"
                    aria-label="{{ __('app.close') }}"
                >
                    <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </x-status-banner>
    </div>
@endunless
