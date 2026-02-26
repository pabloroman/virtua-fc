@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var \Illuminate\Support\Collection $groupStandings */
/** @var \Illuminate\Support\Collection $knockoutTies */
/** @var string|null $championTeamId */
/** @var App\Models\GameMatch|null $finalMatch */
/** @var \Illuminate\Support\Collection $finalGoalEvents */
/** @var App\Models\Team|null $championTeam */
/** @var App\Models\Team|null $finalistTeam */
/** @var string $resultLabel */
/** @var \Illuminate\Support\Collection $yourMatches */
/** @var App\Models\GameStanding|null $playerStanding */
/** @var array $yourRecord */
/** @var \Illuminate\Support\Collection $topScorers */
/** @var \Illuminate\Support\Collection $topAssisters */
/** @var \Illuminate\Support\Collection $topGoalkeepers */
/** @var \Illuminate\Support\Collection $yourSquadStats */
/** @var array $squadHighlights */
/** @var App\Models\TournamentChallenge|null $existingChallenge */

$isChampion = $championTeamId === $game->team_id;
$yourGoalScorers = $yourSquadStats->where('goals', '>', 0)->sortByDesc('goals');
$yourAppearances = $yourSquadStats->where('appearances', '>', 0)->sortByDesc('appearances');

// Result badge colors
$resultBadgeClass = match($resultLabel) {
    'champion'          => 'bg-amber-100 text-amber-800 border-amber-300',
    'runner_up'         => 'bg-slate-200 text-slate-700 border-slate-400',
    'third_place'       => 'bg-orange-100 text-orange-800 border-orange-300',
    'semi_finalist'     => 'bg-blue-100 text-blue-700 border-blue-300',
    'quarter_finalist'  => 'bg-blue-50 text-blue-600 border-blue-200',
    default             => 'bg-slate-100 text-slate-600 border-slate-200',
};

// Group final goal events by team, then by player
$homeGoals = collect();
$awayGoals = collect();
if ($finalMatch && $finalGoalEvents->isNotEmpty()) {
    foreach ($finalGoalEvents as $event) {
        $playerName = $event->gamePlayer?->player?->name ?? '?';
        $isOwnGoal = $event->event_type === \App\Models\MatchEvent::TYPE_OWN_GOAL;

        // For own goals, the scoring team is the OPPOSITE of the event's team
        $scoringTeamId = $isOwnGoal
            ? ($event->team_id === $finalMatch->home_team_id ? $finalMatch->away_team_id : $finalMatch->home_team_id)
            : $event->team_id;

        $entry = [
            'player' => $playerName,
            'minute' => $event->minute,
            'own_goal' => $isOwnGoal,
        ];

        if ($scoringTeamId === $finalMatch->home_team_id) {
            $homeGoals->push($entry);
        } else {
            $awayGoals->push($entry);
        }
    }
}

// Helper to group goals by player and format
$formatGoalGroup = function ($goals) {
    return $goals->groupBy('player')->map(function ($playerGoals, $playerName) {
        $minutes = $playerGoals->pluck('minute')->sort()->map(fn ($m) => $m . "'");
        $hasOwnGoal = $playerGoals->contains('own_goal', true);
        return $playerName . ' ' . $minutes->join(', ') . ($hasOwnGoal ? ' (OG)' : '');
    })->values();
};

$homeGoalLines = $formatGoalGroup($homeGoals);
$awayGoalLines = $formatGoalGroup($awayGoals);
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen bg-gradient-to-b from-slate-100 to-slate-50">

        {{-- ============================================ --}}
        {{-- SECTION 1: Hero Header + Final Scoreboard    --}}
        {{-- ============================================ --}}
        <div class="relative overflow-hidden {{ $isChampion ? 'bg-gradient-to-b from-amber-600 via-amber-500 to-amber-400' : 'bg-gradient-to-b from-slate-800 via-slate-700 to-slate-600' }} py-10 md:py-16 pb-16 md:pb-24">
            {{-- Decorative elements --}}
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-20 -left-20 w-60 h-60 bg-white/5 rounded-full"></div>
                <div class="absolute -bottom-10 -right-10 w-80 h-80 bg-white/5 rounded-full"></div>
                @if($isChampion)
                <div class="absolute top-8 left-1/4 text-amber-300/30 text-4xl">&#9733;</div>
                <div class="absolute top-16 right-1/4 text-amber-300/30 text-3xl">&#9733;</div>
                <div class="absolute bottom-12 left-1/3 text-amber-300/30 text-2xl">&#9733;</div>
                <div class="absolute top-24 right-1/3 text-amber-300/20 text-2xl">&#9733;</div>
                @endif
            </div>

            <div class="relative max-w-4xl mx-auto px-4 text-center">
                {{-- Trophy --}}
                <div class="text-6xl md:text-8xl mb-3">&#127942;</div>

                {{-- Champion announcement --}}
                @if($championTeam)
                <h1 class="text-2xl md:text-4xl font-extrabold text-white mb-1 tracking-tight">
                    {{ __('season.tournament_champion') }}
                </h1>

                {{-- Champion team badge --}}
                <div class="inline-flex flex-col items-center mb-8">
                    <x-team-crest :team="$championTeam"
                         class="w-20 h-20 md:w-28 md:h-28 drop-shadow-lg" />
                    <div class="mt-2 text-xl md:text-2xl font-bold text-white">{{ $championTeam->name }}</div>
                </div>
                @else
                <h1 class="text-2xl md:text-4xl font-extrabold text-white mb-1 tracking-tight">
                    {{ __('season.tournament_complete') }}
                </h1>
                <p class="text-sm md:text-base text-slate-300 font-medium mb-8">
                    {{ __($competition->name ?? 'game.wc2026_name') }}
                </p>
                @endif

                {{-- Final Scoreboard Card --}}
                @if($finalMatch && $championTeam && $finalistTeam)
                @php
                    $homeTeam = $finalMatch->homeTeam;
                    $awayTeam = $finalMatch->awayTeam;
                    $homeIsWinner = $championTeamId === $homeTeam->id;
                    $awayIsWinner = $championTeamId === $awayTeam->id;
                @endphp
                <div class="bg-slate-900/60 backdrop-blur-sm rounded-xl p-4 md:p-6 max-w-lg mx-auto">
                    <div class="text-[10px] md:text-xs uppercase tracking-widest {{ $isChampion ? 'text-amber-300/70' : 'text-white/40' }} font-semibold mb-3">
                        {{ __('season.the_final') }}
                    </div>

                    {{-- Teams + Score --}}
                    <div class="flex items-center justify-between gap-2 md:gap-4">
                        {{-- Home team --}}
                        <div class="flex-1 min-w-0 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <span class="text-sm md:text-base font-semibold truncate {{ $homeIsWinner ? 'text-white' : 'text-white/60' }}">
                                    {{ $homeTeam->name }}
                                </span>
                                <x-team-crest :team="$homeTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                            </div>
                        </div>

                        {{-- Score --}}
                        <div class="shrink-0 text-center px-2 md:px-4">
                            <div class="text-2xl md:text-3xl font-bold text-white">
                                {{ $finalMatch->home_score }} - {{ $finalMatch->away_score }}
                            </div>
                            @if($finalMatch->is_extra_time)
                            <div class="text-[10px] text-white/50 mt-0.5">
                                @if($finalMatch->home_score_penalties !== null)
                                    {{ __('season.aet_abbr') }} &middot; {{ __('season.pens_abbr') }} {{ $finalMatch->home_score_penalties }}-{{ $finalMatch->away_score_penalties }}
                                @else
                                    {{ __('season.aet_abbr') }}
                                @endif
                            </div>
                            @endif
                        </div>

                        {{-- Away team --}}
                        <div class="flex-1 min-w-0 text-left">
                            <div class="flex items-center gap-2">
                                <x-team-crest :team="$awayTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                                <span class="text-sm md:text-base font-semibold truncate {{ $awayIsWinner ? 'text-white' : 'text-white/60' }}">
                                    {{ $awayTeam->name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Goal scorers --}}
                    @if($homeGoalLines->isNotEmpty() || $awayGoalLines->isNotEmpty())
                    <div class="flex justify-between gap-4 mt-3 pt-3 border-t border-white/10">
                        <div class="flex-1 text-right space-y-0.5">
                            @foreach($homeGoalLines as $line)
                            <div class="text-[10px] md:text-xs text-white/60">{{ $line }}</div>
                            @endforeach
                        </div>
                        <div class="shrink-0 w-px bg-white/10"></div>
                        <div class="flex-1 text-left space-y-0.5">
                            @foreach($awayGoalLines as $line)
                            <div class="text-[10px] md:text-xs text-white/60">{{ $line }}</div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 md:-mt-12 relative z-10 pb-12">

            {{-- ============================================ --}}
            {{-- SECTION 2: Expandable Full Tournament Results --}}
            {{-- ============================================ --}}
            @if($groupStandings->isNotEmpty() || $knockoutTies->isNotEmpty())
            <div class="mb-6" x-data="{ showResults: false, tab: 'groups' }">
                <button
                    @click="showResults = !showResults"
                    class="w-full bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex items-center justify-between gap-3 hover:bg-slate-50 transition-colors min-h-[44px]"
                >
                    <span class="text-sm font-semibold text-slate-600">{{ __('season.full_tournament_results') }}</span>
                    <svg class="w-5 h-5 text-slate-400 transition-transform duration-200" :class="showResults && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="showResults" x-collapse class="mt-2">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        {{-- Tabs --}}
                        <div class="flex border-b border-slate-200">
                            @if($groupStandings->isNotEmpty())
                            <button
                                @click="tab = 'groups'"
                                class="flex-1 py-3 text-sm font-medium text-center transition-colors min-h-[44px]"
                                :class="tab === 'groups' ? 'text-slate-900 border-b-2 border-slate-600' : 'text-slate-400 hover:text-slate-600'"
                            >
                                {{ __('season.group_stage_standings') }}
                            </button>
                            @endif
                            @if($knockoutTies->isNotEmpty())
                            <button
                                @click="tab = 'knockout'"
                                class="flex-1 py-3 text-sm font-medium text-center transition-colors min-h-[44px]"
                                :class="tab === 'knockout' ? 'text-slate-900 border-b-2 border-slate-600' : 'text-slate-400 hover:text-slate-600'"
                            >
                                {{ __('game.knockout_phase') }}
                            </button>
                            @endif
                        </div>

                        {{-- Groups Tab --}}
                        @if($groupStandings->isNotEmpty())
                        <div x-show="tab === 'groups'" class="p-4 md:p-6">
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
                                                            <x-team-crest :team="$standing->team" class="w-4 h-4 shrink-0" />
                                                            <span class="text-xs truncate">{{ $standing->team->name }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->played }}</td>
                                                    <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->won }}</td>
                                                    <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->drawn }}</td>
                                                    <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->lost }}</td>
                                                    <td class="text-center py-1.5 text-xs text-slate-500 hidden md:table-cell">{{ $standing->goals_for }}</td>
                                                    <td class="text-center py-1.5 text-xs text-slate-500 hidden md:table-cell">{{ $standing->goals_against }}</td>
                                                    <td class="text-center py-1.5 text-xs font-semibold text-slate-900">{{ $standing->points }}</td>
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

                        {{-- Knockout Tab --}}
                        @if($knockoutTies->isNotEmpty())
                        <div x-show="tab === 'knockout'" class="p-4 md:p-6">
                            <div class="space-y-6">
                                @foreach($knockoutTies->sortKeysDesc() as $roundNumber => $ties)
                                @php
                                    $roundName = $ties->first()->firstLegMatch->round_name ? __($ties->first()->firstLegMatch->round_name) : __('cup.round_n', ['round' => $roundNumber]);
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
                                                <div class="flex items-center gap-2 flex-1 min-w-0 {{ $isHomeWinner ? 'font-semibold' : '' }}">
                                                    <x-team-crest :team="$tie->homeTeam" class="w-5 h-5 shrink-0" />
                                                    <span class="text-sm truncate {{ $isHomeWinner ? 'text-slate-900' : 'text-slate-500' }}">{{ $tie->homeTeam->name }}</span>
                                                </div>
                                                <div class="shrink-0 text-center">
                                                    <span class="text-sm font-semibold text-slate-900">{{ $homeScore }} - {{ $awayScore }}</span>
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
                                                <div class="flex items-center gap-2 flex-1 min-w-0 justify-end {{ $isAwayWinner ? 'font-semibold' : '' }}">
                                                    <span class="text-sm truncate text-right {{ $isAwayWinner ? 'text-slate-900' : 'text-slate-500' }}">{{ $tie->awayTeam->name }}</span>
                                                    <x-team-crest :team="$tie->awayTeam" class="w-5 h-5 shrink-0" />
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
                    </div>
                </div>
            </div>
            @endif

            {{-- ============================================ --}}
            {{-- SECTION 3: Two-Column Main Content           --}}
            {{-- ============================================ --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                {{-- LEFT COLUMN (desktop) / FIRST (mobile): Your Performance --}}
                <div class="col-span-2">

                    {{-- Result Badge Card --}}
                    <div class="space-y-6 bg-white rounded-xl shadow-lg border border-slate-200 p-5 md:p-6">

                        {{-- Badge + Team --}}
                        <div class="flex items-center gap-3">
                            <x-team-crest :team="$game->team" class="w-12 h-12 md:w-14 md:h-14 shrink-0" />
                            <div class="min-w-0 md:w-full md:min-w-max md:flex md:justify-between">
                                <div class="text-lg md:text-xl font-bold text-slate-900 truncate">{{ $game->team->name }}</div>
                                <span class="inline-block mt-1 px-3 py-0.5 text-xs font-bold uppercase tracking-wide rounded-full border {{ $resultBadgeClass }}">
                                    {{ __('season.result_' . $resultLabel) }}
                                </span>
                            </div>
                        </div>

                        {{-- Quick Stats Row --}}
                        <div class="grid grid-cols-7 gap-1 text-center bg-slate-50 rounded-lg p-3">
                            <div>
                                <div class="text-lg md:text-xl font-bold text-slate-900">{{ $yourRecord['played'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.played_abbr') }}</div>
                            </div>
                            <div>
                                <div class="text-lg md:text-xl font-bold text-green-600">{{ $yourRecord['won'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.won') }}</div>
                            </div>
                            <div>
                                <div class="text-lg md:text-xl font-bold text-slate-400">{{ $yourRecord['drawn'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.drawn') }}</div>
                            </div>
                            <div>
                                <div class="text-lg md:text-xl font-bold text-red-500">{{ $yourRecord['lost'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.lost') }}</div>
                            </div>
                            <div>
                                <div class="text-lg md:text-xl font-bold text-slate-900">{{ $yourRecord['goalsFor'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.goals_for') }}</div>
                            </div>
                            <div>
                                <div class="text-lg md:text-xl font-bold text-slate-900">{{ $yourRecord['goalsAgainst'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.goals_against') }}</div>
                            </div>
                            <div>
                                <div class="text-lg md:text-xl font-bold {{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] >= 0 ? 'text-green-600' : 'text-red-500' }}">
                                    {{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] >= 0 ? '+' : '' }}{{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] }}
                                </div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.goal_diff_abbr') }}</div>
                            </div>
                        </div>

                        {{-- Match Journey Card --}}
                        <div class="space-y-1.5">
                            <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4">{{ __('season.your_journey') }}</h2>

                        @foreach($yourMatches as $match)
                            @php
                                $isHome = $match->home_team_id === $game->team_id;
                                $opponent = $isHome ? $match->awayTeam : $match->homeTeam;
                                $scored = $isHome ? $match->home_score : $match->away_score;
                                $conceded = $isHome ? $match->away_score : $match->home_score;
                                $resultClass = $scored > $conceded ? 'bg-green-500' : ($scored < $conceded ? 'bg-red-500' : 'bg-slate-400');
                                $resultLetter = $scored > $conceded ? 'W' : ($scored < $conceded ? 'L' : 'D');
                            @endphp
                            <div class="flex items-center gap-2.5 py-2 px-2.5 rounded-lg {{ $loop->even ? 'bg-slate-50' : '' }}">
                                <span class="shrink-0 w-6 h-6 rounded text-[10px] font-bold flex items-center justify-center text-white {{ $resultClass }}">
                                    {{ $resultLetter }}
                                </span>

                                <span class="hidden md:inline text-[10px] text-slate-400 w-14 shrink-0 truncate">
                                    {{ $match->round_name ? __($match->round_name) : __('game.matchday_n', ['number' => $match->round_number]) }}
                                </span>

                                <div class="flex items-center gap-1.5 flex-1 min-w-0">
                                    <x-team-crest :team="$opponent" class="w-4 h-4 shrink-0" />
                                    <span class="text-sm font-medium text-slate-900 truncate">
                                        {{ $opponent->name }}
                                    </span>
                                </div>

                                <div class="shrink-0 text-sm font-semibold text-slate-900">
                                    {{ $scored }}-{{ $conceded }}
                                </div>

                                @if($match->is_extra_time)
                                <span class="shrink-0 text-[10px] text-slate-400 font-medium">
                                    {{ $match->home_score_penalties !== null ? __('season.pens_abbr') : __('season.aet_abbr') }}
                                </span>
                                @endif
                            </div>
                            @endforeach
                        </div>

                    {{-- Expandable Squad Stats --}}
                    @if($yourAppearances->isNotEmpty())
                        <div class="">

                            {{-- Appearances Table --}}
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('season.your_squad_stats') }}</span>                                <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-[10px] text-slate-400 uppercase border-b border-slate-100">
                                            <th class="text-left py-2"></th>
                                            <th class="text-left py-2"></th>
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
                    </div>
                    @endif
                </div>

                {{-- RIGHT COLUMN (desktop) / SECOND (mobile): Tournament Awards --}}
                <div class="space-y-6 col-span-1">

                    {{-- Golden Boot Card --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-amber-50 to-amber-100/50 px-5 py-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">&#129351;</span>
                                <span class="text-xs text-amber-700 font-semibold uppercase tracking-wide">{{ __('season.golden_boot') }}</span>
                            </div>
                            @if($topScorers->isNotEmpty())
                            @php $scorer = $topScorers->first(); @endphp
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-team-crest :team="$scorer->team" class="w-6 h-6 shrink-0" />
                                    <span class="font-bold text-slate-900 truncate">{{ $scorer->player->name }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="text-2xl md:text-3xl font-bold text-amber-600">{{ $scorer->goals }}</span>
                                    <span class="text-xs text-amber-600/70 ml-0.5">{{ __('season.goals') }}</span>
                                </div>
                            </div>
                            @else
                            <div class="text-slate-400 text-sm">{{ __('season.no_goals_scored') }}</div>
                            @endif
                        </div>
                        @if($topScorers->count() > 1)
                        <div class="px-5 py-3 space-y-1.5">
                            @foreach($topScorers->skip(1) as $scorer)
                            <div class="flex items-center gap-2.5 {{ $scorer->team_id === $game->team_id ? 'bg-amber-50 -mx-2 px-2 rounded' : '' }}">
                                <span class="w-5 text-center text-xs font-bold text-slate-400">{{ $loop->iteration + 1 }}</span>
                                <x-team-crest :team="$scorer->team" class="w-4 h-4 shrink-0" />
                                <span class="flex-1 text-sm text-slate-700 truncate">{{ $scorer->player->name }}</span>
                                <span class="text-xs text-slate-400 w-10 text-right">{{ $scorer->goals }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Golden Glove Card --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-sky-50 to-sky-100/50 px-5 py-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">&#129351;</span>
                                <span class="text-xs text-sky-700 font-semibold uppercase tracking-wide">{{ __('season.golden_glove') }}</span>
                            </div>
                            @if($topGoalkeepers->isNotEmpty())
                            @php $gk = $topGoalkeepers->first(); @endphp
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-team-crest :team="$gk->team" class="w-6 h-6 shrink-0" />
                                    <span class="font-bold text-slate-900 truncate">{{ $gk->player->name }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="text-2xl md:text-3xl font-bold text-sky-600">{{ $gk->clean_sheets }}</span>
                                    <span class="text-xs text-sky-600/70 ml-0.5">{{ __('season.clean_sheets') }}</span>
                                </div>
                            </div>
                            @else
                            <div class="text-slate-400 text-sm">{{ __('season.not_enough_data') }}</div>
                            @endif
                        </div>
                        @if($topGoalkeepers->count() > 1)
                        <div class="px-5 py-3 space-y-1.5">
                            @foreach($topGoalkeepers->skip(1) as $gk)
                            <div class="flex items-center gap-2.5 {{ $gk->team_id === $game->team_id ? 'bg-sky-50 -mx-2 px-2 rounded' : '' }}">
                                <span class="w-5 text-center text-xs font-bold text-slate-400">{{ $loop->iteration + 1 }}</span>
                                <x-team-crest :team="$gk->team" class="w-4 h-4 shrink-0" />
                                <span class="flex-1 text-sm text-slate-700 truncate">{{ $gk->player->name }}</span>
                                <span class="text-xs text-slate-400 w-16 text-right">{{ $gk->clean_sheets }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Most Assists Card --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-emerald-50 to-emerald-100/50 px-5 py-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">&#129351;</span>
                                <span class="text-xs text-emerald-700 font-semibold uppercase tracking-wide">{{ __('season.most_assists') }}</span>
                            </div>
                            @if($topAssisters->isNotEmpty())
                            @php $assister = $topAssisters->first(); @endphp
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-team-crest :team="$assister->team" class="w-6 h-6 shrink-0" />
                                    <span class="font-bold text-slate-900 truncate">{{ $assister->player->name }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="text-2xl md:text-3xl font-bold text-emerald-600">{{ $assister->assists }}</span>
                                    <span class="text-xs text-emerald-600/70 ml-0.5">{{ __('season.assists') }}</span>
                                </div>
                            </div>
                            @else
                            <div class="text-slate-400 text-sm">{{ __('season.no_assists_recorded') }}</div>
                            @endif
                        </div>
                        @if($topAssisters->count() > 1)
                        <div class="px-5 py-3 space-y-1.5">
                            @foreach($topAssisters->skip(1) as $assister)
                            <div class="flex items-center gap-2.5 {{ $assister->team_id === $game->team_id ? 'bg-emerald-50 -mx-2 px-2 rounded' : '' }}">
                                <span class="w-5 text-center text-xs font-bold text-slate-400">{{ $loop->iteration + 1 }}</span>
                                <x-team-crest :team="$assister->team" class="w-4 h-4 shrink-0" />
                                <span class="flex-1 text-sm text-slate-700 truncate">{{ $assister->player->name }}</span>
                                <span class="text-xs text-slate-400 w-10 text-right">{{ $assister->assists }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                </div>
            </div>

            {{-- ============================================ --}}
            {{-- SECTION 4: Bold Picks Highlight              --}}
            {{-- ============================================ --}}
            @if(!empty($squadHighlights['bold_picks']))
            <div class="mt-8 mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-violet-50 to-purple-100/50 px-5 py-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-lg">&#9889;</span>
                            <span class="text-xs text-violet-700 font-semibold uppercase tracking-wide">{{ __('season.bold_picks') }}</span>
                        </div>
                        <p class="text-xs text-violet-600/70">{{ __('season.bold_picks_desc') }}</p>
                    </div>
                    <div class="px-5 py-3 space-y-2">
                        @foreach($squadHighlights['bold_picks'] as $pick)
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-sm font-semibold text-slate-900 truncate">{{ $pick['name'] }}</span>
                                <span class="shrink-0 text-[10px] text-violet-600 bg-violet-100 px-1.5 py-0.5 rounded font-semibold">{{ $pick['overall'] }} OVR</span>
                            </div>
                            <div class="shrink-0 flex items-center gap-2 text-xs text-slate-500">
                                @if($pick['goals'] > 0)<span class="font-semibold text-slate-700">{{ $pick['goals'] }}G</span>@endif
                                @if($pick['assists'] > 0)<span class="font-semibold text-slate-700">{{ $pick['assists'] }}A</span>@endif
                                <span>{{ $pick['appearances'] }}{{ __('season.played_abbr') }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            @if(!empty($squadHighlights['omissions']))
            <div class="mb-6">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-rose-50 to-red-100/50 px-5 py-4">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-lg">&#10060;</span>
                            <span class="text-xs text-rose-700 font-semibold uppercase tracking-wide">{{ __('season.key_omissions') }}</span>
                        </div>
                        <p class="text-xs text-rose-600/70">{{ __('season.key_omissions_desc') }}</p>
                    </div>
                    <div class="px-5 py-3 space-y-2">
                        @foreach($squadHighlights['omissions'] as $omission)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-medium text-slate-700 truncate">{{ $omission['name'] }}</span>
                            <span class="shrink-0 text-[10px] text-rose-600 bg-rose-100 px-1.5 py-0.5 rounded font-semibold">{{ $omission['overall'] }} OVR</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- ============================================ --}}
            {{-- SECTION 5: Share & Challenge CTAs             --}}
            {{-- ============================================ --}}
            <div class="mt-10 mb-10" x-data="shareCard()">

                {{-- Hidden share card for html2canvas capture --}}
                <div x-ref="shareCard" style="display: none;">
                    <x-share-card
                        :team="$game->team"
                        :competition="$competition"
                        :resultLabel="$resultLabel"
                        :yourRecord="$yourRecord"
                        :squadHighlights="$squadHighlights"
                        :isChampion="$isChampion"
                    />
                </div>

                {{-- Hidden share text for Web Share API --}}
                <input type="hidden" x-ref="shareText" value="{{ __('season.share_text', [
                    'result' => __('season.result_' . $resultLabel),
                    'competition' => __($competition->name ?? 'game.wc2026_name'),
                    'team' => $game->team->name,
                ]) }}">

                {{-- Share & Challenge Buttons --}}
                <div class="text-center space-y-4">

                    {{-- Share Card Button --}}
                    <div>
                        <button
                            @click="openShareModal()"
                            :disabled="generating"
                            class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white rounded-lg text-sm font-semibold shadow-lg transition-all min-h-[44px] disabled:opacity-50"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span x-show="!generating">{{ __('season.create_share_card') }}</span>
                            <span x-show="generating" x-cloak>{{ __('season.generating_image') }}...</span>
                        </button>
                    </div>

                    {{-- Challenge a Friend Button --}}
                    <div x-data="{ challengeUrl: '{{ $existingChallenge?->getShareUrl() ?? '' }}', challengeCopied: false, creating: false }">
                        @if($existingChallenge)
                            <button
                                @click="
                                    if (navigator.clipboard) {
                                        navigator.clipboard.writeText(challengeUrl).then(() => {
                                            challengeCopied = true;
                                            setTimeout(() => challengeCopied = false, 2000);
                                        });
                                    }
                                "
                                class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-600 hover:to-yellow-500 text-slate-900 rounded-lg text-sm font-semibold shadow-lg transition-all min-h-[44px]"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span x-show="!challengeCopied">{{ __('season.copy_challenge_link') }}</span>
                                <span x-show="challengeCopied" x-cloak class="text-slate-700">{{ __('season.copied_to_clipboard') }}</span>
                            </button>
                        @else
                            <form method="POST" action="{{ route('game.challenge.create', $game->id) }}" @submit="creating = true">
                                @csrf
                                <button
                                    type="submit"
                                    :disabled="creating"
                                    class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-yellow-400 hover:from-amber-600 hover:to-yellow-500 text-slate-900 rounded-lg text-sm font-semibold shadow-lg transition-all min-h-[44px] disabled:opacity-50"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span x-show="!creating">{{ __('season.challenge_friend') }}</span>
                                    <span x-show="creating" x-cloak>{{ __('season.creating_challenge') }}...</span>
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Play Again --}}
                    <div class="mb-10 pt-2">
                        <a href="{{ route('select-team') }}"
                           class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-700 hover:to-emerald-600 text-white rounded-lg text-lg font-bold shadow-lg transition-all min-h-[44px]">
                            {{ __('season.play_again') }}
                        </a>
                    </div>
                </div>

                {{-- Share Card Modal --}}
                <div x-show="showModal" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="closeModal()">

                    {{-- Backdrop --}}
                    <div class="absolute inset-0 bg-slate-900/80" @click="closeModal()"></div>

                    {{-- Modal Content --}}
                    <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95">

                        {{-- Close button --}}
                        <button @click="closeModal()" class="absolute top-3 right-3 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>

                        <div class="p-5 md:p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-1">{{ __('season.share_card_title') }}</h3>
                            <p class="text-xs text-slate-500 mb-4">{{ __('season.share_card_subtitle') }}</p>

                            {{-- Card Preview --}}
                            <div class="flex justify-center mb-4">
                                <template x-if="ready && imageUrl">
                                    <img :src="imageUrl" class="rounded-xl shadow-lg max-w-[280px] w-full" alt="Share card">
                                </template>
                                <template x-if="generating">
                                    <div class="w-[280px] h-[420px] bg-slate-100 rounded-xl flex items-center justify-center">
                                        <div class="text-center">
                                            <svg class="animate-spin h-8 w-8 text-violet-500 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span class="text-xs text-slate-400">{{ __('season.generating_image') }}...</span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Action Buttons --}}
                            <template x-if="ready">
                                <div class="flex gap-3">
                                    <button @click="downloadImage()"
                                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-sm font-semibold transition-all min-h-[44px]">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        {{ __('season.download_image') }}
                                    </button>
                                    <button @click="shareImage()"
                                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white rounded-lg text-sm font-semibold transition-all min-h-[44px]">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                        </svg>
                                        {{ __('season.share_image') }}
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Flash: challenge URL created --}}
                @if(session('challenge_url'))
                <div x-data="{ show: true, linkCopied: false }" x-show="show" x-cloak
                     class="fixed bottom-4 left-4 right-4 md:left-auto md:right-4 md:w-96 z-50 bg-white rounded-xl shadow-2xl border border-amber-200 p-4"
                     x-transition>
                    <div class="flex items-start gap-3">
                        <span class="text-2xl shrink-0">&#127942;</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900">{{ __('season.challenge_created') }}</p>
                            <p class="text-xs text-slate-500 mt-0.5 truncate">{{ session('challenge_url') }}</p>
                            <div class="flex gap-2 mt-2">
                                <button @click="
                                    navigator.clipboard.writeText('{{ session('challenge_url') }}').then(() => {
                                        linkCopied = true;
                                        setTimeout(() => linkCopied = false, 2000);
                                    });
                                " class="text-xs font-semibold text-amber-700 hover:text-amber-800 min-h-[44px] flex items-center">
                                    <span x-show="!linkCopied">{{ __('season.copy_link') }}</span>
                                    <span x-show="linkCopied" x-cloak class="text-green-600">{{ __('season.copied_to_clipboard') }}</span>
                                </button>
                                <button @click="show = false" class="text-xs text-slate-400 hover:text-slate-500 min-h-[44px] flex items-center ml-2">{{ __('app.close') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
