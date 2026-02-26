@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2 mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ __($competition->name) }}</h3>
                        <div class="flex items-center gap-4">
                            @if($cupStatus === 'champion')
                                <span class="px-3 py-1 text-sm bg-yellow-100 text-yellow-700 rounded-full">{{ __('cup.champion') }}</span>
                            @elseif($cupStatus === 'eliminated')
                                <span class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-full">{{ __('cup.eliminated') }}</span>
                            @elseif($cupStatus === 'active')
                                <span class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded-full">{{ __($playerRoundName) }}</span>
                            @elseif($cupStatus === 'advanced')
                                <span class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded-full">{{ __('cup.advanced_to_next_round') }}</span>
                            @else
                                <span class="px-3 py-1 text-sm bg-slate-100 text-slate-600 rounded-full">{{ __('cup.not_yet_entered') }}</span>
                            @endif
                        </div>
                    </div>

                    @if($rounds->isEmpty())
                        <div class="text-center py-12 text-slate-500">
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
                            <div class="mb-8 p-6 rounded-xl bg-gradient-to-r from-sky-50 to-sky-100 border border-sky-200">
                                <div class="text-center text-sm text-sky-600 mb-3">{{ __('cup.your_current_cup_tie', ['round' => $round?->name]) }}</div>
                                <div class="flex items-center justify-center gap-6">
                                    <div class="flex items-center gap-3 flex-1 justify-end">
                                        <span class="text-xl font-semibold @if($playerTie->home_team_id === $game->team_id) text-sky-700 @endif">
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
                                            <div class="text-slate-400">{{ __('game.vs') }}</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 flex-1">
                                        <x-team-crest :team="$playerTie->awayTeam" class="w-12 h-12" />
                                        <span class="text-xl font-semibold @if($playerTie->away_team_id === $game->team_id) text-sky-700 @endif">
                                            {{ $playerTie->awayTeam->name }}
                                        </span>
                                    </div>
                                </div>
                                @if($round?->twoLegged)
                                    <div class="text-center text-sm text-slate-500 mt-2">{{ __('cup.two_legged_tie') }}</div>
                                @endif
                            </div>
                        @elseif($playerTie && $playerTie->completed)
                            @php
                                $won = $playerTie->winner_id === $game->team_id;
                            @endphp
                            <div class="mb-8 p-6 rounded-xl {{ $won ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} border">
                                <div class="text-center text-sm {{ $won ? 'text-green-600' : 'text-red-600' }} mb-3">
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
                                        <span class="text-lg font-semibold @if($playerTie->home_team_id === $game->team_id) {{ $won ? 'text-green-700' : 'text-red-700' }} @endif">
                                            {{ $playerTie->homeTeam->name }}
                                        </span>
                                        <x-team-crest :team="$playerTie->homeTeam" class="w-10 h-10" />
                                    </div>
                                    <div class="px-4 text-lg font-semibold">
                                        {{ $playerTie->getScoreDisplay() }}
                                    </div>
                                    <div class="flex items-center gap-3 flex-1">
                                        <x-team-crest :team="$playerTie->awayTeam" class="w-10 h-10" />
                                        <span class="text-lg font-semibold @if($playerTie->away_team_id === $game->team_id) {{ $won ? 'text-green-700' : 'text-red-700' }} @endif">
                                            {{ $playerTie->awayTeam->name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Cup Bracket --}}
                        <div class="overflow-x-auto">
                            <div class="flex gap-4" style="min-width: fit-content;">
                                @foreach($rounds as $round)
                                    @php $ties = $tiesByRound->get($round->round, collect()); @endphp
                                    <div class="flex-shrink-0 w-64">
                                        <div class="text-center mb-4">
                                            <h4 class="font-semibold text-slate-700">{{ __($round->name) }}</h4>
                                            <div class="text-xs text-slate-400">
                                                {{ $round->firstLegDate->format('M d') }}
                                                @if($round->twoLegged)
                                                    / {{ $round->secondLegDate->format('M d') }}
                                                @endif
                                            </div>
                                        </div>

                                        @if($ties->isEmpty())
                                            <div class="p-4 text-center border border-dashed rounded-lg">
                                                <div class="text-slate-400 text-sm">{{ __('cup.draw_pending') }}</div>
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

                        {{-- Legend --}}
                        <div class="mt-8 pt-4 border-t text-xs text-slate-500">
                            <div class="flex gap-6">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-sky-100 border border-sky-300 rounded"></div>
                                    <span>{{ __('cup.your_matches') }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-green-50 rounded"></div>
                                    <span>{{ __('cup.winner') }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
