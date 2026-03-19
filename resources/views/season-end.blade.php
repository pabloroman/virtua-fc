@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var \Illuminate\Support\Collection $standings */
/** @var App\Models\GameStanding|null $playerStanding */
/** @var App\Models\GameStanding|null $champion */
/** @var array $standingsZones */

$isChampion = $champion && $champion->team_id === $game->team_id;

// Tailwind safelist: dynamic classes from zone config must appear as full strings for JIT
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
<div class="min-h-screen py-6 md:py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- ============================================================ --}}
        {{-- HERO BANNER                                                   --}}
        {{-- ============================================================ --}}

        <div class="text-center mb-8 md:mb-10">
            @if($isChampion)
                <div class="inline-block mb-4 relative">
                    <div class="absolute inset-0 rounded-full bg-amber-400/20 blur-xl scale-150"></div>
                    <div class="relative drop-shadow-lg">
                        <x-team-crest :team="$game->team" class="w-20 h-20 md:w-28 md:h-28 mx-auto" />
                    </div>
                </div>
                <h1 class="font-heading text-3xl md:text-5xl font-bold text-accent-gold tracking-tight mb-1">
                    {{ __('season.champion_label') }}
                </h1>
                <p class="text-lg text-text-secondary">{{ $game->team->name }} · {{ $game->formatted_season }}</p>
            @else
                <div class="inline-block drop-shadow-lg mb-4">
                    <x-team-crest :team="$game->team" class="w-20 h-20 md:w-28 md:h-28 mx-auto" />
                </div>
                <h1 class="font-heading text-3xl md:text-5xl font-bold text-text-primary mb-1">
                    {{ __('game.season_complete') }}
                </h1>
                <p class="text-lg text-text-secondary">{{ $game->team->name }} · {{ $game->formatted_season }}</p>
            @endif
        </div>

        {{-- ============================================================ --}}
        {{-- MANAGER EVALUATION CARD                                       --}}
        {{-- ============================================================ --}}

        @php
            $gradeAccent = [
                'exceptional' => 'border-l-green-500 bg-accent-green/10',
                'exceeded' => 'border-l-emerald-500 bg-accent-green/10',
                'met' => 'border-l-border-strong bg-surface-700/50',
                'below' => 'border-l-amber-500 bg-accent-gold/10',
                'disaster' => 'border-l-red-500 bg-accent-red/10',
            ];
            $gradeTextColors = [
                'exceptional' => 'text-accent-green',
                'exceeded' => 'text-accent-green',
                'met' => 'text-text-body',
                'below' => 'text-accent-gold',
                'disaster' => 'text-accent-red',
            ];
            $accentClass = $gradeAccent[$managerEvaluation['grade']] ?? $gradeAccent['met'];
            $textClass = $gradeTextColors[$managerEvaluation['grade']] ?? $gradeTextColors['met'];
        @endphp

        <x-section-card class="mb-6">
            <div class="border-l-4 {{ $accentClass }} p-5 md:p-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 mb-2">
                    <div class="font-heading font-bold text-base {{ $textClass }}">{{ $managerEvaluation['title'] }}</div>
                    <div class="text-xs text-text-secondary">
                        {{ __('season.target') }}: {{ $managerEvaluation['goalLabel'] }}
                        &rarr;
                        {{ __('season.actual') }}: {{ __('season.place', ['position' => $managerEvaluation['actualPosition']]) }}
                    </div>
                </div>
                <p class="text-sm text-text-body leading-relaxed">{{ $managerEvaluation['message'] }}</p>
            </div>
        </x-section-card>

        {{-- ============================================================ --}}
        {{-- STANDINGS + AWARDS                                            --}}
        {{-- ============================================================ --}}

        <x-section-card class="mb-6 !p-0">
            <div class="p-5 md:p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    {{-- Standings table --}}
                    <div class="md:col-span-2 space-y-4">
                        <h3 class="font-heading text-xs text-text-secondary uppercase tracking-widest font-semibold">
                            {{ __('season.final_standings') }}
                        </h3>

                        <div class="border border-border-default rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-right divide-y divide-border-default">
                                    <thead class="bg-surface-700/50">
                                    <tr class="text-xs text-text-muted uppercase tracking-wide">
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
                                    <tbody>
                                    @foreach($standings as $standing)
                                        @php
                                            $isPlayer = $standing->team_id === $game->team_id;
                                            $zoneClass = $getZoneClass($standing->position);
                                        @endphp
                                        <tr class="border-b border-border-default text-sm {{ $zoneClass }} @if($isPlayer) bg-accent-gold/10 font-semibold @endif">
                                            <td class="whitespace-nowrap text-left px-2 py-1.5 text-text-primary font-semibold">
                                                {{ $standing->position }}
                                            </td>
                                            <td class="whitespace-nowrap py-1.5 px-2">
                                                <div class="flex items-center space-x-2 @if($isPlayer) font-semibold @endif">
                                                    <x-team-crest :team="$standing->team" class="w-5 h-5 shrink-0" />
                                                    <span class="truncate">{{ $standing->team->name }}</span>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap py-1.5 px-2 text-text-secondary hidden md:table-cell">{{ $standing->won }}</td>
                                            <td class="whitespace-nowrap py-1.5 px-2 text-text-secondary hidden md:table-cell">{{ $standing->drawn }}</td>
                                            <td class="whitespace-nowrap py-1.5 px-2 text-text-secondary hidden md:table-cell">{{ $standing->lost }}</td>
                                            <td class="whitespace-nowrap py-1.5 px-2 text-text-secondary hidden md:table-cell">{{ $standing->goals_for }}</td>
                                            <td class="whitespace-nowrap py-1.5 px-2 text-text-secondary hidden md:table-cell">{{ $standing->goals_against }}</td>
                                            <td class="whitespace-nowrap py-1.5 px-2 text-text-secondary">{{ $standing->goal_difference }}</td>
                                            <td class="whitespace-nowrap py-1.5 px-2 font-semibold">{{ $standing->points }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Zone legend --}}
                            @if(count($standingsZones) > 0)
                            <div class="flex flex-wrap gap-x-5 gap-y-1 px-3 py-2.5 bg-surface-700/50 border-t border-border-default text-[11px] text-text-muted">
                                @foreach($standingsZones as $zone)
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2.5 h-2.5 {{ $bgColorMap[$zone['bgColor']] ?? '' }} rounded-xs"></div>
                                        <span>{{ __($zone['label']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        {{-- Reputation hint --}}
                        @if(!empty($reputationData))
                        @php
                            $repDirectionConfig = match($reputationData['direction']) {
                                'rising' => ['icon' => '&#9650;', 'color' => 'text-accent-green', 'label' => __('season.reputation_rising')],
                                'declining' => ['icon' => '&#9660;', 'color' => 'text-accent-red', 'label' => __('season.reputation_declining')],
                                default => ['icon' => '&#9654;', 'color' => 'text-text-secondary', 'label' => __('season.reputation_stable')],
                            };
                        @endphp
                        <div class="rounded-lg border border-border-default bg-surface-700/50 p-4 flex items-center justify-between">
                            <div>
                                <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1">{{ __('game.club_reputation') }}</div>
                                <div class="font-heading text-base font-bold text-text-primary">{{ __('finances.reputation.' . $reputationData['level']) }}</div>
                            </div>
                            <div class="text-right">
                                <span class="text-lg {{ $repDirectionConfig['color'] }}">{!! $repDirectionConfig['icon'] !!}</span>
                                <div class="text-[10px] {{ $repDirectionConfig['color'] }} font-medium">{{ $repDirectionConfig['label'] }}</div>
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Awards sidebar --}}
                    <div class="space-y-4">
                        <h3 class="font-heading text-xs text-text-secondary uppercase tracking-widest font-semibold">
                            {{ __('season.individual_awards') }}
                        </h3>

                        {{-- Pichichi — Top Scorer --}}
                        <div class="border border-border-default rounded-lg overflow-hidden">
                            <div class="px-4 py-2.5 bg-accent-gold/10 border-b border-accent-gold/20">
                                <div class="text-xs font-semibold text-accent-gold uppercase tracking-wide">
                                    {{ __($competition->getConfig()->getTopScorerAwardName()) }}
                                </div>
                            </div>
                            <div class="p-3">
                                @if($topScorers->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($topScorers as $index => $scorer)
                                        @php $isPlayerTeam = $scorer->team_id === $game->team_id; @endphp
                                        <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-accent-blue/10 -mx-1 px-1 py-0.5 rounded-sm @endif">
                                            <span class="w-5 text-center text-xs font-bold {{ $index === 0 ? 'text-accent-gold' : 'text-text-secondary' }}">{{ $index + 1 }}</span>
                                            <x-team-crest :team="$scorer->team" class="w-4 h-4 shrink-0" />
                                            <span class="flex-1 truncate @if($isPlayerTeam) font-medium @endif">{{ $scorer->player->name }}</span>
                                            <span class="font-heading font-bold tabular-nums text-text-primary">{{ $scorer->goals }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @else
                                <p class="text-sm text-text-secondary text-center py-2">{{ __('season.no_goals_scored') }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Zamora — Best Goalkeeper --}}
                        <div class="border border-border-default rounded-lg overflow-hidden">
                            <div class="px-4 py-2.5 bg-accent-blue/10 border-b border-accent-blue/20">
                                <div class="text-xs font-semibold text-accent-blue uppercase tracking-wide">
                                    {{ __($competition->getConfig()->getBestGoalkeeperAwardName()) }}
                                </div>
                            </div>
                            <div class="p-3">
                                @if($bestGoalkeeper)
                                    @php $isPlayerTeam = $bestGoalkeeper->team_id === $game->team_id; @endphp
                                    <div class="@if($isPlayerTeam) bg-accent-blue/10 rounded-sm p-2 -m-1 @endif">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <x-team-crest :team="$bestGoalkeeper->team" class="w-5 h-5 shrink-0" />
                                            <span class="font-semibold text-sm text-text-primary truncate">{{ $bestGoalkeeper->player->name }}</span>
                                        </div>
                                        <div class="flex items-baseline gap-3 text-xs text-text-muted">
                                            <span><span class="font-heading font-bold text-text-primary text-base tabular-nums">{{ $bestGoalkeeper->clean_sheets }}</span> {{ __('season.clean_sheets') }}</span>
                                            <span>{{ number_format($bestGoalkeeper->goals_conceded / max(1, $bestGoalkeeper->appearances), 2) }} {{ __('season.goals_per_game') }}</span>
                                        </div>
                                    </div>
                                @else
                                <p class="text-sm text-text-secondary text-center py-2">{{ __('season.not_enough_data') }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Most MVPs --}}
                        <div class="border border-border-default rounded-lg overflow-hidden">
                            <div class="px-4 py-2.5 bg-accent-yellow/10 border-b border-accent-yellow/20">
                                <div class="text-xs font-semibold text-accent-yellow uppercase tracking-wide flex items-center gap-1.5">
                                    <span>&#9733;</span>
                                    {{ __('season.most_mvps') }}
                                </div>
                            </div>
                            <div class="p-3">
                                @if($topMvps->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($topMvps as $index => $mvp)
                                        @php $isPlayerTeam = $mvp->gamePlayer->team_id === $game->team_id; @endphp
                                        <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-accent-blue/10 -mx-1 px-1 py-0.5 rounded-sm @endif">
                                            <span class="w-5 text-center text-xs font-bold {{ $index === 0 ? 'text-accent-yellow' : 'text-text-secondary' }}">{{ $index + 1 }}</span>
                                            <x-team-crest :team="$mvp->gamePlayer->team" class="w-4 h-4 shrink-0" />
                                            <span class="flex-1 truncate @if($isPlayerTeam) font-medium @endif">{{ $mvp->gamePlayer->player->name }}</span>
                                            <span class="font-heading font-bold tabular-nums text-text-primary">{{ $mvp->count }}</span>
                                        </div>
                                    @endforeach
                                </div>
                                @else
                                <p class="text-sm text-text-secondary text-center py-2">{{ __('season.no_mvps_awarded') }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Promoted / Relegated teams --}}
                        @if($promotionData)
                            @if(!empty($promotionData['promoted']))
                            <div class="border border-border-default rounded-lg overflow-hidden">
                                <div class="px-4 py-2.5 bg-accent-green/10 border-b border-accent-green/20">
                                    <div class="text-xs font-semibold text-accent-green uppercase tracking-wide">
                                        {{ __('season.promoted_to', ['league' => $promotionData['topLeagueName']]) }}
                                    </div>
                                </div>
                                <div class="p-3 space-y-1.5">
                                    @foreach($promotionData['promoted'] as $entry)
                                        @php
                                            $team = $promotionData['teams'][$entry['teamId']] ?? null;
                                            $isPlayerTeam = $entry['teamId'] === $game->team_id;
                                        @endphp
                                        @if($team)
                                        <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-accent-blue/10 -mx-1 px-1 py-0.5 rounded-sm @endif">
                                            <x-team-crest :team="$team" class="w-4 h-4 shrink-0" />
                                            <span class="flex-1 truncate @if($isPlayerTeam) font-medium @endif">{{ $team->name }}</span>
                                            <span class="text-xs text-text-secondary tabular-nums">{{ is_int($entry['position']) ? $entry['position'] . 'º' : $entry['position'] }}</span>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            @if(!empty($promotionData['relegated']))
                            <div class="border border-border-default rounded-lg overflow-hidden">
                                <div class="px-4 py-2.5 bg-accent-red/10 border-b border-accent-red/20">
                                    <div class="text-xs font-semibold text-accent-red uppercase tracking-wide">
                                        {{ __('season.relegated_to', ['league' => $promotionData['bottomLeagueName']]) }}
                                    </div>
                                </div>
                                <div class="p-3 space-y-1.5">
                                    @foreach($promotionData['relegated'] as $entry)
                                        @php
                                            $team = $promotionData['teams'][$entry['teamId']] ?? null;
                                            $isPlayerTeam = $entry['teamId'] === $game->team_id;
                                        @endphp
                                        @if($team)
                                        <div class="flex items-center gap-2 text-sm @if($isPlayerTeam) bg-accent-blue/10 -mx-1 px-1 py-0.5 rounded-sm @endif">
                                            <x-team-crest :team="$team" class="w-4 h-4 shrink-0" />
                                            <span class="flex-1 truncate @if($isPlayerTeam) font-medium @endif">{{ $team->name }}</span>
                                            <span class="text-xs text-text-secondary tabular-nums">{{ $entry['position'] }}º</span>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </x-section-card>

        {{-- ============================================================ --}}
        {{-- OTHER COMPETITIONS                                            --}}
        {{-- ============================================================ --}}

        @if(count($otherCompetitionResults) > 0)
        <x-section-card :title="__('season.your_other_competitions')" class="mb-6">
            <div class="p-5 md:p-6 space-y-2">
                @foreach($otherCompetitionResults as $result)
                    @php $comp = $result['competition']; @endphp
                    <div class="rounded-lg border px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-2
                        @if($result['wonCompetition']) border-accent-gold/20 bg-accent-gold/10 @else border-border-default bg-surface-700/50 @endif">

                        <div class="flex items-center gap-2.5">
                            @if($result['wonCompetition'])
                                <div class="w-7 h-7 rounded-full bg-accent-gold/10 flex items-center justify-center text-accent-gold text-sm shrink-0">&#9733;</div>
                            @else
                                <div class="w-7 h-7 rounded-full bg-surface-600 flex items-center justify-center text-text-secondary text-xs shrink-0">&#9917;</div>
                            @endif
                            <div>
                                <div class="font-semibold text-sm text-text-primary">{{ __($comp->name) }}</div>
                                @if($result['wonCompetition'])
                                    <div class="text-xs font-semibold text-accent-gold">{{ __('season.champion_label') }}</div>
                                @elseif($result['roundName'])
                                    <div class="text-xs text-text-muted">
                                        @if($result['eliminated'])
                                            {{ __('season.eliminated_in', ['round' => __($result['roundName'])]) }}
                                            @if($result['opponent'])
                                                {{ $result['opponent']->name }}
                                            @endif
                                        @else
                                            {{ __('season.reached_round', ['round' => __($result['roundName'])]) }}
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3 text-sm">
                            @if($result['swissStanding'])
                                <span class="text-xs text-text-muted bg-surface-600/60 rounded-sm px-2 py-0.5">
                                    {{ __('season.swiss_position', ['position' => $result['swissStanding']->position]) }}
                                </span>
                            @endif
                            @if($result['score'] && $result['opponent'])
                                <div class="flex items-center gap-1.5 text-xs text-text-muted">
                                    <x-team-crest :team="$result['opponent']" class="w-4 h-4 shrink-0" />
                                    <span class="font-mono tabular-nums font-semibold text-text-body">{{ $result['score'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-section-card>
        @endif

        {{-- ============================================================ --}}
        {{-- TEAM IN NUMBERS                                               --}}
        {{-- ============================================================ --}}

        <x-section-card :title="__('season.team_in_numbers')" class="mb-6">
            <div class="p-5 md:p-6">

                {{-- Player highlights --}}
                @if(($teamTopScorer && $teamTopScorer->goals > 0) || ($teamTopAssister && $teamTopAssister->assists > 0) || ($teamMostAppearances && $teamMostAppearances->appearances > 0) || $teamMvpLeader)
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                    @if($teamTopScorer && $teamTopScorer->goals > 0)
                    <div class="bg-accent-gold/10 border border-accent-gold/20 rounded-lg p-3">
                        <div class="text-[10px] text-accent-gold uppercase tracking-widest font-semibold mb-1.5">{{ __('season.your_top_scorer') }}</div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <x-team-crest :team="$teamTopScorer->team" class="w-4 h-4 shrink-0" />
                            <span class="text-sm font-medium text-text-primary truncate">{{ $teamTopScorer->player->name }}</span>
                        </div>
                        <div class="font-heading text-xl font-bold text-text-primary tabular-nums">{{ $teamTopScorer->goals }}</div>
                        <div class="text-[10px] text-accent-gold/80">{{ __('season.goals') }}</div>
                    </div>
                    @endif

                    @if($teamTopAssister && $teamTopAssister->assists > 0)
                    <div class="bg-accent-blue/10 border border-accent-blue/20 rounded-lg p-3">
                        <div class="text-[10px] text-accent-blue uppercase tracking-widest font-semibold mb-1.5">{{ __('season.your_top_assister') }}</div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <x-team-crest :team="$teamTopAssister->team" class="w-4 h-4 shrink-0" />
                            <span class="text-sm font-medium text-text-primary truncate">{{ $teamTopAssister->player->name }}</span>
                        </div>
                        <div class="font-heading text-xl font-bold text-text-primary tabular-nums">{{ $teamTopAssister->assists }}</div>
                        <div class="text-[10px] text-accent-blue/80">{{ __('season.assists') }}</div>
                    </div>
                    @endif

                    @if($teamMostAppearances && $teamMostAppearances->appearances > 0)
                    <div class="bg-accent-green/10 border border-accent-green/20 rounded-lg p-3">
                        <div class="text-[10px] text-accent-green uppercase tracking-widest font-semibold mb-1.5">{{ __('season.most_appearances') }}</div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <x-team-crest :team="$teamMostAppearances->team" class="w-4 h-4 shrink-0" />
                            <span class="text-sm font-medium text-text-primary truncate">{{ $teamMostAppearances->player->name }}</span>
                        </div>
                        <div class="font-heading text-xl font-bold text-text-primary tabular-nums">{{ $teamMostAppearances->appearances }}</div>
                        <div class="text-[10px] text-accent-green/80">{{ __('season.appearances') }}</div>
                    </div>
                    @endif

                    @if($teamMvpLeader)
                    <div class="bg-accent-yellow/10 border border-accent-yellow/20 rounded-lg p-3">
                        <div class="text-[10px] text-accent-yellow uppercase tracking-widest font-semibold mb-1.5">{{ __('season.your_team_mvp') }}</div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <x-team-crest :team="$teamMvpLeader->gamePlayer->team" class="w-4 h-4 shrink-0" />
                            <span class="text-sm font-medium text-text-primary truncate">{{ $teamMvpLeader->gamePlayer->player->name }}</span>
                        </div>
                        <div class="font-heading text-xl font-bold text-text-primary tabular-nums">{{ $teamMvpLeader->count }}</div>
                        <div class="text-[10px] text-accent-yellow/80">{{ __('season.mvp_awards') }}</div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Match records --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-3 mb-5">
                    @if($biggestVictory)
                    <div class="bg-accent-green/10 border border-accent-green/20 rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.biggest_victory') }}</div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <x-team-crest :team="$biggestVictory['opponent']" class="w-4 h-4 shrink-0" />
                            <span class="text-sm text-text-secondary truncate">{{ __('season.vs') }} {{ $biggestVictory['opponent']->name }}</span>
                        </div>
                        <div class="font-heading text-xl font-bold text-accent-green tabular-nums">{{ $biggestVictory['score'] }}</div>
                    </div>
                    @endif

                    @if($worstDefeat)
                    <div class="bg-accent-red/10 border border-accent-red/20 rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.worst_defeat') }}</div>
                        <div class="flex items-center gap-1.5 mb-1">
                            <x-team-crest :team="$worstDefeat['opponent']" class="w-4 h-4 shrink-0" />
                            <span class="text-sm text-text-secondary truncate">{{ __('season.vs') }} {{ $worstDefeat['opponent']->name }}</span>
                        </div>
                        <div class="font-heading text-xl font-bold text-accent-red tabular-nums">{{ $worstDefeat['score'] }}</div>
                    </div>
                    @else
                    <div class="bg-accent-green/10 border border-accent-green/20 rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.worst_defeat') }}</div>
                        <div class="font-heading text-xl font-bold text-accent-green mt-2">{{ __('season.no_defeats') }}</div>
                    </div>
                    @endif

                    <div class="bg-surface-700/50 border border-border-default rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.home_record') }}</div>
                        <div class="flex items-baseline gap-1 mt-2">
                            <span class="font-heading text-xl font-bold text-accent-green tabular-nums">{{ $homeRecord['w'] }}</span>
                            <span class="text-xs text-text-secondary">{{ __('season.won') }}</span>
                            <span class="font-heading text-xl font-bold text-text-secondary tabular-nums ml-1">{{ $homeRecord['d'] }}</span>
                            <span class="text-xs text-text-secondary">{{ __('season.drawn') }}</span>
                            <span class="font-heading text-xl font-bold text-accent-red tabular-nums ml-1">{{ $homeRecord['l'] }}</span>
                            <span class="text-xs text-text-secondary">{{ __('season.lost') }}</span>
                        </div>
                    </div>

                    <div class="bg-surface-700/50 border border-border-default rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.away_record') }}</div>
                        <div class="flex items-baseline gap-1 mt-2">
                            <span class="font-heading text-xl font-bold text-accent-green tabular-nums">{{ $awayRecord['w'] }}</span>
                            <span class="text-xs text-text-secondary">{{ __('season.won') }}</span>
                            <span class="font-heading text-xl font-bold text-text-secondary tabular-nums ml-1">{{ $awayRecord['d'] }}</span>
                            <span class="text-xs text-text-secondary">{{ __('season.drawn') }}</span>
                            <span class="font-heading text-xl font-bold text-accent-red tabular-nums ml-1">{{ $awayRecord['l'] }}</span>
                            <span class="text-xs text-text-secondary">{{ __('season.lost') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Discipline & extras --}}
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 md:gap-3">
                    <div class="bg-surface-700/50 border border-border-default rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.total_clean_sheets') }}</div>
                        <div class="font-heading text-xl font-bold text-text-primary tabular-nums mt-2">{{ $teamCleanSheets }}</div>
                    </div>

                    <div class="bg-surface-700/50 border border-border-default rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.cards') }}</div>
                        <div class="flex items-baseline gap-2 mt-2">
                            <div class="flex items-center gap-1">
                                <div class="w-3 h-4 rounded-xs bg-yellow-400"></div>
                                <span class="font-heading text-xl font-bold text-text-primary tabular-nums">{{ $teamYellowCards }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <div class="w-3 h-4 rounded-xs bg-accent-red"></div>
                                <span class="font-heading text-xl font-bold text-text-primary tabular-nums">{{ $teamRedCards }}</span>
                            </div>
                        </div>
                    </div>

                    @if($transferBalance !== 0)
                    <div class="bg-surface-700/50 border border-border-default rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.transfer_balance') }}</div>
                        <div class="font-heading text-xl font-bold tabular-nums mt-2 {{ $transferBalance >= 0 ? 'text-accent-green' : 'text-accent-red' }}">
                            {{ \App\Support\Money::format($transferBalance) }}
                        </div>
                    </div>
                    @endif

                    @if($userTeamRetiring->isNotEmpty())
                    <div class="bg-surface-700/50 border border-border-default rounded-lg p-3">
                        <div class="text-[10px] text-text-muted uppercase tracking-widest font-semibold mb-1.5">{{ __('season.retiring_label') }}</div>
                        <div class="font-heading text-xl font-bold text-accent-orange tabular-nums">
                            {{ $userTeamRetiring->count() === 1 ? __('season.player_retiring_singular') : __('season.players_retiring', ['count' => $userTeamRetiring->count()]) }}
                        </div>
                        <div class="mt-1.5 space-y-0.5">
                            @foreach($userTeamRetiring as $retiring)
                                <div class="text-xs text-text-secondary truncate">{{ $retiring->player->name }}</div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </x-section-card>

        {{-- ============================================================ --}}
        {{-- OTHER LEAGUE RESULTS (SIMULATED)                              --}}
        {{-- ============================================================ --}}

        @if(count($simulatedResults) > 0)
        <x-section-card :title="__('season.simulated_results')" class="mb-6">
            <div class="p-5 md:p-6">
                <div class="bg-surface-700/50 border border-border-default rounded-lg px-4 py-3">
                    <div class="space-y-2">
                        @foreach($simulatedResults as $simResult)
                            <div class="flex items-center gap-3 text-sm">
                                <span class="text-text-muted flex-1 truncate">{{ __('season.league_champion', ['league' => __($simResult['competition']->name)]) }}</span>
                                @if($simResult['champion'])
                                    <x-team-crest :team="$simResult['champion']" class="w-5 h-5 shrink-0" />
                                    <span class="font-semibold text-text-primary">{{ $simResult['champion']->name }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-section-card>
        @endif

        {{-- ============================================================ --}}
        {{-- DONATION CTA                                                  --}}
        {{-- ============================================================ --}}

        <div class="mb-8">
            <x-donation-cta />
        </div>

        {{-- ============================================================ --}}
        {{-- CTA: Start New Season                                         --}}
        {{-- ============================================================ --}}

        <div class="text-center py-6 md:py-10">
            @if(config('beta.allow_new_season'))
            <form method="post" action="{{ route('game.start-new-season', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
                @csrf
                <x-primary-button-spin color="red" class="px-8 py-4 text-lg font-bold">
                    {{ __('season.start_new_season', ['season' => \App\Models\Game::formatSeason((string)((int)$game->season + 1))]) }}
                </x-primary-button-spin>
            </form>
            @else
            <p class="text-text-secondary text-lg">{{ __('season.new_season_coming_soon') }}</p>
            @endif
        </div>

    </div>
</div>
</x-app-layout>
