@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var array $standingsZones */
/** @var \Illuminate\Support\Collection $knockoutTies */

$hasKnockout = $knockoutTies->isNotEmpty() || $knockoutRounds->isNotEmpty();
$knockoutStarted = $knockoutTies->isNotEmpty();
$defaultTab = $knockoutStarted ? 'knockout' : 'league';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8" x-data="{ activeTab: '{{ $defaultTab }}' }">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __($competition->name) }}</h2>
        </div>

        {{-- Tab Navigation --}}
        @if($hasKnockout)
            <div class="flex border-b border-border-strong mb-0">
                <x-tab-button @click="activeTab = 'league'"
                        x-bind:class="activeTab === 'league' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted hover:text-text-body hover:border-border-strong'">
                    {{ __('game.league_phase') }}
                    @if($leaguePhaseComplete)
                        <span class="ml-1.5 px-1.5 py-0.5 text-[10px] font-bold bg-green-600 text-white rounded-full">{{ __('game.completed') }}</span>
                    @endif
                </x-tab-button>
                <x-tab-button @click="activeTab = 'knockout'"
                        x-bind:class="activeTab === 'knockout' ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted hover:text-text-body hover:border-border-strong'">
                    {{ __('game.knockout_phase') }}
                </x-tab-button>
            </div>
        @endif

        {{-- Knockout Phase Bracket --}}
        @if($hasKnockout)
            <div x-show="activeTab === 'knockout'" class="mt-6">
                <x-cup-bracket
                    :rounds="$knockoutRounds"
                    :ties-by-round="$knockoutTies"
                    :player-team-id="$game->team_id"
                />
            </div>
        @endif

        {{-- League Phase Standings --}}
        <div x-show="activeTab === 'league'" @if($hasKnockout) x-cloak @endif class="mt-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                <div class="md:col-span-2 space-y-3">
                    @if(!$hasKnockout)
                        <h4 class="font-semibold text-lg text-text-body">
                            {{ __('game.league_phase') }}
                        </h4>
                    @endif

                    @include('partials.standings-flat', [
                        'game' => $game,
                        'standings' => $standings,
                        'teamForms' => $teamForms,
                        'standingsZones' => $standingsZones,
                    ])
                </div>

                <x-top-scorers :top-scorers="$topScorers" :player-team-id="$game->team_id" />
            </div>
        </div>
    </div>
</x-app-layout>
