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

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8" x-data="{ tab: '{{ $groupStageComplete && $knockoutTies->isNotEmpty() ? 'knockout' : 'groups' }}' }">

                    {{-- Header --}}
                    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-3 mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ __($competition->name) }}</h3>
                        <div class="flex items-center gap-3">
                            @if($knockoutStatus === 'champion')
                                <span class="px-3 py-1 text-sm bg-yellow-100 text-yellow-700 rounded-full">{{ __('cup.champion') }}</span>
                            @elseif($knockoutStatus === 'eliminated')
                                <span class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded-full">{{ __('cup.eliminated') }}</span>
                            @elseif($knockoutStatus === 'active')
                                <span class="px-3 py-1 text-sm bg-green-100 text-green-700 rounded-full">{{ __($playerTie?->firstLegMatch?->round_name ?? '') }}</span>
                            @elseif($knockoutStatus === 'qualified')
                                <span class="px-3 py-1 text-sm bg-emerald-100 text-emerald-700 rounded-full">{{ __('game.knockout_qualified') }}</span>
                            @elseif($knockoutStatus === 'group_stage')
                                <span class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded-full">{{ __('game.group_stage') }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Tab Navigation --}}
                    <div class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto scrollbar-hide">
                        <button @click="tab = 'groups'"
                                :class="tab === 'groups' ? 'border-b-2 border-red-500 text-red-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                                class="px-4 py-2.5 text-sm whitespace-nowrap shrink-0 min-h-[44px]">
                            {{ __('game.group_stage') }}
                        </button>
                        <button @click="tab = 'knockout'"
                                :class="tab === 'knockout' ? 'border-b-2 border-red-500 text-red-600 font-semibold' : 'text-slate-500 hover:text-slate-700'"
                                class="px-4 py-2.5 text-sm whitespace-nowrap shrink-0 min-h-[44px] flex items-center gap-2">
                            {{ __('game.knockout_phase') }}
                            @if(!$groupStageComplete)
                                <span class="w-2 h-2 rounded-full bg-slate-300"></span>
                            @elseif($knockoutTies->isNotEmpty())
                                <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            @endif
                        </button>
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
                            <div class="text-center py-12 text-slate-500">
                                <p>{{ __('game.no_standings_yet') }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Knockout Tab --}}
                    <div x-show="tab === 'knockout'" x-cloak>
                        @if(!$groupStageComplete)
                            <div class="text-center py-12">
                                <div class="text-4xl mb-3">&#9917;</div>
                                <p class="text-slate-500 text-sm">{{ __('game.knockout_not_started') }}</p>
                                <p class="text-slate-400 text-xs mt-1">{{ __('game.knockout_not_started_desc') }}</p>
                            </div>
                        @elseif($knockoutTies->isEmpty())
                            <div class="text-center py-12">
                                <div class="text-4xl mb-3">&#127942;</div>
                                <p class="text-slate-500 text-sm">{{ __('game.knockout_generating') }}</p>
                            </div>
                        @else
                            {{-- Player's Current Tie Highlight --}}
                            @if($playerTie && !$playerTie->completed)
                                @php
                                    $isHome = $playerTie->home_team_id === $game->team_id;
                                    $opponent = $isHome ? $playerTie->awayTeam : $playerTie->homeTeam;
                                @endphp
                                <div class="mb-8 p-6 rounded-xl bg-gradient-to-r from-sky-50 to-sky-100 border border-sky-200">
                                    <div class="text-center text-sm text-sky-600 mb-3">{{ __('cup.your_current_cup_tie', ['round' => __($playerTie->firstLegMatch?->round_name ?? '')]) }}</div>
                                    <div class="flex items-center justify-center gap-6">
                                        <div class="flex items-center gap-3 flex-1 justify-end">
                                            <span class="text-lg md:text-xl font-semibold @if($playerTie->home_team_id === $game->team_id) text-sky-700 @endif truncate">
                                                {{ $playerTie->homeTeam->name }}
                                            </span>
                                            <x-team-crest :team="$playerTie->homeTeam" class="w-10 h-10 md:w-12 md:h-12 shrink-0" />
                                        </div>
                                        <div class="px-4 md:px-6 text-center">
                                            @if($playerTie->firstLegMatch?->played)
                                                <div class="text-2xl font-semibold">{{ $playerTie->getScoreDisplay() }}</div>
                                            @else
                                                <div class="text-slate-400">{{ __('game.vs') }}</div>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-3 flex-1">
                                            <x-team-crest :team="$playerTie->awayTeam" class="w-10 h-10 md:w-12 md:h-12 shrink-0" />
                                            <span class="text-lg md:text-xl font-semibold @if($playerTie->away_team_id === $game->team_id) text-sky-700 @endif truncate">
                                                {{ $playerTie->awayTeam->name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @elseif($playerTie && $playerTie->completed)
                                @php $won = $playerTie->winner_id === $game->team_id; @endphp
                                <div class="mb-8 p-5 rounded-xl {{ $won ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }} border">
                                    <div class="text-center text-sm {{ $won ? 'text-green-600' : 'text-red-600' }} mb-3">
                                        @if($knockoutStatus === 'champion')
                                            {{ __('cup.champion_message', ['competition' => __($competition->name)]) }}
                                        @elseif($won)
                                            {{ __('cup.advanced_to_next_round') }}
                                        @else
                                            {{ __('cup.eliminated') }}
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-center gap-6">
                                        <div class="flex items-center gap-3 flex-1 justify-end">
                                            <span class="text-base md:text-lg font-semibold @if($playerTie->home_team_id === $game->team_id) {{ $won ? 'text-green-700' : 'text-red-700' }} @endif truncate">
                                                {{ $playerTie->homeTeam->name }}
                                            </span>
                                            <x-team-crest :team="$playerTie->homeTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                                        </div>
                                        <div class="px-4 text-lg font-semibold">{{ $playerTie->getScoreDisplay() }}</div>
                                        <div class="flex items-center gap-3 flex-1">
                                            <x-team-crest :team="$playerTie->awayTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                                            <span class="text-base md:text-lg font-semibold @if($playerTie->away_team_id === $game->team_id) {{ $won ? 'text-green-700' : 'text-red-700' }} @endif truncate">
                                                {{ $playerTie->awayTeam->name }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Knockout Bracket --}}
                            <div class="overflow-x-auto">
                                <div class="flex gap-4" style="min-width: fit-content;">
                                    @foreach($knockoutRounds as $round)
                                        @php $ties = $knockoutTies->get($round->round, collect()); @endphp
                                        <div class="flex-shrink-0 w-64">
                                            <div class="text-center mb-4">
                                                <h4 class="font-semibold text-slate-700">{{ __($round->name) }}</h4>
                                                <div class="text-xs text-slate-400">{{ $round->firstLegDate->format('d M') }}</div>
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
    </div>
</x-app-layout>
