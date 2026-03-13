@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        {{-- Page Title --}}
        <div class="mt-6 mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2">
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __($competition->name) }}</h2>
                @if($cupStatus === 'champion')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-gold/20 text-accent-gold">{{ __('cup.champion') }}</span>
                @elseif($cupStatus === 'eliminated')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-red/20 text-accent-red">{{ __('cup.eliminated') }}</span>
                @elseif($cupStatus === 'active')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-green/20 text-accent-green">{{ __($playerRoundName) }}</span>
                @elseif($cupStatus === 'advanced')
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-accent-green/20 text-accent-green">{{ __('cup.advanced_to_next_round') }}</span>
                @else
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold bg-white/10 text-text-secondary">{{ __('cup.not_yet_entered') }}</span>
                @endif
            </div>
        </div>

        @if($rounds->isEmpty())
            <div class="text-center py-12 text-text-muted">
                <p>{{ __('cup.cup_data_not_available') }}</p>
            </div>
        @else
            {{-- Player's Current Tie Highlight --}}
            @if($playerTie)
                <x-cup-player-tie
                    :tie="$playerTie"
                    :player-team-id="$game->team_id"
                    :competition-name="$competition->name"
                    :cup-status="$cupStatus"
                    :round-name="$playerTie->completed ? null : ($rounds->firstWhere('round', $playerTie->round_number)?->name)"
                />
            @endif

            {{-- Cup Bracket --}}
            <x-cup-bracket
                :rounds="$rounds"
                :ties-by-round="$tiesByRound"
                :player-team-id="$game->team_id"
            />
        @endif
    </div>
</x-app-layout>
