@use(App\Support\Money)

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Club hub title + subnav --}}
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('club.hub_title') }}</h2>
        </div>
        <x-club-section-nav :game="$game" active="commercial" />

        <x-flash-message type="success" :message="session('success')" class="mt-4" />
        <x-flash-message type="error" :message="session('error')" class="mt-4" />

        {{-- Intro: this is where you grow recurring income. --}}
        <div class="mt-6 bg-surface-800 border border-border-default rounded-xl px-5 py-5">
            <h3 class="font-heading text-lg font-bold uppercase tracking-wide text-text-primary">{{ __('club.commercial.title') }}</h3>
            <p class="mt-2 text-sm text-text-secondary leading-relaxed">{{ __('club.commercial.intro') }}</p>
        </div>

        {{-- Naming rights --}}
        <div class="mt-4">
            <x-section-card :title="__('club.commercial.naming_rights_title')">
                <div class="px-5 py-5">

                    {{-- Current ground identity --}}
                    <div class="flex items-center justify-between gap-3 px-3.5 py-3 bg-surface-700 border border-border-default rounded-lg">
                        <div class="min-w-0">
                            <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.naming_rights.current_name') }}</div>
                            <div class="font-heading text-base font-bold text-text-primary truncate">{{ $namingRights['currentName'] ?? '—' }}</div>
                        </div>
                        <span class="shrink-0 text-[10px] uppercase tracking-wide px-2 py-1 rounded-md bg-surface-600 text-text-secondary">
                            {{ __('club.stadium.naming_rights.source_' . $namingRights['source']) }}
                        </span>
                    </div>

                    @if($namingRights['activeDeal'])
                        {{-- Active sponsorship --}}
                        @php($deal = $namingRights['activeDeal'])
                        <div class="mt-4 px-4 py-4 bg-accent-green/10 border border-accent-green/30 rounded-lg">
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm font-semibold text-text-primary">{{ $deal['sponsor_name'] }}</span>
                                <span class="text-[11px] text-text-secondary">{{ trans_choice('club.stadium.naming_rights.seasons_remaining', $deal['seasons_remaining'], ['count' => $deal['seasons_remaining']]) }}</span>
                            </div>
                            <div class="mt-3 text-sm">
                                <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('club.stadium.naming_rights.annual_value') }}</div>
                                <div class="font-heading text-lg font-bold text-accent-green tabular-nums">{{ Money::format($deal['annual_value_cents']) }}</div>
                            </div>
                        </div>
                    @elseif($namingRights['windowOpen'])
                        @php($seek = $namingRights['seek'])

                        @if(count($namingRights['offers']) > 0)
                            {{-- Offer board — once offers are in, the cards speak for
                                 themselves; the seek/agency control is hidden. --}}
                            <div class="mt-6">
                                <div class="text-[10px] text-text-muted uppercase tracking-widest mb-3">{{ __('club.stadium.naming_rights.offers_title') }}</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    @foreach($namingRights['offers'] as $offer)
                                        <x-naming-rights-offer-card :offer="$offer" :game="$game" />
                                    @endforeach
                                </div>
                            </div>
                        @else
                            {{-- Seek control — shown only while the board is empty --}}
                            <div class="mt-4 px-4 py-4 bg-surface-700 border border-border-default rounded-lg">
                                <p class="text-xs text-text-secondary leading-relaxed">
                                    {{ __('club.commercial.seek_explainer', [
                                        'fee' => Money::format($seek['feeCents']),
                                        'days' => $seek['cooldownLength'],
                                    ]) }}
                                </p>

                                <div class="mt-3">
                                    @if($seek['canSeek'] && $seek['feeAffordable'])
                                        <form method="POST" action="{{ route('game.club.commercial.seek', $game->id) }}">
                                            @csrf
                                            <x-primary-button color="green">
                                                {{ __('club.commercial.seek_button', ['fee' => Money::format($seek['feeCents'])]) }}
                                            </x-primary-button>
                                        </form>
                                    @elseif($seek['cooldownDays'] > 0)
                                        <p class="text-[11px] text-accent-gold">{{ trans_choice('club.commercial.seek_cooldown', $seek['cooldownDays'], ['days' => $seek['cooldownDays']]) }}</p>
                                    @elseif(! $seek['feeAffordable'])
                                        <p class="text-[11px] text-accent-gold">{{ __('club.commercial.seek_unaffordable', ['fee' => Money::format($seek['feeCents'])]) }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @else
                        {{-- Window closed (league under way) --}}
                        <p class="mt-4 text-[11px] text-accent-gold leading-relaxed">{{ __('club.stadium.naming_rights.window_closed_notice') }}</p>
                    @endif
                </div>
            </x-section-card>
        </div>
    </div>
</x-app-layout>
