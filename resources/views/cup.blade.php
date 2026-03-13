@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2">
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __($competition->name) }}</h2>
                <div class="flex items-center gap-4">
                    @if($cupStatus === 'champion')
                        <span class="px-3 py-1 text-sm bg-accent-gold/10 text-accent-gold rounded-full">{{ __('cup.champion') }}</span>
                    @elseif($cupStatus === 'eliminated')
                        <span class="px-3 py-1 text-sm bg-accent-red/10 text-accent-red rounded-full">{{ __('cup.eliminated') }}</span>
                    @elseif($cupStatus === 'active')
                        <span class="px-3 py-1 text-sm bg-accent-green/10 text-accent-green rounded-full">{{ __($playerRoundName) }}</span>
                    @elseif($cupStatus === 'advanced')
                        <span class="px-3 py-1 text-sm bg-accent-green/10 text-accent-green rounded-full">{{ __('cup.advanced_to_next_round') }}</span>
                    @else
                        <span class="px-3 py-1 text-sm bg-surface-700 text-text-secondary rounded-full">{{ __('cup.not_yet_entered') }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if($rounds->isEmpty())
            <div class="text-center py-12 text-text-muted">
                <p>{{ __('cup.cup_data_not_available') }}</p>
            </div>
        @else
            {{-- Player's Current Tie Highlight --}}
            @if($playerTie && !$playerTie->completed)
                @php
                    $isHome = $playerTie->home_team_id === $game->team_id;
                    $opponent = $isHome ? $playerTie->awayTeam : $playerTie->homeTeam;
                    $round = $rounds->firstWhere('round', $playerTie->round_number);
                @endphp
                <div class="mb-8 p-6 rounded-xl bg-[var(--accent-tint)] border border-accent-blue/20">
                    <div class="text-center text-sm text-accent-blue mb-3">{{ __('cup.your_current_cup_tie', ['round' => __($round?->name)]) }}</div>
                    <div class="flex items-center justify-center gap-6">
                        <div class="flex items-center gap-3 flex-1 justify-end">
                            <span class="text-xl font-semibold @if($playerTie->home_team_id === $game->team_id) text-accent-blue @endif">
                                {{ $playerTie->homeTeam->name }}
                            </span>
                            <x-team-crest :team="$playerTie->homeTeam" class="w-12 h-12" />
                        </div>
                        <div class="px-6 text-center">
                            @if($playerTie->firstLegMatch?->played)
                                <div class="text-2xl font-semibold">
                                    {{ $playerTie->getScoreDisplay() }}
                                </div>
                            @else
                                <div class="text-text-secondary">{{ __('game.vs') }}</div>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 flex-1">
                            <x-team-crest :team="$playerTie->awayTeam" class="w-12 h-12" />
                            <span class="text-xl font-semibold @if($playerTie->away_team_id === $game->team_id) text-accent-blue @endif">
                                {{ $playerTie->awayTeam->name }}
                            </span>
                        </div>
                    </div>
                    @if($round?->twoLegged)
                        <div class="text-center text-sm text-text-muted mt-2">{{ __('cup.two_legged_tie') }}</div>
                    @endif
                </div>
            @elseif($playerTie && $playerTie->completed)
                @php
                    $won = $playerTie->winner_id === $game->team_id;
                @endphp
                <div class="mb-8 p-6 rounded-xl {{ $won ? 'bg-accent-green/10 border-accent-green/20' : 'bg-accent-red/10 border-accent-red/20' }} border">
                    <div class="text-center text-sm {{ $won ? 'text-accent-green' : 'text-accent-red' }} mb-3">
                        @if($cupStatus === 'champion')
                            {{ __('cup.champion_message', ['competition' => __($competition->name)]) }}
                        @elseif($won)
                            {{ __('cup.advanced_to_next_round') }}
                        @else
                            {{ __('cup.eliminated') }}
                        @endif
                    </div>
                    <div class="flex items-center justify-center gap-6">
                        <div class="flex items-center gap-3 flex-1 justify-end">
                            <span class="text-lg font-semibold @if($playerTie->home_team_id === $game->team_id) {{ $won ? 'text-accent-green' : 'text-accent-red' }} @endif">
                                {{ $playerTie->homeTeam->name }}
                            </span>
                            <x-team-crest :team="$playerTie->homeTeam" class="w-10 h-10" />
                        </div>
                        <div class="px-4 text-lg font-semibold">
                            {{ $playerTie->getScoreDisplay() }}
                        </div>
                        <div class="flex items-center gap-3 flex-1">
                            <x-team-crest :team="$playerTie->awayTeam" class="w-10 h-10" />
                            <span class="text-lg font-semibold @if($playerTie->away_team_id === $game->team_id) {{ $won ? 'text-accent-green' : 'text-accent-red' }} @endif">
                                {{ $playerTie->awayTeam->name }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Cup Bracket --}}
            <div class="bg-surface-800 rounded-xl border border-border-default p-4 md:p-6">
                <div class="overflow-x-auto">
                    <div class="flex gap-4" style="min-width: fit-content;">
                        @foreach($rounds as $round)
                            @php $ties = $tiesByRound->get($round->round, collect()); @endphp
                            <div class="shrink-0 w-64">
                                <div class="text-center mb-4">
                                    <h4 class="font-semibold text-text-body">{{ __($round->name) }}</h4>
                                    <div class="text-xs text-text-secondary">
                                        {{ $round->firstLegDate->format('M d') }}
                                        @if($round->twoLegged)
                                            / {{ $round->secondLegDate->format('M d') }}
                                        @endif
                                    </div>
                                </div>

                                @if($ties->isEmpty())
                                    <div class="p-4 text-center border border-dashed rounded-lg">
                                        <div class="text-text-secondary text-sm">{{ __('cup.draw_pending') }}</div>
                                    </div>
                                @else
                                    <div class="space-y-2">
                                        @foreach($ties as $tie)
                                            <x-cup-tie-card :tie="$tie" :player-team-id="$game->team_id" />
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Legend --}}
            <div class="mt-6 text-xs text-text-muted">
                <div class="flex gap-6">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-accent-blue/10 border border-accent-blue/30 rounded-sm"></div>
                        <span>{{ __('cup.your_matches') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 bg-accent-green/10 rounded-sm"></div>
                        <span>{{ __('cup.winner') }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
