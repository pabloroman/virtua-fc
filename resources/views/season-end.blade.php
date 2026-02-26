@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var \Illuminate\Support\Collection $standings */
/** @var App\Models\GameStanding|null $playerStanding */
/** @var App\Models\GameStanding|null $champion */
/** @var array $standingsZones */

$isChampion = $champion && $champion->team_id === $game->team_id;

$borderColorMap = [
    'blue-500' => 'border-l-4 border-l-blue-500',
    'orange-500' => 'border-l-4 border-l-orange-500',
    'red-500' => 'border-l-4 border-l-red-500',
    'green-300' => 'border-l-4 border-l-green-300',
    'green-500' => 'border-l-4 border-l-green-500',
    'yellow-500' => 'border-l-4 border-l-yellow-500',
];

$bgColorMap = [
    'bg-blue-500' => 'bg-blue-500',
    'bg-orange-500' => 'bg-orange-500',
    'bg-red-500' => 'bg-red-500',
    'bg-green-300' => 'bg-green-300',
    'bg-green-500' => 'bg-green-500',
    'bg-yellow-500' => 'bg-yellow-500',
];

$getZoneClass = function($position) use ($standingsZones, $borderColorMap) {
    foreach ($standingsZones as $zone) {
        if ($position >= $zone['minPosition'] && $position <= $zone['maxPosition']) {
            return $borderColorMap[$zone['borderColor']] ?? '';
        }
    }
    return '';
};
@endphp

<x-app-layout :hide-footer="true">
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-10 space-y-8 md:space-y-12">

    {{-- Season header --}}
    <div class="text-center">
        <div class="text-slate-400 font-semibold text-xs uppercase tracking-widest">
            {{ __('season.season_honours', ['season' => $game->formatted_season]) }}
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 1: THE LEAGUE — Hero Standings + Awards Sidebar       --}}
    {{-- ============================================================ --}}

    {{-- Champion celebration (only if user won the league) --}}
    @if($isChampion)
    <div class="text-center py-6 md:py-8 bg-gradient-to-b from-amber-50 via-amber-50/60 to-transparent rounded-xl border border-amber-200/60">
        <x-team-crest :team="$game->team" class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-3" />
        <h2 class="text-2xl md:text-3xl font-black text-amber-700 tracking-tight">
            {{ __('season.champion_label') }}
        </h2>
        <div class="text-sm text-amber-600/80 mt-1">{{ $champion->points }} {{ __('season.points') }}</div>
    </div>
    @endif

    {{-- Standings + Awards grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">

        {{-- Standings table (2 columns on desktop) --}}
        <div class="md:col-span-2 space-y-4">
            <h3 class="text-xs text-slate-400 uppercase tracking-widest font-semibold">
                {{ __('season.final_standings') }}
            </h3>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full table-fixed text-right divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                        <tr class="text-xs text-slate-500 uppercase tracking-wide">
                            <th class="font-semibold text-left w-8 py-2.5 px-2"></th>
                            <th class="font-semibold text-left py-2.5 px-2"></th>
                            <th class="font-semibold w-8 py-2.5 px-2 hidden md:table-cell">{{ __('game.won_abbr') }}</th>
                            <th class="font-semibold w-8 py-2.5 px-2 hidden md:table-cell">{{ __('game.drawn_abbr') }}</th>
                            <th class="font-semibold w-8 py-2.5 px-2 hidden md:table-cell">{{ __('game.lost_abbr') }}</th>
                            <th class="font-semibold w-8 py-2.5 px-2 hidden md:table-cell">{{ __('game.goals_for_abbr') }}</th>
                            <th class="font-semibold w-8 py-2.5 px-2 hidden md:table-cell">{{ __('game.goals_against_abbr') }}</th>
                            <th class="font-semibold w-8 py-2.5 px-2">{{ __('game.goal_diff_abbr') }}</th>
                            <th class="font-semibold w-8 py-2.5 px-2">{{ __('game.pts_abbr') }}</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach($standings as $standing)
                            @php
                                $isPlayer = $standing->team_id === $game->team_id;
                                $isFirst = $standing->position === 1;
                                $zoneClass = $getZoneClass($standing->position);
                            @endphp
                            <tr class="text-sm {{ $zoneClass }} @if($isFirst && $isPlayer) bg-amber-100/80 @elseif($isFirst) bg-amber-50/50 @elseif($isPlayer) bg-amber-50 @endif">
                                <td class="whitespace-nowrap text-left px-2 py-1.5 text-slate-900 font-semibold">
                                    {{ $standing->position }}
                                </td>
                                <td class="whitespace-nowrap py-1.5 px-2">
                                    <div class="flex items-center space-x-2 @if($isPlayer) font-semibold @endif">
                                        <x-team-crest :team="$standing->team" class="w-5 h-5 shrink-0" />
                                        <span class="truncate">{{ $standing->team->name }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap py-1.5 px-2 text-slate-400 hidden md:table-cell">{{ $standing->won }}</td>
                                <td class="whitespace-nowrap py-1.5 px-2 text-slate-400 hidden md:table-cell">{{ $standing->drawn }}</td>
                                <td class="whitespace-nowrap py-1.5 px-2 text-slate-400 hidden md:table-cell">{{ $standing->lost }}</td>
                                <td class="whitespace-nowrap py-1.5 px-2 text-slate-400 hidden md:table-cell">{{ $standing->goals_for }}</td>
                                <td class="whitespace-nowrap py-1.5 px-2 text-slate-400 hidden md:table-cell">{{ $standing->goals_against }}</td>
                                <td class="whitespace-nowrap py-1.5 px-2 text-slate-400">{{ $standing->goal_difference }}</td>
                                <td class="whitespace-nowrap py-1.5 px-2 font-semibold">{{ $standing->points }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Zone legend --}}
                @if(count($standingsZones) > 0)
                <div class="flex flex-wrap gap-x-5 gap-y-1 px-3 py-2.5 bg-slate-50 border-t border-slate-200 text-[11px] text-slate-500">
                    @foreach($standingsZones as $zone)
                        <div class="flex items-center gap-1.5">
                            <div class="w-2.5 h-2.5 {{ $bgColorMap[$zone['bgColor']] ?? '' }} rounded-sm"></div>
                            <span>{{ __($zone['label']) }}</span>
                        </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Manager evaluation --}}
            @php
                $gradeColors = [
                    'exceptional' => 'bg-green-50 border-green-300 text-green-800',
                    'exceeded' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                    'met' => 'bg-slate-50 border-slate-300 text-slate-700',
                    'below' => 'bg-amber-50 border-amber-200 text-amber-800',
                    'disaster' => 'bg-red-50 border-red-200 text-red-800',
                ];
                $gradeClass = $gradeColors[$managerEvaluation['grade']] ?? $gradeColors['met'];
            @endphp
            <div class="rounded-xl border p-4 {{ $gradeClass }}">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 mb-2">
                    <div class="font-bold text-base">{{ $managerEvaluation['title'] }}</div>
                    <div class="text-xs opacity-80">
                        {{ __('season.target') }}: {{ $managerEvaluation['goalLabel'] }}
                        &rarr;
                        {{ __('season.actual') }}: {{ __('season.place', ['position' => $managerEvaluation['actualPosition']]) }}
                    </div>
                </div>
                <p class="text-sm opacity-90 leading-relaxed">{{ $managerEvaluation['message'] }}</p>
            </div>
        </div>

        {{-- Awards sidebar (right column on desktop, below on mobile) --}}
        <div class="space-y-4">
            <h3 class="text-xs text-slate-400 uppercase tracking-widest font-semibold">
                {{ __('season.individual_awards') }}
            </h3>

            {{-- Pichichi — Top Scorer (top 3) --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-4 py-2.5 bg-amber-50/60 border-b border-amber-100">
                    <div class="text-xs font-semibold text-amber-700 uppercase tracking-wide">
                        {{ __($competition->getConfig()->getTopScorerAwardName()) }}
                    </div>
                </div>
                <div class="p-3">
                    @if($topScorers->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($topScorers as $index => $scorer)
                            @php $isPlayerTeam = $scorer->team_id === $game->team_id; @endphp
                            <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-sky-50 -mx-1 px-1 py-0.5 rounded @endif">
                                <span class="w-5 text-center text-xs font-bold {{ $index === 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ $index + 1 }}</span>
                                <x-team-crest :team="$scorer->team" class="w-4 h-4 shrink-0" />
                                <span class="flex-1 truncate @if($isPlayerTeam) font-medium @endif">{{ $scorer->player->name }}</span>
                                <span class="font-bold tabular-nums text-slate-900">{{ $scorer->goals }}</span>
                            </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-sm text-slate-400 text-center py-2">{{ __('season.no_goals_scored') }}</p>
                    @endif
                </div>
            </div>

            {{-- Zamora — Best Goalkeeper --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-4 py-2.5 bg-sky-50/60 border-b border-sky-100">
                    <div class="text-xs font-semibold text-sky-700 uppercase tracking-wide">
                        {{ __($competition->getConfig()->getBestGoalkeeperAwardName()) }}
                    </div>
                </div>
                <div class="p-3">
                    @if($bestGoalkeeper)
                        @php $isPlayerTeam = $bestGoalkeeper->team_id === $game->team_id; @endphp
                        <div class="@if($isPlayerTeam) bg-sky-50 rounded p-2 -m-1 @endif">
                            <div class="flex items-center gap-2 mb-1.5">
                                <x-team-crest :team="$bestGoalkeeper->team" class="w-5 h-5 shrink-0" />
                                <span class="font-semibold text-sm text-slate-900 truncate">{{ $bestGoalkeeper->player->name }}</span>
                            </div>
                            <div class="flex items-baseline gap-3 text-xs text-slate-500">
                                <span><span class="font-bold text-slate-900 text-base tabular-nums">{{ $bestGoalkeeper->clean_sheets }}</span> {{ __('season.clean_sheets') }}</span>
                                <span>{{ number_format($bestGoalkeeper->goals_conceded / max(1, $bestGoalkeeper->appearances), 2) }} {{ __('season.goals_per_game') }}</span>
                            </div>
                        </div>
                    @else
                    <p class="text-sm text-slate-400 text-center py-2">{{ __('season.not_enough_data') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 2: YOUR OTHER COMPETITIONS                            --}}
    {{-- ============================================================ --}}

    @if(count($otherCompetitionResults) > 0)
    <div class="space-y-3">
        <h3 class="text-xs text-slate-400 uppercase tracking-widest font-semibold">
            {{ __('season.your_other_competitions') }}
        </h3>

        <div class="space-y-2">
            @foreach($otherCompetitionResults as $result)
                @php $comp = $result['competition']; @endphp
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2
                    @if($result['wonCompetition']) ring-1 ring-amber-300 bg-amber-50/30 @endif">

                    {{-- Left: competition name --}}
                    <div class="flex items-center gap-2.5">
                        @if($result['wonCompetition'])
                            <div class="w-7 h-7 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 text-sm shrink-0">&#9733;</div>
                        @else
                            <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 text-xs shrink-0">&#9917;</div>
                        @endif
                        <div>
                            <div class="font-semibold text-sm text-slate-900">{{ __($comp->name) }}</div>
                            @if($result['wonCompetition'])
                                <div class="text-xs font-semibold text-amber-600">{{ __('season.champion_label') }}</div>
                            @elseif($result['roundName'])
                                <div class="text-xs text-slate-500">
                                    @if($result['eliminated'])
                                        {{ __('season.eliminated_in', ['round' => $result['roundName']]) }}
                                        @if($result['opponent'])
                                            {{ $result['opponent']->name }}
                                        @endif
                                    @else
                                        {{ __('season.reached_round', ['round' => $result['roundName']]) }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Right: score + swiss info --}}
                    <div class="flex items-center gap-3 text-sm">
                        @if($result['swissStanding'])
                            <span class="text-xs text-slate-500 bg-slate-100 rounded px-2 py-0.5">
                                {{ __('season.swiss_position', ['position' => $result['swissStanding']->position]) }}
                            </span>
                        @endif
                        @if($result['score'] && $result['opponent'])
                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                <x-team-crest :team="$result['opponent']" class="w-4 h-4 shrink-0" />
                                <span class="font-mono tabular-nums font-semibold text-slate-700">{{ $result['score'] }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- SECTION 3: YOUR TEAM IN NUMBERS                               --}}
    {{-- ============================================================ --}}

    <div class="space-y-3">
        <h3 class="text-xs text-slate-400 uppercase tracking-widest font-semibold">
            {{ __('season.team_in_numbers') }}
        </h3>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 md:gap-3">

            {{-- Top scorer --}}
            @if($teamTopScorer && $teamTopScorer->goals > 0)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.your_top_scorer') }}</div>
                <div class="flex items-center gap-1.5 mb-1">
                    <x-team-crest :team="$teamTopScorer->team" class="w-4 h-4 shrink-0" />
                    <span class="text-sm font-medium text-slate-900 truncate">{{ $teamTopScorer->player->name }}</span>
                </div>
                <div class="text-xl font-bold text-slate-900 tabular-nums">{{ $teamTopScorer->goals }}</div>
                <div class="text-[10px] text-slate-400">{{ __('season.goals') }}</div>
            </div>
            @endif

            {{-- Top assister --}}
            @if($teamTopAssister && $teamTopAssister->assists > 0)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.your_top_assister') }}</div>
                <div class="flex items-center gap-1.5 mb-1">
                    <x-team-crest :team="$teamTopAssister->team" class="w-4 h-4 shrink-0" />
                    <span class="text-sm font-medium text-slate-900 truncate">{{ $teamTopAssister->player->name }}</span>
                </div>
                <div class="text-xl font-bold text-slate-900 tabular-nums">{{ $teamTopAssister->assists }}</div>
                <div class="text-[10px] text-slate-400">{{ __('season.assists') }}</div>
            </div>
            @endif

            {{-- Most appearances --}}
            @if($teamMostAppearances && $teamMostAppearances->appearances > 0)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.most_appearances') }}</div>
                <div class="flex items-center gap-1.5 mb-1">
                    <x-team-crest :team="$teamMostAppearances->team" class="w-4 h-4 shrink-0" />
                    <span class="text-sm font-medium text-slate-900 truncate">{{ $teamMostAppearances->player->name }}</span>
                </div>
                <div class="text-xl font-bold text-slate-900 tabular-nums">{{ $teamMostAppearances->appearances }}</div>
                <div class="text-[10px] text-slate-400">{{ __('season.appearances') }}</div>
            </div>
            @endif

            {{-- Biggest victory --}}
            @if($biggestVictory)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.biggest_victory') }}</div>
                <div class="flex items-center gap-1.5 mb-1">
                    <x-team-crest :team="$biggestVictory['opponent']" class="w-4 h-4 shrink-0" />
                    <span class="text-sm text-slate-600 truncate">{{ __('season.vs') }} {{ $biggestVictory['opponent']->name }}</span>
                </div>
                <div class="text-xl font-bold text-green-600 tabular-nums">{{ $biggestVictory['score'] }}</div>
            </div>
            @endif

            {{-- Worst defeat --}}
            @if($worstDefeat)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.worst_defeat') }}</div>
                <div class="flex items-center gap-1.5 mb-1">
                    <x-team-crest :team="$worstDefeat['opponent']" class="w-4 h-4 shrink-0" />
                    <span class="text-sm text-slate-600 truncate">{{ __('season.vs') }} {{ $worstDefeat['opponent']->name }}</span>
                </div>
                <div class="text-xl font-bold text-red-600 tabular-nums">{{ $worstDefeat['score'] }}</div>
            </div>
            @else
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.worst_defeat') }}</div>
                <div class="text-xl font-bold text-green-600 mt-2">{{ __('season.no_defeats') }}</div>
            </div>
            @endif

            {{-- Home record --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.home_record') }}</div>
                <div class="flex items-baseline gap-1 mt-2">
                    <span class="text-xl font-bold text-green-600 tabular-nums">{{ $homeRecord['w'] }}</span>
                    <span class="text-xs text-slate-400">{{ __('season.won') }}</span>
                    <span class="text-xl font-bold text-slate-400 tabular-nums ml-1">{{ $homeRecord['d'] }}</span>
                    <span class="text-xs text-slate-400">{{ __('season.drawn') }}</span>
                    <span class="text-xl font-bold text-red-500 tabular-nums ml-1">{{ $homeRecord['l'] }}</span>
                    <span class="text-xs text-slate-400">{{ __('season.lost') }}</span>
                </div>
            </div>

            {{-- Away record --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.away_record') }}</div>
                <div class="flex items-baseline gap-1 mt-2">
                    <span class="text-xl font-bold text-green-600 tabular-nums">{{ $awayRecord['w'] }}</span>
                    <span class="text-xs text-slate-400">{{ __('season.won') }}</span>
                    <span class="text-xl font-bold text-slate-400 tabular-nums ml-1">{{ $awayRecord['d'] }}</span>
                    <span class="text-xs text-slate-400">{{ __('season.drawn') }}</span>
                    <span class="text-xl font-bold text-red-500 tabular-nums ml-1">{{ $awayRecord['l'] }}</span>
                    <span class="text-xs text-slate-400">{{ __('season.lost') }}</span>
                </div>
            </div>

            {{-- Clean sheets --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.total_clean_sheets') }}</div>
                <div class="text-xl font-bold text-slate-900 tabular-nums mt-2">{{ $teamCleanSheets }}</div>
            </div>

            {{-- Cards --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.cards') }}</div>
                <div class="flex items-baseline gap-2 mt-2">
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-4 rounded-sm bg-yellow-400"></div>
                        <span class="text-xl font-bold text-slate-900 tabular-nums">{{ $teamYellowCards }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="w-3 h-4 rounded-sm bg-red-500"></div>
                        <span class="text-xl font-bold text-slate-900 tabular-nums">{{ $teamRedCards }}</span>
                    </div>
                </div>
            </div>

            {{-- Transfer balance --}}
            @if($transferBalance !== 0)
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.transfer_balance') }}</div>
                <div class="text-xl font-bold tabular-nums mt-2 {{ $transferBalance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ \App\Support\Money::format($transferBalance) }}
                </div>
            </div>
            @endif

            {{-- Retiring players --}}
            @if($userTeamRetiring->isNotEmpty())
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-3">
                <div class="text-[10px] text-slate-400 uppercase tracking-wide font-semibold mb-1.5">{{ __('season.retiring_label') }}</div>
                <div class="text-xl font-bold text-orange-600 tabular-nums">
                    {{ $userTeamRetiring->count() === 1 ? __('season.player_retiring_singular') : __('season.players_retiring', ['count' => $userTeamRetiring->count()]) }}
                </div>
                <div class="mt-1.5 space-y-0.5">
                    @foreach($userTeamRetiring as $retiring)
                        <div class="text-xs text-slate-600 truncate">{{ $retiring->player->name }}</div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- SECTION 4: OTHER LEAGUE RESULTS (SIMULATED)                   --}}
    {{-- ============================================================ --}}

    @if(count($simulatedResults) > 0)
    <div class="space-y-2">
        <h3 class="text-xs text-slate-400 uppercase tracking-widest font-semibold">
            {{ __('season.simulated_results') }}
        </h3>

        <div class="bg-slate-50 rounded-xl border border-slate-200 px-4 py-3">
            <div class="space-y-2">
                @foreach($simulatedResults as $simResult)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-slate-500 flex-1 truncate">{{ __('season.league_champion', ['league' => __($simResult['competition']->name)]) }}</span>
                        @if($simResult['champion'])
                            <x-team-crest :team="$simResult['champion']" class="w-5 h-5 shrink-0" />
                            <span class="font-semibold text-slate-900">{{ $simResult['champion']->name }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- CTA: Start New Season                                         --}}
    {{-- ============================================================ --}}

    <div class="text-center pt-4 pb-8">
        @if(config('beta.allow_new_season'))
        <form method="post" action="{{ route('game.start-new-season', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-700 hover:to-red-600 text-white px-8 py-4 rounded-xl text-xl font-bold shadow-lg transition-all transform hover:scale-105 min-h-[44px]"
                    :disabled="loading">
                <span x-show="!loading">{{ __('season.start_new_season', ['season' => \App\Models\Game::formatSeason((string)((int)$game->season + 1))]) }}</span>
                <span x-show="loading" x-cloak>
                    <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
            </button>
        </form>
        @else
        <p class="text-slate-500 text-lg">{{ __('season.new_season_coming_soon') }}</p>
        @endif
    </div>

</div>
</x-app-layout>
