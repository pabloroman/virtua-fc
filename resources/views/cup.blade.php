@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ $competition->name }}</h3>
                        <div class="flex items-center gap-4">
                            @if($game->cup_eliminated)
                                <span class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-full">Eliminated</span>
                            @elseif($game->cup_round > 0)
                                <span class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded-full">
                                    Round {{ $game->cup_round }}
                                </span>
                            @else
                                <span class="px-3 py-1 text-sm bg-gray-100 text-gray-600 rounded-full">Not yet entered</span>
                            @endif
                            <a href="{{ route('show-game', $game->id) }}" class="text-sky-600 hover:text-sky-800">Back to Dashboard</a>
                        </div>
                    </div>

                    @if($rounds->isEmpty())
                        <div class="text-center py-12 text-gray-500">
                            <p>Cup data not available.</p>
                        </div>
                    @else
                        {{-- Player's Current Tie Highlight --}}
                        @if($playerTie && !$playerTie->completed)
                            @php
                                $isHome = $playerTie->home_team_id === $game->team_id;
                                $opponent = $isHome ? $playerTie->awayTeam : $playerTie->homeTeam;
                                $round = $rounds->firstWhere('round_number', $playerTie->round_number);
                            @endphp
                            <div class="mb-8 p-6 rounded-xl bg-gradient-to-r from-sky-50 to-sky-100 border border-sky-200">
                                <div class="text-center text-sm text-sky-600 mb-3">Your Current Cup Tie - {{ $round?->round_name }}</div>
                                <div class="flex items-center justify-center gap-6">
                                    <div class="flex items-center gap-3 flex-1 justify-end">
                                        <span class="text-xl font-semibold @if($playerTie->home_team_id === $game->team_id) text-sky-700 @endif">
                                            {{ $playerTie->homeTeam->name }}
                                        </span>
                                        <img src="{{ $playerTie->homeTeam->image }}" class="w-12 h-12">
                                    </div>
                                    <div class="px-6 text-center">
                                        @if($playerTie->firstLegMatch?->played)
                                            <div class="text-2xl font-semibold">
                                                {{ $playerTie->getScoreDisplay() }}
                                            </div>
                                        @else
                                            <div class="text-gray-400">vs</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-3 flex-1">
                                        <img src="{{ $playerTie->awayTeam->image }}" class="w-12 h-12">
                                        <span class="text-xl font-semibold @if($playerTie->away_team_id === $game->team_id) text-sky-700 @endif">
                                            {{ $playerTie->awayTeam->name }}
                                        </span>
                                    </div>
                                </div>
                                @if($round?->isTwoLegged())
                                    <div class="text-center text-sm text-gray-500 mt-2">Two-legged tie</div>
                                @endif
                            </div>
                        @elseif($playerTie && $playerTie->completed)
                            @php
                                $won = $playerTie->winner_id === $game->team_id;
                            @endphp
                            <div class="mb-8 p-6 rounded-xl {{ $won ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} border">
                                <div class="text-center text-sm {{ $won ? 'text-green-600' : 'text-red-600' }} mb-3">
                                    {{ $won ? 'Advanced to Next Round!' : 'Eliminated' }}
                                </div>
                                <div class="flex items-center justify-center gap-6">
                                    <div class="flex items-center gap-3 flex-1 justify-end">
                                        <span class="text-lg font-semibold @if($playerTie->home_team_id === $game->team_id) {{ $won ? 'text-green-700' : 'text-red-700' }} @endif">
                                            {{ $playerTie->homeTeam->name }}
                                        </span>
                                        <img src="{{ $playerTie->homeTeam->image }}" class="w-10 h-10">
                                    </div>
                                    <div class="px-4 text-lg font-semibold">
                                        {{ $playerTie->getScoreDisplay() }}
                                    </div>
                                    <div class="flex items-center gap-3 flex-1">
                                        <img src="{{ $playerTie->awayTeam->image }}" class="w-10 h-10">
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
                                    @php $ties = $tiesByRound->get($round->round_number, collect()); @endphp
                                    <div class="flex-shrink-0 w-64">
                                        <div class="text-center mb-4">
                                            <h4 class="font-semibold text-gray-700">{{ $round->round_name }}</h4>
                                            <div class="text-xs text-gray-400">
                                                {{ $round->first_leg_date->format('M d') }}
                                                @if($round->isTwoLegged())
                                                    / {{ $round->second_leg_date->format('M d') }}
                                                @endif
                                            </div>
                                        </div>

                                        @if($ties->isEmpty())
                                            <div class="p-4 text-center border border-dashed rounded-lg">
                                                <div class="text-gray-400 text-sm mb-2">Draw pending</div>
                                                @php
                                                    $canDraw = app(\App\Game\Services\CupDrawService::class)
                                                        ->needsDrawForRound($game->id, 'ESPCUP', $round->round_number);
                                                @endphp
                                                @if($canDraw)
                                                    <form method="POST" action="{{ route('game.cup.draw', [$game->id, $round->round_number]) }}">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-3 py-1 bg-sky-500 text-white rounded hover:bg-sky-600">
                                                            Conduct Draw
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @else
                                            <div class="space-y-2">
                                                @foreach($ties as $tie)
                                                    @php
                                                        $isPlayerTie = $tie->involvesTeam($game->team_id);
                                                        $homeWon = $tie->winner_id === $tie->home_team_id;
                                                        $awayWon = $tie->winner_id === $tie->away_team_id;
                                                    @endphp
                                                    <div class="border rounded-lg overflow-hidden {{ $isPlayerTie ? 'border-sky-300 bg-sky-50' : 'border-gray-200' }}">
                                                        {{-- Home Team --}}
                                                        <div class="flex items-center gap-2 p-2 {{ $homeWon ? 'bg-green-50' : '' }} {{ $awayWon ? 'opacity-50' : '' }}">
                                                            <img src="{{ $tie->homeTeam->image }}" class="w-5 h-5">
                                                            <span class="flex-1 text-sm truncate @if($homeWon) font-semibold @endif {{ $tie->home_team_id === $game->team_id ? 'font-semibold text-sky-700' : '' }}">
                                                                {{ $tie->homeTeam->name }}
                                                            </span>
                                                            @if($tie->firstLegMatch?->played)
                                                                <span class="text-sm {{ $homeWon ? 'font-semibold' : '' }}">
                                                                    {{ $tie->firstLegMatch->home_score }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        {{-- Away Team --}}
                                                        <div class="flex items-center gap-2 p-2 border-t {{ $awayWon ? 'bg-green-50' : '' }} {{ $homeWon ? 'opacity-50' : '' }}">
                                                            <img src="{{ $tie->awayTeam->image }}" class="w-5 h-5">
                                                            <span class="flex-1 text-sm truncate @if($awayWon) font-semibold @endif {{ $tie->away_team_id === $game->team_id ? 'font-semibold text-sky-700' : '' }}">
                                                                {{ $tie->awayTeam->name }}
                                                            </span>
                                                            @if($tie->firstLegMatch?->played)
                                                                <span class="text-sm {{ $awayWon ? 'font-semibold' : '' }}">
                                                                    {{ $tie->firstLegMatch->away_score }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        {{-- Resolution info --}}
                                                        @if($tie->completed && $tie->resolution && $tie->resolution['type'] !== 'normal')
                                                            <div class="text-xs text-center text-gray-400 py-1 border-t bg-gray-50">
                                                                @if($tie->resolution['type'] === 'penalties')
                                                                    Pens: {{ $tie->resolution['penalties'] }}
                                                                @elseif($tie->resolution['type'] === 'extra_time')
                                                                    AET
                                                                @elseif($tie->resolution['type'] === 'away_goals')
                                                                    Away goals
                                                                @elseif($tie->resolution['type'] === 'aggregate')
                                                                    Agg: {{ $tie->resolution['aggregate'] }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Legend --}}
                        <div class="mt-8 pt-4 border-t text-xs text-gray-500">
                            <div class="flex gap-6">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-sky-100 border border-sky-300 rounded"></div>
                                    <span>Your matches</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 bg-green-50 rounded"></div>
                                    <span>Winner</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
