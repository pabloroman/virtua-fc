@php
/** @var App\Models\TournamentSummary $summary */
/** @var App\Models\Competition $competition */
/** @var array $teams - stdClass objects keyed by team ID */
/** @var string $playerTeamId */
/** @var string|null $championTeamId */
/** @var array|null $finalMatch */
/** @var array $finalGoalEvents */
/** @var string|null $finalistTeamId */
/** @var string $resultLabel */
/** @var array $yourMatches */
/** @var array $yourRecord */
/** @var array $topScorers */
/** @var array $topAssisters */
/** @var array $topGoalkeepers */
/** @var array $yourSquadStats */
/** @var array $topMvps */
/** @var array $mvpCounts */
/** @var array $groupStandings */
/** @var array $knockoutTies */

$isChampion = $championTeamId === $playerTeamId;
$championTeam = $championTeamId ? ($teams[$championTeamId] ?? null) : null;
$finalistTeam = $finalistTeamId ? ($teams[$finalistTeamId] ?? null) : null;
$playerTeam = $teams[$playerTeamId] ?? null;

$yourGoalScorers = collect($yourSquadStats)->where('goals', '>', 0)->sortByDesc('goals');
$yourAppearances = collect($yourSquadStats)->where('appearances', '>', 0)->sortByDesc('appearances');

// Group squad by position for image download
$positionGroupLabels = [
    'Goalkeeper' => __('squad.goalkeepers'),
    'Defender' => __('squad.defenders'),
    'Midfielder' => __('squad.midfielders'),
    'Forward' => __('squad.forwards'),
];
$squadByGroup = $yourAppearances
    ->groupBy(fn($p) => \App\Support\PositionMapper::getPositionGroup($p['position']))
    ->map(fn($players) => $players->map(fn($p) => [
        'name' => $p['player_name'],
        'appearances' => $p['appearances'],
        'goals' => $p['goals'],
        'assists' => $p['assists'],
    ])->values()->toArray())
    ->toArray();

// Result badge colors
$resultBadgeClass = match($resultLabel) {
    'champion'          => 'bg-accent-gold/20 text-accent-gold border-accent-gold/20',
    'runner_up'         => 'bg-surface-600 text-text-body border-border-default',
    'third_place'       => 'bg-accent-orange/10 text-accent-orange border-accent-orange/20',
    'semi_finalist'     => 'bg-accent-blue/10 text-blue-400 border-accent-blue/20',
    'quarter_finalist'  => 'bg-accent-blue/10 text-accent-blue border-accent-blue/20',
    default             => 'bg-surface-700 text-text-secondary border-border-default',
};

// Group final goal events by team, then by player
$homeGoals = collect();
$awayGoals = collect();
if ($finalMatch && !empty($finalGoalEvents)) {
    foreach ($finalGoalEvents as $event) {
        $playerName = $event['player_name'] ?? '?';
        $isOwnGoal = $event['is_own_goal'] ?? false;

        $scoringTeamId = $isOwnGoal
            ? ($event['team_id'] === $finalMatch['home_team_id'] ? $finalMatch['away_team_id'] : $finalMatch['home_team_id'])
            : $event['team_id'];

        $entry = [
            'player' => $playerName,
            'minute' => $event['minute'],
            'own_goal' => $isOwnGoal,
        ];

        if ($scoringTeamId === $finalMatch['home_team_id']) {
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
    <div class="min-h-screen bg-surface-900" x-data="tournamentSummary({
        teamName: @js($playerTeam->name ?? ''),
        teamCrestUrl: @js($playerTeam->image ?? ''),
        resultLabel: @js(__('season.result_' . $resultLabel)),
        isChampion: @js($isChampion),
        record: @js($yourRecord),
        squadByGroup: @js($squadByGroup),
        groupLabels: @js($positionGroupLabels),
        statLabels: @js([
            'played' => __('season.played_abbr'),
            'won' => __('season.won'),
            'drawn' => __('season.drawn'),
            'lost' => __('season.lost'),
            'gf' => __('season.goals_for'),
            'ga' => __('season.goals_against'),
            'gd' => __('season.goal_diff_abbr'),
            'apps' => __('squad.appearances'),
            'goals' => __('squad.goals'),
            'assists' => __('squad.assists'),
        ]),
    })">

        {{-- ============================================ --}}
        {{-- SECTION 1: Hero Header + Final Scoreboard    --}}
        {{-- ============================================ --}}
        <div class="relative overflow-hidden {{ $isChampion ? 'bg-linear-to-b from-amber-600 via-amber-500 to-amber-400' : 'bg-linear-to-b from-slate-800 to-slate-900' }} py-10 md:py-16 pb-16 md:pb-24">
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
                <h1 class="font-heading text-2xl md:text-4xl font-bold text-white mb-1 tracking-tight">
                    {{ __('season.tournament_champion') }}
                </h1>

                <div class="inline-flex flex-col items-center mb-8">
                    <x-team-crest :team="$championTeam"
                         class="w-20 h-20 md:w-28 md:h-28 drop-shadow-lg" />
                    <div class="mt-2 font-heading text-xl md:text-2xl font-bold text-white">{{ $championTeam->name }}</div>
                </div>
                @else
                <h1 class="font-heading text-2xl md:text-4xl font-bold text-white mb-1 tracking-tight">
                    {{ __('season.tournament_complete') }}
                </h1>
                <p class="text-sm md:text-base text-text-body font-medium mb-8">
                    {{ __($competitionName ?? 'game.wc2026_name') }}
                </p>
                @endif

                {{-- Final Scoreboard Card --}}
                @if($finalMatch && $championTeam && $finalistTeam)
                @php
                    $homeTeam = $teams[$finalMatch['home_team_id']] ?? null;
                    $awayTeam = $teams[$finalMatch['away_team_id']] ?? null;
                    $homeIsWinner = $championTeamId === $finalMatch['home_team_id'];
                    $awayIsWinner = $championTeamId === $finalMatch['away_team_id'];
                @endphp
                @if($homeTeam && $awayTeam)
                <div class="bg-slate-900/70 backdrop-blur-xs rounded-xl border border-white/10 p-4 md:p-6 max-w-lg mx-auto">
                    <div class="text-[10px] md:text-xs font-heading uppercase tracking-widest {{ $isChampion ? 'text-amber-300/70' : 'text-white/40' }} font-semibold mb-3">
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
                            <div class="font-heading text-2xl md:text-3xl font-bold text-white">
                                {{ $finalMatch['home_score'] }} - {{ $finalMatch['away_score'] }}
                            </div>
                            @if($finalMatch['is_extra_time'])
                            <div class="text-[10px] text-white/50 mt-0.5">
                                @if($finalMatch['home_score_penalties'] !== null)
                                    {{ __('season.aet_abbr') }} &middot; {{ __('season.pens_abbr') }} {{ $finalMatch['home_score_penalties'] }}-{{ $finalMatch['away_score_penalties'] }}
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
                @endif
            </div>
        </div>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-8 md:-mt-12 relative z-10 pb-12">

            {{-- ============================================ --}}
            {{-- SECTION 2: Expandable Full Tournament Results --}}
            {{-- ============================================ --}}
            @if(!empty($groupStandings) || !empty($knockoutTies))
            <div class="mb-6" x-data="{ showResults: false, tab: 'groups' }">
                <x-ghost-button
                    color="slate"
                    @click="showResults = !showResults"
                    class="w-full bg-surface-800 rounded-xl border border-border-default p-4 justify-between gap-3"
                >
                    <span class="text-sm font-semibold text-text-secondary">{{ __('season.full_tournament_results') }}</span>
                    <svg class="w-5 h-5 text-text-secondary transition-transform duration-200" :class="showResults && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </x-ghost-button>

                <div x-show="showResults" x-collapse class="mt-2">
                    <x-section-card>
                        {{-- Tabs --}}
                        <div class="flex border-b border-border-default">
                            @if(!empty($groupStandings))
                            <x-tab-button
                                @click="tab = 'groups'"
                                class="flex-1 text-center min-h-[44px]"
                                x-bind:class="tab === 'groups' ? 'text-text-primary border-border-default' : 'text-text-secondary hover:text-text-secondary border-transparent'"
                            >
                                {{ __('season.group_stage_standings') }}
                            </x-tab-button>
                            @endif
                            @if(!empty($knockoutTies))
                            <x-tab-button
                                @click="tab = 'knockout'"
                                class="flex-1 text-center min-h-[44px]"
                                x-bind:class="tab === 'knockout' ? 'text-text-primary border-border-default' : 'text-text-secondary hover:text-text-secondary border-transparent'"
                            >
                                {{ __('game.knockout_phase') }}
                            </x-tab-button>
                            @endif
                        </div>

                        {{-- Groups Tab --}}
                        @if(!empty($groupStandings))
                        <div x-show="tab === 'groups'" class="p-4 md:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                                @foreach($groupStandings as $groupLabel => $standings)
                                <div>
                                    <h3 class="font-heading text-xs font-semibold text-text-muted uppercase tracking-widest mb-2">
                                        {{ __('season.group_label', ['group' => $groupLabel]) }}
                                    </h3>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr class="text-[10px] text-text-muted uppercase tracking-wide">
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
                                                @php $standingTeam = $teams[$standing['team_id']] ?? null; @endphp
                                                <tr class="{{ $standing['team_id'] === $playerTeamId ? 'bg-accent-gold/10 font-semibold' : '' }} {{ $standing['position'] <= 2 ? 'border-l-2 border-l-emerald-400' : '' }}">
                                                    <td class="py-1.5 pr-1 text-center text-xs text-text-secondary">{{ $standing['position'] }}</td>
                                                    <td class="py-1.5">
                                                        <div class="flex items-center gap-1.5">
                                                            @if($standingTeam)
                                                            <x-team-crest :team="$standingTeam" class="w-4 h-4 shrink-0" />
                                                            <span class="text-xs truncate">{{ $standingTeam->name }}</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="text-center py-1.5 text-xs text-text-muted">{{ $standing['played'] }}</td>
                                                    <td class="text-center py-1.5 text-xs text-text-muted">{{ $standing['won'] }}</td>
                                                    <td class="text-center py-1.5 text-xs text-text-muted">{{ $standing['drawn'] }}</td>
                                                    <td class="text-center py-1.5 text-xs text-text-muted">{{ $standing['lost'] }}</td>
                                                    <td class="text-center py-1.5 text-xs text-text-muted hidden md:table-cell">{{ $standing['goals_for'] }}</td>
                                                    <td class="text-center py-1.5 text-xs text-text-muted hidden md:table-cell">{{ $standing['goals_against'] }}</td>
                                                    <td class="text-center py-1.5 text-xs font-semibold text-text-primary">{{ $standing['points'] }}</td>
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
                        @if(!empty($knockoutTies))
                        <div x-show="tab === 'knockout'" class="p-4 md:p-6">
                            <div class="space-y-6">
                                @foreach(collect($knockoutTies)->sortKeysDesc() as $roundNumber => $round)
                                @php
                                    $roundName = $round['round_name'] ? __($round['round_name']) : __('cup.round_n', ['round' => $roundNumber]);
                                @endphp
                                <div>
                                    <h3 class="font-heading text-xs font-semibold text-text-muted uppercase tracking-widest mb-3">{{ $roundName }}</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        @foreach($round['ties'] as $tie)
                                        @php
                                            $homeScore = $tie['home_score'] ?? 0;
                                            $awayScore = $tie['away_score'] ?? 0;
                                            $involvesPlayer = $tie['home_team_id'] === $playerTeamId || $tie['away_team_id'] === $playerTeamId;
                                            $isHomeWinner = $tie['winner_id'] === $tie['home_team_id'];
                                            $isAwayWinner = $tie['winner_id'] === $tie['away_team_id'];
                                            $tieHomeTeam = $teams[$tie['home_team_id']] ?? null;
                                            $tieAwayTeam = $teams[$tie['away_team_id']] ?? null;
                                        @endphp
                                        @if($tieHomeTeam && $tieAwayTeam)
                                        <div class="border rounded-lg p-3 {{ $involvesPlayer ? 'border-accent-gold/20 bg-accent-gold/10' : 'border-border-default' }}">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2 flex-1 min-w-0 {{ $isHomeWinner ? 'font-semibold' : '' }}">
                                                    <x-team-crest :team="$tieHomeTeam" class="w-5 h-5 shrink-0" />
                                                    <span class="text-sm truncate {{ $isHomeWinner ? 'text-text-primary' : 'text-text-muted' }}">{{ $tieHomeTeam->name }}</span>
                                                </div>
                                                <div class="shrink-0 text-center">
                                                    <span class="font-heading text-sm font-semibold text-text-primary">{{ $homeScore }} - {{ $awayScore }}</span>
                                                    @if($tie['is_extra_time'] ?? false)
                                                    <div class="text-[10px] text-text-secondary">
                                                        @if($tie['home_score_penalties'] !== null)
                                                            {{ __('season.pens_abbr') }} {{ $tie['home_score_penalties'] }}-{{ $tie['away_score_penalties'] }}
                                                        @else
                                                            {{ __('season.aet_abbr') }}
                                                        @endif
                                                    </div>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-2 flex-1 min-w-0 justify-end {{ $isAwayWinner ? 'font-semibold' : '' }}">
                                                    <span class="text-sm truncate text-right {{ $isAwayWinner ? 'text-text-primary' : 'text-text-muted' }}">{{ $tieAwayTeam->name }}</span>
                                                    <x-team-crest :team="$tieAwayTeam" class="w-5 h-5 shrink-0" />
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        @endforeach
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </x-section-card>
                </div>
            </div>
            @endif

            {{-- ============================================ --}}
            {{-- SECTION 3: Two-Column Main Content           --}}
            {{-- ============================================ --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                {{-- LEFT COLUMN: Your Performance --}}
                <div class="md:col-span-2">

                    <x-section-card>
                        <div class="p-5 md:p-6 space-y-6">

                            {{-- Badge + Team --}}
                            @if($playerTeam)
                            <div class="flex items-center gap-3">
                                <x-team-crest :team="$playerTeam" class="w-12 h-12 md:w-14 md:h-14 shrink-0" />
                                <div class="min-w-0 md:w-full md:min-w-max md:flex md:justify-between">
                                    <div class="font-heading text-lg md:text-xl font-bold text-text-primary truncate">{{ $playerTeam->name }}</div>
                                    <span class="inline-block mt-1 px-3 py-0.5 text-xs font-bold uppercase tracking-wide rounded-full border {{ $resultBadgeClass }}">
                                        {{ __('season.result_' . $resultLabel) }}
                                    </span>
                                </div>
                            </div>
                            @endif

                            {{-- Quick Stats Row --}}
                            <div class="grid grid-cols-7 gap-1 text-center bg-surface-700/50 border border-border-default rounded-lg p-3">
                                <div>
                                    <div class="font-heading text-lg md:text-xl font-bold text-text-primary">{{ $yourRecord['played'] }}</div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('season.played_abbr') }}</div>
                                </div>
                                <div>
                                    <div class="font-heading text-lg md:text-xl font-bold text-accent-green">{{ $yourRecord['won'] }}</div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('season.won') }}</div>
                                </div>
                                <div>
                                    <div class="font-heading text-lg md:text-xl font-bold text-text-secondary">{{ $yourRecord['drawn'] }}</div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('season.drawn') }}</div>
                                </div>
                                <div>
                                    <div class="font-heading text-lg md:text-xl font-bold text-accent-red">{{ $yourRecord['lost'] }}</div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('season.lost') }}</div>
                                </div>
                                <div>
                                    <div class="font-heading text-lg md:text-xl font-bold text-text-primary">{{ $yourRecord['goalsFor'] }}</div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('season.goals_for') }}</div>
                                </div>
                                <div>
                                    <div class="font-heading text-lg md:text-xl font-bold text-text-primary">{{ $yourRecord['goalsAgainst'] }}</div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('season.goals_against') }}</div>
                                </div>
                                <div>
                                    <div class="font-heading text-lg md:text-xl font-bold {{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] >= 0 ? 'text-accent-green' : 'text-accent-red' }}">
                                        {{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] >= 0 ? '+' : '' }}{{ $yourRecord['goalsFor'] - $yourRecord['goalsAgainst'] }}
                                    </div>
                                    <div class="text-[10px] text-text-muted uppercase tracking-widest">{{ __('season.goal_diff_abbr') }}</div>
                                </div>
                            </div>

                            {{-- Match Journey --}}
                            <div class="space-y-1.5">
                                <h2 class="font-heading text-xs font-semibold text-text-secondary uppercase tracking-widest mb-4">{{ __('season.your_journey') }}</h2>

                                @foreach($yourMatches as $index => $match)
                                    @php
                                        $isHome = $match['home_team_id'] === $playerTeamId;
                                        $opponent = $teams[$isHome ? $match['away_team_id'] : $match['home_team_id']] ?? null;
                                        $scored = $isHome ? $match['home_score'] : $match['away_score'];
                                        $conceded = $isHome ? $match['away_score'] : $match['home_score'];
                                        $resultClass = $scored > $conceded ? 'bg-accent-green' : ($scored < $conceded ? 'bg-accent-red' : 'bg-surface-600');
                                        $resultLetter = $scored > $conceded ? 'W' : ($scored < $conceded ? 'L' : 'D');
                                    @endphp
                                    @if($opponent)
                                    <div class="flex items-center gap-2.5 py-2 px-2.5 rounded-lg {{ $index % 2 === 1 ? 'bg-surface-700/50' : '' }}">
                                        <span class="shrink-0 w-6 h-6 rounded-sm text-[10px] font-bold flex items-center justify-center text-white {{ $resultClass }}">
                                            {{ $resultLetter }}
                                        </span>

                                        <span class="hidden md:inline text-[10px] text-text-muted w-14 shrink-0 truncate">
                                            {{ $match['round_name'] ? __($match['round_name']) : __('game.matchday_n', ['number' => $match['round_number']]) }}
                                        </span>

                                        <div class="flex items-center gap-1.5 flex-1 min-w-0">
                                            <x-team-crest :team="$opponent" class="w-4 h-4 shrink-0" />
                                            <span class="text-sm font-medium text-text-primary truncate">
                                                {{ $opponent->name }}
                                            </span>
                                        </div>

                                        <div class="shrink-0 font-heading text-sm font-semibold text-text-primary">
                                            {{ $scored }}-{{ $conceded }}
                                        </div>

                                        @if($match['is_extra_time'])
                                        <span class="shrink-0 text-[10px] text-text-secondary font-medium">
                                            {{ $match['home_score_penalties'] !== null ? __('season.pens_abbr') : __('season.aet_abbr') }}
                                        </span>
                                        @endif
                                    </div>
                                    @endif
                                @endforeach
                            </div>

                            {{-- Squad Stats --}}
                            @if($yourAppearances->isNotEmpty())
                            <div>
                                <h2 class="font-heading text-xs font-semibold text-text-secondary uppercase tracking-widest mb-3">{{ __('season.your_squad_stats') }}</h2>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-[10px] text-text-muted uppercase tracking-wide border-b border-border-default">
                                                <th class="text-left py-2"></th>
                                                <th class="text-left py-2"></th>
                                                <th class="text-center py-2 w-10">{{ __('squad.appearances') }}</th>
                                                <th class="text-center py-2 w-10">{{ __('squad.goals') }}</th>
                                                <th class="text-center py-2 w-10">{{ __('squad.assists') }}</th>
                                                <th class="text-center py-2 w-10 text-accent-gold">{{ __('squad.mvp') }}</th>
                                                <th class="text-center py-2 w-10 hidden md:table-cell">{{ __('squad.yellow_cards') }}</th>
                                                <th class="text-center py-2 w-10 hidden md:table-cell">{{ __('squad.red_cards') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($yourAppearances as $index => $gp)
                                            <tr class="{{ $index % 2 === 1 ? 'bg-surface-700/50' : '' }}">
                                                <td class="py-1.5 pr-2"><x-position-badge :position="$gp['position']" size="sm" /></td>
                                                <td class="py-1.5 font-medium text-text-primary truncate max-w-[140px]">{{ $gp['player_name'] }}</td>
                                                <td class="text-center py-1.5 font-semibold text-text-body">{{ $gp['appearances'] }}</td>
                                                <td class="text-center py-1.5 {{ $gp['goals'] > 0 ? 'font-semibold text-text-body' : 'text-text-muted' }}">{{ $gp['goals'] }}</td>
                                                <td class="text-center py-1.5 {{ $gp['assists'] > 0 ? 'font-semibold text-text-body' : 'text-text-muted' }}">{{ $gp['assists'] }}</td>
                                                @php $gpMvpCount = $mvpCounts[$gp['game_player_id']] ?? 0; @endphp
                                                <td class="text-center py-1.5 {{ $gpMvpCount > 0 ? 'font-semibold text-accent-gold' : 'text-text-muted' }}">{{ $gpMvpCount }}</td>
                                                <td class="text-center py-1.5 hidden md:table-cell {{ $gp['yellow_cards'] > 0 ? 'text-accent-gold font-medium' : 'text-text-muted' }}">{{ $gp['yellow_cards'] }}</td>
                                                <td class="text-center py-1.5 hidden md:table-cell {{ $gp['red_cards'] > 0 ? 'text-accent-red font-medium' : 'text-text-muted' }}">{{ $gp['red_cards'] }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif

                        </div>
                    </x-section-card>
                </div>

                {{-- RIGHT COLUMN: Tournament Awards --}}
                <div class="space-y-6">

                    {{-- Golden Boot --}}
                    <x-section-card>
                        <div class="bg-accent-gold/10 px-5 py-4 border-b border-accent-gold/20">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">&#129351;</span>
                                <span class="font-heading text-xs text-accent-gold font-semibold uppercase tracking-widest">{{ __('season.golden_boot') }}</span>
                            </div>
                            @if(!empty($topScorers))
                            @php $scorer = $topScorers[0]; @endphp
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if(isset($teams[$scorer['team_id']]))
                                    <x-team-crest :team="$teams[$scorer['team_id']]" class="w-6 h-6 shrink-0" />
                                    @endif
                                    <span class="font-bold text-text-primary truncate">{{ $scorer['player_name'] }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-heading text-2xl md:text-3xl font-bold text-accent-gold">{{ $scorer['goals'] }}</span>
                                    <span class="text-xs text-accent-gold/70 ml-0.5">{{ __('season.goals') }}</span>
                                </div>
                            </div>
                            @else
                            <div class="text-text-secondary text-sm">{{ __('season.no_goals_scored') }}</div>
                            @endif
                        </div>
                        @if(count($topScorers) > 1)
                        <div class="px-5 py-3 space-y-1.5">
                            @foreach(array_slice($topScorers, 1) as $i => $scorer)
                            <div class="flex items-center gap-2.5 {{ $scorer['team_id'] === $playerTeamId ? 'bg-accent-gold/10 -mx-2 px-2 rounded-sm' : '' }}">
                                <span class="w-5 text-center text-xs font-bold text-text-secondary">{{ $i + 2 }}</span>
                                @if(isset($teams[$scorer['team_id']]))
                                <x-team-crest :team="$teams[$scorer['team_id']]" class="w-4 h-4 shrink-0" />
                                @endif
                                <span class="flex-1 text-sm text-text-body truncate">{{ $scorer['player_name'] }}</span>
                                <span class="font-heading text-xs font-semibold text-text-secondary w-10 text-right">{{ $scorer['goals'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </x-section-card>

                    {{-- Golden Glove --}}
                    <x-section-card>
                        <div class="bg-accent-blue/10 px-5 py-4 border-b border-accent-blue/20">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">&#129351;</span>
                                <span class="font-heading text-xs text-accent-blue font-semibold uppercase tracking-widest">{{ __('season.golden_glove') }}</span>
                            </div>
                            @if(!empty($topGoalkeepers))
                            @php $gk = $topGoalkeepers[0]; @endphp
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if(isset($teams[$gk['team_id']]))
                                    <x-team-crest :team="$teams[$gk['team_id']]" class="w-6 h-6 shrink-0" />
                                    @endif
                                    <span class="font-bold text-text-primary truncate">{{ $gk['player_name'] }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-heading text-2xl md:text-3xl font-bold text-accent-blue">{{ $gk['clean_sheets'] }}</span>
                                    <span class="text-xs text-accent-blue/70 ml-0.5">{{ __('season.clean_sheets') }}</span>
                                </div>
                            </div>
                            @else
                            <div class="text-text-secondary text-sm">{{ __('season.not_enough_data') }}</div>
                            @endif
                        </div>
                        @if(count($topGoalkeepers) > 1)
                        <div class="px-5 py-3 space-y-1.5">
                            @foreach(array_slice($topGoalkeepers, 1) as $i => $gk)
                            <div class="flex items-center gap-2.5 {{ $gk['team_id'] === $playerTeamId ? 'bg-accent-blue/10 -mx-2 px-2 rounded-sm' : '' }}">
                                <span class="w-5 text-center text-xs font-bold text-text-secondary">{{ $i + 2 }}</span>
                                @if(isset($teams[$gk['team_id']]))
                                <x-team-crest :team="$teams[$gk['team_id']]" class="w-4 h-4 shrink-0" />
                                @endif
                                <span class="flex-1 text-sm text-text-body truncate">{{ $gk['player_name'] }}</span>
                                <span class="font-heading text-xs font-semibold text-text-secondary w-16 text-right">{{ $gk['clean_sheets'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </x-section-card>

                    {{-- Most Assists --}}
                    <x-section-card>
                        <div class="bg-accent-green/10 px-5 py-4 border-b border-accent-green/20">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">&#129351;</span>
                                <span class="font-heading text-xs text-accent-green font-semibold uppercase tracking-widest">{{ __('season.most_assists') }}</span>
                            </div>
                            @if(!empty($topAssisters))
                            @php $assister = $topAssisters[0]; @endphp
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if(isset($teams[$assister['team_id']]))
                                    <x-team-crest :team="$teams[$assister['team_id']]" class="w-6 h-6 shrink-0" />
                                    @endif
                                    <span class="font-bold text-text-primary truncate">{{ $assister['player_name'] }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-heading text-2xl md:text-3xl font-bold text-accent-green">{{ $assister['assists'] }}</span>
                                    <span class="text-xs text-accent-green/70 ml-0.5">{{ __('season.assists') }}</span>
                                </div>
                            </div>
                            @else
                            <div class="text-text-secondary text-sm">{{ __('season.no_assists_recorded') }}</div>
                            @endif
                        </div>
                        @if(count($topAssisters) > 1)
                        <div class="px-5 py-3 space-y-1.5">
                            @foreach(array_slice($topAssisters, 1) as $i => $assister)
                            <div class="flex items-center gap-2.5 {{ $assister['team_id'] === $playerTeamId ? 'bg-accent-green/10 -mx-2 px-2 rounded-sm' : '' }}">
                                <span class="w-5 text-center text-xs font-bold text-text-secondary">{{ $i + 2 }}</span>
                                @if(isset($teams[$assister['team_id']]))
                                <x-team-crest :team="$teams[$assister['team_id']]" class="w-4 h-4 shrink-0" />
                                @endif
                                <span class="flex-1 text-sm text-text-body truncate">{{ $assister['player_name'] }}</span>
                                <span class="font-heading text-xs font-semibold text-text-secondary w-10 text-right">{{ $assister['assists'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </x-section-card>

                    {{-- Most MVPs --}}
                    <x-section-card>
                        <div class="bg-accent-gold/10 px-5 py-4 border-b border-accent-gold/20">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg">&#9733;</span>
                                <span class="font-heading text-xs text-accent-gold font-semibold uppercase tracking-widest">{{ __('season.most_mvps') }}</span>
                            </div>
                            @if(!empty($topMvps))
                            @php $mvpWinner = $topMvps[0]; @endphp
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    @if(isset($teams[$mvpWinner['team_id']]))
                                    <x-team-crest :team="$teams[$mvpWinner['team_id']]" class="w-6 h-6 shrink-0" />
                                    @endif
                                    <span class="font-bold text-text-primary truncate">{{ $mvpWinner['player_name'] }}</span>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-heading text-2xl md:text-3xl font-bold text-accent-gold">{{ $mvpWinner['count'] }}</span>
                                    <span class="text-xs text-accent-gold/70 ml-0.5">{{ __('season.mvp_awards') }}</span>
                                </div>
                            </div>
                            @else
                            <div class="text-text-secondary text-sm">{{ __('season.no_mvps_awarded') }}</div>
                            @endif
                        </div>
                        @if(count($topMvps) > 1)
                        <div class="px-5 py-3 space-y-1.5">
                            @foreach(array_slice($topMvps, 1) as $i => $mvp)
                            <div class="flex items-center gap-2.5 {{ $mvp['team_id'] === $playerTeamId ? 'bg-accent-gold/10 -mx-2 px-2 rounded-sm' : '' }}">
                                <span class="w-5 text-center text-xs font-bold text-text-secondary">{{ $i + 2 }}</span>
                                @if(isset($teams[$mvp['team_id']]))
                                <x-team-crest :team="$teams[$mvp['team_id']]" class="w-4 h-4 shrink-0" />
                                @endif
                                <span class="flex-1 text-sm text-text-body truncate">{{ $mvp['player_name'] }}</span>
                                <span class="font-heading text-xs font-semibold text-text-secondary w-10 text-right">{{ $mvp['count'] }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </x-section-card>

                </div>
            </div>

            {{-- ============================================ --}}
            {{-- DONATION CTA                                 --}}
            {{-- ============================================ --}}
            <div class="mt-10">
                <x-donation-cta />
            </div>

            {{-- ============================================ --}}
            {{-- SECTION 4: Bottom CTAs                       --}}
            {{-- ============================================ --}}
            <div class="mt-10 mb-10 flex flex-col sm:flex-row items-center justify-center gap-3">
                <x-secondary-button
                    type="button"
                    @click="downloadTournamentImage()"
                    class="px-6 py-4 text-base"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span class="ml-1.5">{{ __('squad.download_squad') }}</span>
                </x-secondary-button>
                <x-primary-button-link href="{{ route('select-team') }}" color="green" class="px-8 py-4 text-lg font-bold">
                    {{ __('season.play_again') }}
                </x-primary-button-link>
            </div>

        </div>
    </div>
</x-app-layout>
