@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var \Illuminate\Support\Collection $groupStandings */
/** @var \Illuminate\Support\Collection $knockoutTies */
/** @var string|null $championTeamId */
/** @var \Illuminate\Support\Collection $yourMatches */
/** @var App\Models\GameStanding|null $playerStanding */
/** @var array $yourRecord */
/** @var \Illuminate\Support\Collection $topScorers */
/** @var \Illuminate\Support\Collection $topAssisters */
/** @var App\Models\GamePlayer|null $bestGoalkeeper */
/** @var \Illuminate\Support\Collection $yourSquadStats */

$isChampion = $championTeamId === $game->team_id;
$yourGoalScorers = $yourSquadStats->where('goals', '>', 0)->sortByDesc('goals');
$yourAppearances = $yourSquadStats->where('appearances', '>', 0)->sortByDesc('appearances');
@endphp

<x-app-layout>
    <div class="min-h-screen">

        {{-- Hero Section --}}
        <div class="relative overflow-hidden {{ $isChampion ? 'bg-gradient-to-b from-amber-600 via-amber-500 to-amber-400' : 'bg-gradient-to-b from-slate-800 via-slate-700 to-slate-600' }} py-12 md:py-20">
            {{-- Decorative elements --}}
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-20 -left-20 w-60 h-60 bg-white/5 rounded-full"></div>
                <div class="absolute -bottom-10 -right-10 w-80 h-80 bg-white/5 rounded-full"></div>
                @if($isChampion)
                <div class="absolute top-8 left-1/4 text-amber-300/30 text-4xl">&#9733;</div>
                <div class="absolute top-16 right-1/4 text-amber-300/30 text-3xl">&#9733;</div>
                <div class="absolute bottom-8 left-1/3 text-amber-300/30 text-2xl">&#9733;</div>
                @endif
            </div>

            <div class="relative max-w-4xl mx-auto px-4 text-center">
                {{-- Trophy --}}
                <div class="text-6xl md:text-8xl mb-4">
                    @if($isChampion)
                        &#127942;
                    @else
                        &#9917;
                    @endif
                </div>

                {{-- Title --}}
                @if($isChampion)
                    <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-2 tracking-tight">
                        {{ __('season.tournament_champion') }}
                    </h1>
                    <p class="text-lg md:text-xl text-amber-100 font-medium">{{ $competition->name ?? 'World Cup' }} 2026</p>
                @else
                    <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-2 tracking-tight">
                        {{ __('season.tournament_complete') }}
                    </h1>
                    <p class="text-lg md:text-xl text-slate-300 font-medium">{{ $competition->name ?? 'World Cup' }} 2026</p>
                @endif

                {{-- Team Badge --}}
                <div class="mt-6 md:mt-8 inline-flex flex-col items-center">
                    <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}"
                         class="w-20 h-20 md:w-28 md:h-28 {{ $isChampion ? 'drop-shadow-lg' : '' }}">
                    <div class="mt-3 text-xl md:text-2xl font-bold text-white">{{ $game->team->name }}</div>
                    @if($playerStanding)
                    <div class="mt-1 text-sm {{ $isChampion ? 'text-amber-100' : 'text-slate-300' }}">
                        {{ __('season.group_label', ['group' => $playerStanding->group_label]) }}
                        &middot;
                        {{ __('season.finished_position', ['position' => $playerStanding->position]) }}
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 md:-mt-8 relative z-10 pb-12">

            {{-- Your Tournament Record --}}
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-5 md:p-8 mb-6">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-5 text-center">{{ __('season.your_tournament') }}</h2>

                {{-- Stats Grid --}}
                <div class="grid grid-cols-3 md:grid-cols-7 gap-3 md:gap-4 text-center">
                    <div>
                        <div class="text-2xl md:text-3xl font-bold text-slate-900">{{ $yourRecord['played'] }}</div>
                        <div class="text-xs text-slate-400 uppercase mt-1">{{ __('season.played_abbr') }}</div>
                    </div>
                    <div>
                        <div class="text-2xl md:text-3xl font-bold text-green-600">{{ $yourRecord['won'] }}</div>
                        <div class="text-xs text-slate-400 uppercase mt-1">{{ __('season.won') }}</div>
                    </div>
                    <div>
                        <div class="text-2xl md:text-3xl font-bold text-slate-400">{{ $yourRecord['drawn'] }}</div>
                        <div class="text-xs text-slate-400 uppercase mt-1">{{ __('season.drawn') }}</div>
                    </div>
                    <div class="hidden md:block">
                        <div class="text-2xl md:text-3xl font-bold text-red-500">{{ $yourRecord['lost'] }}</div>
                        <div class="text-xs text-slate-400 uppercase mt-1">{{ __('season.lost') }}</div>
                    </div>
                    <div class="hidden md:block">
                        <div class="text-2xl md:text-3xl font-bold text-slate-900">{{ $yourRecord['goalsFor'] }}</div>
                        <div class="text-xs text-slate-400 uppercase mt-1">{{ __('season.goals_for') }}</div>
                    </div>
                    <div class="hidden md:block">
                        <div class="text-2xl md:text-3xl font-bold text-slate-900">{{ $yourRecord['goalsAgainst'] }}</div>
                        <div class="text-xs text-slate-400 uppercase mt-1">{{ __('season.goals_against') }}</div>
                    </div>
                    <div class="hidden md:block">
                        <div class="text-2xl md:text-3xl font-bold {{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] >= 0 ? 'text-green-600' : 'text-red-500' }}">
                            {{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] >= 0 ? '+' : '' }}{{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] }}
                        </div>
                        <div class="text-xs text-slate-400 uppercase mt-1">{{ __('season.goal_diff_abbr') }}</div>
                    </div>
                </div>

                {{-- Mobile: extra row for hidden stats --}}
                <div class="grid grid-cols-4 gap-3 text-center mt-3 md:hidden">
                    <div>
                        <div class="text-xl font-bold text-red-500">{{ $yourRecord['lost'] }}</div>
                        <div class="text-[10px] text-slate-400 uppercase">{{ __('season.lost') }}</div>
                    </div>
                    <div>
                        <div class="text-xl font-bold text-slate-900">{{ $yourRecord['goalsFor'] }}</div>
                        <div class="text-[10px] text-slate-400 uppercase">{{ __('season.goals_for') }}</div>
                    </div>
                    <div>
                        <div class="text-xl font-bold text-slate-900">{{ $yourRecord['goalsAgainst'] }}</div>
                        <div class="text-[10px] text-slate-400 uppercase">{{ __('season.goals_against') }}</div>
                    </div>
                    <div>
                        <div class="text-xl font-bold {{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] >= 0 ? 'text-green-600' : 'text-red-500' }}">
                            {{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] >= 0 ? '+' : '' }}{{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] }}
                        </div>
                        <div class="text-[10px] text-slate-400 uppercase">{{ __('season.goal_diff_abbr') }}</div>
                    </div>
                </div>
            </div>

            {{-- Your Matches Journey --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-8 mb-6">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-5 text-center">{{ __('season.your_journey') }}</h2>

                <div class="space-y-2">
                    @foreach($yourMatches as $match)
                    @php
                        $isHome = $match->home_team_id === $game->team_id;
                        $opponent = $isHome ? $match->awayTeam : $match->homeTeam;
                        $scored = $isHome ? $match->home_score : $match->away_score;
                        $conceded = $isHome ? $match->away_score : $match->home_score;
                        $resultClass = $scored > $conceded ? 'bg-green-500' : ($scored < $conceded ? 'bg-red-500' : 'bg-slate-400');
                        $resultLetter = $scored > $conceded ? 'W' : ($scored < $conceded ? 'L' : 'D');
                    @endphp
                    <div class="flex items-center gap-3 py-2.5 px-3 rounded-lg {{ $loop->even ? 'bg-slate-50' : '' }}">
                        {{-- Result Badge --}}
                        <span class="shrink-0 w-7 h-7 rounded text-xs font-bold flex items-center justify-center text-white {{ $resultClass }}">
                            {{ $resultLetter }}
                        </span>

                        {{-- Round --}}
                        <span class="hidden md:inline text-xs text-slate-400 w-16 shrink-0">
                            {{ $match->round_name ?? __('game.matchday_n', ['number' => $match->round_number]) }}
                        </span>

                        {{-- Opponent --}}
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <img src="{{ $opponent->image }}" class="w-5 h-5 shrink-0">
                            <span class="text-sm font-medium text-slate-900 truncate">
                                {{ $isHome ? '' : '@ ' }}{{ $opponent->name }}
                            </span>
                        </div>

                        {{-- Score --}}
                        <div class="shrink-0 text-sm font-bold text-slate-900">
                            {{ $scored }}-{{ $conceded }}
                        </div>

                        {{-- Extra time / penalties indicator --}}
                        @if($match->is_extra_time)
                        <span class="shrink-0 text-[10px] text-slate-400 font-medium">
                            {{ $match->home_score_penalties !== null ? __('season.pens_abbr') : __('season.aet_abbr') }}
                        </span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Group Standings --}}
            @if($groupStandings->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-8 mb-6">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-5 text-center">{{ __('season.group_stage_standings') }}</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                    @foreach($groupStandings as $groupLabel => $standings)
                    <div>
                        <h3 class="text-xs font-semibold text-slate-500 uppercase mb-2">
                            {{ __('season.group_label', ['group' => $groupLabel]) }}
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-[10px] text-slate-400 uppercase">
                                        <th class="text-left py-1 pr-2 w-6"></th>
                                        <th class="text-left py-1"></th>
                                        <th class="text-center py-1 w-6">{{ __('season.played_abbr') }}</th>
                                        <th class="text-center py-1 w-6">{{ __('season.won') }}</th>
                                        <th class="text-center py-1 w-6">{{ __('season.drawn') }}</th>
                                        <th class="text-center py-1 w-6">{{ __('season.lost') }}</th>
                                        <th class="text-center py-1 w-8 hidden md:table-cell">{{ __('season.goals_for') }}</th>
                                        <th class="text-center py-1 w-8 hidden md:table-cell">{{ __('season.goals_against') }}</th>
                                        <th class="text-center py-1 w-8 font-bold">{{ __('season.pts_abbr') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($standings as $standing)
                                    <tr class="{{ $standing->team_id === $game->team_id ? 'bg-amber-50 font-semibold' : '' }} {{ $standing->position <= 2 ? 'border-l-2 border-l-emerald-400' : '' }}">
                                        <td class="py-1.5 pr-1 text-center text-xs text-slate-400">{{ $standing->position }}</td>
                                        <td class="py-1.5">
                                            <div class="flex items-center gap-1.5">
                                                <img src="{{ $standing->team->image }}" class="w-4 h-4 shrink-0">
                                                <span class="text-xs truncate">{{ $standing->team->name }}</span>
                                            </div>
                                        </td>
                                        <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->played }}</td>
                                        <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->won }}</td>
                                        <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->drawn }}</td>
                                        <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->lost }}</td>
                                        <td class="text-center py-1.5 text-xs text-slate-500 hidden md:table-cell">{{ $standing->goals_for }}</td>
                                        <td class="text-center py-1.5 text-xs text-slate-500 hidden md:table-cell">{{ $standing->goals_against }}</td>
                                        <td class="text-center py-1.5 text-xs font-bold text-slate-900">{{ $standing->points }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Knockout Bracket --}}
            @if($knockoutTies->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-8 mb-6">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-5 text-center">{{ __('game.knockout_phase') }}</h2>

                <div class="space-y-6">
                    @foreach($knockoutTies as $roundNumber => $ties)
                    @php
                        $roundName = $ties->first()->firstLegMatch->round_name ?? __('cup.round_n', ['round' => $roundNumber]);
                    @endphp
                    <div>
                        <h3 class="text-xs font-semibold text-slate-500 uppercase mb-3">{{ $roundName }}</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @foreach($ties as $tie)
                            @php
                                $match = $tie->firstLegMatch;
                                $homeScore = $match?->home_score ?? 0;
                                $awayScore = $match?->away_score ?? 0;
                                $involvesPlayer = $tie->home_team_id === $game->team_id || $tie->away_team_id === $game->team_id;
                                $isHomeWinner = $tie->winner_id === $tie->home_team_id;
                                $isAwayWinner = $tie->winner_id === $tie->away_team_id;
                            @endphp
                            <div class="border rounded-lg p-3 {{ $involvesPlayer ? 'border-amber-300 bg-amber-50/50' : 'border-slate-200' }}">
                                <div class="flex items-center justify-between gap-2">
                                    {{-- Home team --}}
                                    <div class="flex items-center gap-2 flex-1 min-w-0 {{ $isHomeWinner ? 'font-semibold' : '' }}">
                                        <img src="{{ $tie->homeTeam->image }}" class="w-5 h-5 shrink-0">
                                        <span class="text-sm truncate {{ $isHomeWinner ? 'text-slate-900' : 'text-slate-500' }}">{{ $tie->homeTeam->name }}</span>
                                    </div>

                                    {{-- Score --}}
                                    <div class="shrink-0 text-center">
                                        <span class="text-sm font-bold text-slate-900">{{ $homeScore }} - {{ $awayScore }}</span>
                                        @if($match?->is_extra_time)
                                        <div class="text-[10px] text-slate-400">
                                            @if($match->home_score_penalties !== null)
                                                {{ __('season.pens_abbr') }} {{ $match->home_score_penalties }}-{{ $match->away_score_penalties }}
                                            @else
                                                {{ __('season.aet_abbr') }}
                                            @endif
                                        </div>
                                        @endif
                                    </div>

                                    {{-- Away team --}}
                                    <div class="flex items-center gap-2 flex-1 min-w-0 justify-end {{ $isAwayWinner ? 'font-semibold' : '' }}">
                                        <span class="text-sm truncate text-right {{ $isAwayWinner ? 'text-slate-900' : 'text-slate-500' }}">{{ $tie->awayTeam->name }}</span>
                                        <img src="{{ $tie->awayTeam->image }}" class="w-5 h-5 shrink-0">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Tournament Awards --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-8 mb-6">
                <div class="text-center text-slate-400 font-semibold text-xs uppercase tracking-wide mb-6">
                    <span>&#9733;</span> {{ __('season.tournament_awards') }} <span>&#9733;</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Golden Boot --}}
                    <div class="bg-gradient-to-b from-amber-50 to-white rounded-lg border border-amber-200 p-5 text-center">
                        <div class="text-2xl mb-2">&#129351;</div>
                        <div class="text-xs text-amber-600 font-semibold uppercase tracking-wide mb-3">{{ __('season.golden_boot') }}</div>
                        @if($topScorers->isNotEmpty())
                            @php $scorer = $topScorers->first(); @endphp
                            <div class="flex items-center justify-center gap-2 mb-1">
                                <img src="{{ $scorer->team->image }}" class="w-5 h-5">
                                <span class="font-semibold text-sm text-slate-900">{{ $scorer->player->name }}</span>
                            </div>
                            <div class="text-3xl font-bold text-amber-600">{{ $scorer->goals }}</div>
                            <div class="text-xs text-slate-500">{{ __('season.goals') }}</div>
                        @else
                            <div class="text-slate-400 text-sm">{{ __('season.no_goals_scored') }}</div>
                        @endif
                    </div>

                    {{-- Golden Glove --}}
                    <div class="bg-gradient-to-b from-sky-50 to-white rounded-lg border border-sky-200 p-5 text-center">
                        <div class="text-2xl mb-2">&#129351;</div>
                        <div class="text-xs text-sky-600 font-semibold uppercase tracking-wide mb-3">{{ __('season.golden_glove') }}</div>
                        @if($bestGoalkeeper)
                            <div class="flex items-center justify-center gap-2 mb-1">
                                <img src="{{ $bestGoalkeeper->team->image }}" class="w-5 h-5">
                                <span class="font-semibold text-sm text-slate-900">{{ $bestGoalkeeper->player->name }}</span>
                            </div>
                            <div class="text-3xl font-bold text-sky-600">{{ $bestGoalkeeper->clean_sheets }}</div>
                            <div class="text-xs text-slate-500">{{ __('season.clean_sheets') }}</div>
                            <div class="text-xs text-slate-400 mt-1">
                                {{ number_format($bestGoalkeeper->goals_conceded / max(1, $bestGoalkeeper->appearances), 2) }} {{ __('season.goals_per_game') }}
                            </div>
                        @else
                            <div class="text-slate-400 text-sm">{{ __('season.not_enough_data') }}</div>
                        @endif
                    </div>

                    {{-- Most Assists --}}
                    <div class="bg-gradient-to-b from-emerald-50 to-white rounded-lg border border-emerald-200 p-5 text-center">
                        <div class="text-2xl mb-2">&#129351;</div>
                        <div class="text-xs text-emerald-600 font-semibold uppercase tracking-wide mb-3">{{ __('season.most_assists') }}</div>
                        @if($topAssisters->isNotEmpty())
                            @php $assister = $topAssisters->first(); @endphp
                            <div class="flex items-center justify-center gap-2 mb-1">
                                <img src="{{ $assister->team->image }}" class="w-5 h-5">
                                <span class="font-semibold text-sm text-slate-900">{{ $assister->player->name }}</span>
                            </div>
                            <div class="text-3xl font-bold text-emerald-600">{{ $assister->assists }}</div>
                            <div class="text-xs text-slate-500">{{ __('season.assists') }}</div>
                        @else
                            <div class="text-slate-400 text-sm">{{ __('season.no_assists_recorded') }}</div>
                        @endif
                    </div>
                </div>

                {{-- Top Scorers Leaderboard --}}
                @if($topScorers->count() > 1)
                <div class="mt-6 pt-5 border-t border-slate-100">
                    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">{{ __('season.top_scorers') }}</h3>
                    <div class="space-y-1.5">
                        @foreach($topScorers as $scorer)
                        <div class="flex items-center gap-3 py-1.5 {{ $scorer->team_id === $game->team_id ? 'bg-amber-50 -mx-2 px-2 rounded' : '' }}">
                            <span class="w-5 text-center text-xs font-bold {{ $loop->first ? 'text-amber-600' : 'text-slate-400' }}">{{ $loop->iteration }}</span>
                            <img src="{{ $scorer->team->image }}" class="w-4 h-4 shrink-0">
                            <span class="flex-1 text-sm text-slate-900 truncate">{{ $scorer->player->name }}</span>
                            <span class="text-sm font-bold text-slate-700">{{ $scorer->goals }}</span>
                            <span class="text-xs text-slate-400 w-10">{{ $scorer->assists }} ast</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Your Squad Performance --}}
            @if($yourGoalScorers->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 md:p-8 mb-6">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-5 text-center">{{ __('season.your_squad_performance') }}</h2>

                {{-- Goal Scorers --}}
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">{{ __('season.your_goal_scorers') }}</h3>
                <div class="space-y-1.5 mb-6">
                    @foreach($yourGoalScorers as $gp)
                    <div class="flex items-center gap-3 py-1.5 px-2 {{ $loop->even ? 'bg-slate-50' : '' }} rounded">
                        <x-position-badge :position="$gp->position" size="sm" />
                        <span class="flex-1 text-sm text-slate-900 truncate">{{ $gp->player->name }}</span>
                        <div class="flex items-center gap-3 text-sm">
                            <span class="font-bold text-slate-700">{{ $gp->goals }} <span class="text-xs text-slate-400 font-normal">{{ __('season.goals') }}</span></span>
                            @if($gp->assists > 0)
                            <span class="text-slate-500">{{ $gp->assists }} <span class="text-xs text-slate-400">{{ __('season.assists') }}</span></span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Appearances Table --}}
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">{{ __('season.squad_appearances') }}</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] text-slate-400 uppercase border-b border-slate-100">
                                <th class="text-left py-2"></th>
                                <th class="text-left py-2">{{ __('squad.squad') }}</th>
                                <th class="text-center py-2 w-10">{{ __('squad.appearances') }}</th>
                                <th class="text-center py-2 w-10">{{ __('squad.goals') }}</th>
                                <th class="text-center py-2 w-10">{{ __('squad.assists') }}</th>
                                <th class="text-center py-2 w-10 hidden md:table-cell">{{ __('squad.yellow_cards') }}</th>
                                <th class="text-center py-2 w-10 hidden md:table-cell">{{ __('squad.red_cards') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($yourAppearances as $gp)
                            <tr class="{{ $loop->even ? 'bg-slate-50' : '' }}">
                                <td class="py-1.5 pr-2"><x-position-badge :position="$gp->position" size="sm" /></td>
                                <td class="py-1.5 font-medium text-slate-900 truncate max-w-[140px]">{{ $gp->player->name }}</td>
                                <td class="text-center py-1.5 font-semibold text-slate-700">{{ $gp->appearances }}</td>
                                <td class="text-center py-1.5 {{ $gp->goals > 0 ? 'font-semibold text-slate-700' : 'text-slate-300' }}">{{ $gp->goals }}</td>
                                <td class="text-center py-1.5 {{ $gp->assists > 0 ? 'font-semibold text-slate-700' : 'text-slate-300' }}">{{ $gp->assists }}</td>
                                <td class="text-center py-1.5 hidden md:table-cell {{ $gp->yellow_cards > 0 ? 'text-amber-600 font-medium' : 'text-slate-300' }}">{{ $gp->yellow_cards }}</td>
                                <td class="text-center py-1.5 hidden md:table-cell {{ $gp->red_cards > 0 ? 'text-red-600 font-medium' : 'text-slate-300' }}">{{ $gp->red_cards }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Back to Dashboard --}}
            <div class="text-center pt-4 pb-8">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-slate-700 to-slate-600 hover:from-slate-800 hover:to-slate-700 text-white rounded-lg text-lg font-bold shadow-lg transition-all min-h-[44px]">
                    {{ __('season.back_to_dashboard') }}
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
