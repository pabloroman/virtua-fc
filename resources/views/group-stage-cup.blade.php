@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var \Illuminate\Support\Collection|null $groupedStandings */
/** @var array $teamForms */
/** @var \Illuminate\Support\Collection $topScorers */
/** @var bool $groupStageComplete */
/** @var \Illuminate\Support\Collection $knockoutRounds */
/** @var \Illuminate\Support\Collection $knockoutTies */
/** @var App\Models\CupTie|null $playerTie */
/** @var string $knockoutStatus */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8" x-data="{ tab: '{{ $groupStageComplete && $knockoutTies->isNotEmpty() ? 'knockout' : 'groups' }}' }">
        <div class="mt-6 mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2">
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __($competition->name) }}</h2>
                @if($knockoutStatus === 'champion')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-gold/20 text-accent-gold">{{ __('cup.champion') }}</span>
                @elseif($knockoutStatus === 'eliminated')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-red/20 text-accent-red">{{ __('cup.eliminated') }}</span>
                @elseif($knockoutStatus === 'active')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-green/20 text-accent-green">{{ __($playerTie?->firstLegMatch?->round_name ?? '') }}</span>
                @elseif($knockoutStatus === 'qualified')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-green/20 text-accent-green">{{ __('game.knockout_qualified') }}</span>
                @elseif($knockoutStatus === 'group_stage')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-blue/20 text-accent-blue">{{ __('game.group_stage') }}</span>
                @endif
            </div>
        </div>

        {{-- Tab Navigation --}}
        <div class="flex gap-1 border-b border-border-strong mb-6 overflow-x-auto scrollbar-hide">
            <x-tab-button @click="tab = 'groups'"
                    x-bind:class="tab === 'groups' ? 'border-b-2 border-red-500 text-accent-red font-semibold' : 'text-text-muted hover:text-text-body border-transparent'"
                    class="shrink-0 min-h-[44px]">
                {{ __('game.group_stage') }}
            </x-tab-button>
            <x-tab-button @click="tab = 'knockout'"
                    x-bind:class="tab === 'knockout' ? 'border-b-2 border-red-500 text-accent-red font-semibold' : 'text-text-muted hover:text-text-body border-transparent'"
                    class="shrink-0 min-h-[44px] gap-2">
                {{ __('game.knockout_phase') }}
                @if(!$groupStageComplete)
                    <span class="w-2 h-2 rounded-full bg-surface-600"></span>
                @elseif($knockoutTies->isNotEmpty())
                    <span class="w-2 h-2 rounded-full bg-accent-green"></span>
                @endif
            </x-tab-button>
        </div>

        {{-- Groups Tab --}}
        <div x-show="tab === 'groups'" x-cloak>
            @if(!empty($groupedStandings))
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8">
                    @include('partials.standings-grouped', [
                        'game' => $game,
                        'competition' => $competition,
                        'groupedStandings' => $groupedStandings,
                        'teamForms' => $teamForms,
                    ])

                    <x-top-scorers :top-scorers="$topScorers" :player-team-id="$game->team_id" />
                </div>
            @else
                <div class="text-center py-12 text-text-muted">
                    <p>{{ __('game.no_standings_yet') }}</p>
                </div>
            @endif
        </div>

        {{-- Knockout Tab --}}
        <div x-show="tab === 'knockout'" x-cloak>
            @if(!$groupStageComplete)
                <div class="text-center py-12">
                    <div class="text-4xl mb-3">&#9917;</div>
                    <p class="text-text-muted text-sm">{{ __('game.knockout_not_started') }}</p>
                    <p class="text-text-secondary text-xs mt-1">{{ __('game.knockout_not_started_desc') }}</p>
                </div>
            @elseif($knockoutTies->isEmpty())
                <div class="text-center py-12">
                    <div class="text-4xl mb-3">&#127942;</div>
                    <p class="text-text-muted text-sm">{{ __('game.knockout_generating') }}</p>
                </div>
            @else
                {{-- Player's Current Tie Highlight --}}
                @if($playerTie)
                    <x-cup-player-tie
                        :tie="$playerTie"
                        :player-team-id="$game->team_id"
                        :competition-name="$competition->name"
                        :cup-status="$knockoutStatus"
                        :round-name="$playerTie->completed ? null : ($playerTie->firstLegMatch?->round_name)"
                    />
                @endif

                {{-- Knockout Bracket --}}
                <x-cup-bracket
                    :rounds="$knockoutRounds"
                    :ties-by-round="$knockoutTies"
                    :player-team-id="$game->team_id"
                />
            @endif
        </div>
    </div>
</x-app-layout>
