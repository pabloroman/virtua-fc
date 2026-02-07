@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="null"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-8">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8 space-y-8">

                    {{-- Season Honours --}}
                    <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-4">
                        <span>&#9733;</span> {{ __('season.season_honours', ['season' => $game->season]) }} <span>&#9733;</span>
                    </div>

                    {{-- Major Trophies Grid --}}
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        {{-- League Champion --}}
                        <div class="text-center py-6 bg-gradient-to-b from-amber-50 to-white rounded-lg border border-amber-200">
                            <div class="text-amber-600 font-semibold text-sm uppercase tracking-wide mb-2">{{ __('season.league_champion', ['league' => $competition->name]) }}</div>
                            <div class="flex justify-center items-center gap-3 mb-2">
                                <img src="{{ $champion->team->image }}" class="w-14 h-14">
                            </div>
                            <div class="text-xl font-bold text-slate-900">{{ $champion->team->name }}</div>
                            <div class="text-sm text-slate-600">{{ $champion->points }} pts</div>
                        </div>

                        {{-- Cup Winner --}}
                        <div class="text-center py-6 bg-gradient-to-b from-sky-50 to-white rounded-lg border border-sky-200">
                            <div class="text-sky-600 font-semibold text-sm uppercase tracking-wide mb-2">{{ $cupName }}</div>
                            @if($cupWinner)
                                <div class="flex justify-center items-center gap-3 mb-2">
                                    <img src="{{ $cupWinner->image }}" class="w-14 h-14">
                                </div>
                                <div class="text-xl font-bold text-slate-900">{{ $cupWinner->name }}</div>
                                <div class="text-sm text-slate-500">
                                    {{ __('season.beat', ['team' => $cupRunnerUp?->name ?? 'opponent']) }}
                                </div>
                            @else
                                <div class="text-slate-400 py-4">{{ __('season.competition_in_progress') }}</div>
                            @endif
                        </div>
                    </div>

                    {{-- League Top 3 --}}
                    <div class="bg-slate-50 rounded-lg p-4 mb-6">
                        <div class="text-xs text-slate-500 uppercase tracking-wide mb-3 text-center">{{ __('season.final_standings') }}</div>
                        <div class="space-y-2">
                            @foreach($standings->take(3) as $standing)
                                <div class="flex items-center gap-3 {{ $standing->team_id === $game->team_id ? 'bg-amber-100 -mx-2 px-2 py-1 rounded' : '' }}">
                                    <div class="w-6 text-center font-bold {{ $standing->position === 1 ? 'text-amber-600' : ($standing->position === 2 ? 'text-slate-500' : 'text-amber-700') }}">
                                        {{ $standing->position }}
                                    </div>
                                    <img src="{{ $standing->team->image }}" class="w-6 h-6">
                                    <div class="flex-1 font-medium text-slate-900">{{ $standing->team->name }}</div>
                                    <div class="text-slate-600 text-sm">{{ $standing->points }} pts</div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Your Season Section --}}
                    <div class="border-t pt-6">
                        <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-4">{{ __('season.your_season') }}</div>

                        <div class="flex justify-center items-center gap-4 mb-4">
                            <img src="{{ $game->team->image }}" class="w-12 h-12">
                            <div>
                                <div class="text-xl font-bold text-slate-900">{{ $game->team->name }}</div>
                            </div>
                        </div>

                        <div class="flex justify-center">
                            <div class="inline-block bg-slate-100 rounded-lg px-6 py-4 mb-4 text-center">
                                <div class="text-3xl font-bold text-slate-900">
                                    {{ __('season.place', ['position' => $playerStanding->position]) }}
                                </div>
                                <div class="text-lg text-slate-600">{{ $playerStanding->points }} {{ __('season.points') }}</div>
                            </div>
                        </div>

                        <div class="flex justify-center gap-6 text-sm text-slate-600 mb-6">
                            <div><span class="font-semibold">{{ __('season.won') }}</span> {{ $playerTeamStats['won'] }}</div>
                            <div><span class="font-semibold">{{ __('season.drawn') }}</span> {{ $playerTeamStats['drawn'] }}</div>
                            <div><span class="font-semibold">{{ __('season.lost') }}</span> {{ $playerTeamStats['lost'] }}</div>
                            <div><span class="font-semibold">{{ __('season.goals_for') }}</span> {{ $playerTeamStats['goalsFor'] }}</div>
                            <div><span class="font-semibold">{{ __('season.goals_against') }}</span> {{ $playerTeamStats['goalsAgainst'] }}</div>
                        </div>

                        {{-- Manager Evaluation --}}
                        @php
                            $gradeColors = [
                                'exceptional' => 'bg-green-100 border-green-300 text-green-800',
                                'exceeded' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                                'met' => 'bg-slate-100 border-slate-300 text-slate-700',
                                'below' => 'bg-amber-50 border-amber-200 text-amber-800',
                                'disaster' => 'bg-red-50 border-red-200 text-red-800',
                            ];
                            $gradeClass = $gradeColors[$managerEvaluation['grade']] ?? $gradeColors['met'];
                        @endphp
                        <div class="rounded-lg border p-4 {{ $gradeClass }}">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-bold text-lg">{{ $managerEvaluation['title'] }}</div>
                                <div class="text-sm">
                                    {{ __('season.target') }}: {{ __('season.place', ['position' => $managerEvaluation['targetPosition']]) }}
                                    &rarr;
                                    {{ __('season.actual') }}: {{ __('season.place', ['position' => $managerEvaluation['actualPosition']]) }}
                                </div>
                            </div>
                            <p class="text-sm opacity-90">{{ $managerEvaluation['message'] }}</p>
                        </div>
                    </div>

                    {{-- Season Awards Section --}}
                    <div class="border-t pt-6">
                        <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-6">
                            <span>&#9733;</span> {{ __('season.individual_awards') }} <span>&#9733;</span>
                        </div>

                        <div class="grid grid-cols-3 gap-4 mb-6">
                            {{-- Top Scorer --}}
                            <div class="bg-slate-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-2">{{ __('season.pichichi') }}</div>
                                @if($topScorers->isNotEmpty())
                                    @php $scorer = $topScorers->first(); @endphp
                                    <div class="flex items-center justify-center gap-2 mb-1">
                                        <img src="{{ $scorer->team->image }}" class="w-5 h-5">
                                        <span class="font-semibold text-slate-900">{{ $scorer->player->name }}</span>
                                    </div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $scorer->goals }}</div>
                                    <div class="text-xs text-slate-500">{{ __('season.goals') }}</div>
                                @else
                                    <div class="text-slate-400">{{ __('season.no_goals_scored') }}</div>
                                @endif
                            </div>

                            {{-- Most Assists --}}
                            <div class="bg-slate-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-2">{{ __('season.most_assists') }}</div>
                                @if($topAssisters->isNotEmpty())
                                    @php $assister = $topAssisters->first(); @endphp
                                    <div class="flex items-center justify-center gap-2 mb-1">
                                        <img src="{{ $assister->team->image }}" class="w-5 h-5">
                                        <span class="font-semibold text-slate-900">{{ $assister->player->name }}</span>
                                    </div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $assister->assists }}</div>
                                    <div class="text-xs text-slate-500">{{ __('season.assists') }}</div>
                                @else
                                    <div class="text-slate-400">{{ __('season.no_assists_recorded') }}</div>
                                @endif
                            </div>

                            {{-- Best Goalkeeper --}}
                            <div class="bg-slate-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-slate-500 uppercase tracking-wide mb-2">{{ __('season.zamora') }}</div>
                                @if($bestGoalkeeper)
                                    <div class="flex items-center justify-center gap-2 mb-1">
                                        <img src="{{ $bestGoalkeeper->team->image }}" class="w-5 h-5">
                                        <span class="font-semibold text-slate-900">{{ $bestGoalkeeper->player->name }}</span>
                                    </div>
                                    <div class="text-2xl font-bold text-slate-900">{{ $bestGoalkeeper->clean_sheets }}</div>
                                    <div class="text-xs text-slate-500">{{ __('season.clean_sheets') }}</div>
                                    <div class="text-xs text-slate-400 mt-1">
                                        {{ number_format($bestGoalkeeper->goals_conceded / max(1, $bestGoalkeeper->appearances), 2) }} {{ __('season.goals_per_game') }}
                                    </div>
                                @else
                                    <div class="text-slate-400">{{ __('season.not_enough_data') }}</div>
                                @endif
                            </div>
                        </div>

                        {{-- Team Awards --}}
                        <div class="grid grid-cols-2 gap-4">
                            {{-- Best Attack --}}
                            <div class="bg-slate-50 rounded-lg p-3 flex items-center gap-3">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-green-600 text-sm">
                                    &#9917;
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-slate-500 uppercase tracking-wide">{{ __('season.best_attack') }}</div>
                                    <div class="flex items-center gap-2">
                                        <img src="{{ $bestAttack->team->image }}" class="w-4 h-4">
                                        <span class="font-medium text-slate-900 text-sm">{{ $bestAttack->team->name }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-green-600">{{ $bestAttack->goals_for }}</div>
                                    <div class="text-xs text-slate-400">{{ __('season.goals') }}</div>
                                </div>
                            </div>

                            {{-- Best Defense --}}
                            <div class="bg-slate-50 rounded-lg p-3 flex items-center gap-3">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 text-sm">
                                    &#128737;
                                </div>
                                <div class="flex-1">
                                    <div class="text-xs text-slate-500 uppercase tracking-wide">{{ __('season.best_defense') }}</div>
                                    <div class="flex items-center gap-2">
                                        <img src="{{ $bestDefense->team->image }}" class="w-4 h-4">
                                        <span class="font-medium text-slate-900 text-sm">{{ $bestDefense->team->name }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-blue-600">{{ $bestDefense->goals_against }}</div>
                                    <div class="text-xs text-slate-400">{{ __('season.conceded') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Promotion & Relegation --}}
                    @if($relegatedTeams->isNotEmpty() || $directlyPromoted->isNotEmpty() || $playoffWinner)
                    <div class="border-t pt-6">
                        <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-4">{{ __('season.league_movements') }}</div>

                        <div class="grid {{ ($relegatedTeams->isNotEmpty() && ($directlyPromoted->isNotEmpty() || $playoffWinner)) ? 'grid-cols-2' : 'grid-cols-1' }} gap-4">
                            {{-- Promoted --}}
                            @if($directlyPromoted->isNotEmpty() || $playoffWinner)
                            <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                                <div class="flex items-center gap-2 text-green-700 font-semibold text-sm mb-3">
                                    <span>&#9650;</span> {{ __('season.promoted_to', ['league' => $promotionTargetLeague?->name ?? 'Top Division']) }}
                                </div>
                                <div class="space-y-2">
                                    @foreach($directlyPromoted as $promoted)
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $promoted->team->image }}" class="w-5 h-5">
                                            <span class="text-sm text-slate-900">{{ $promoted->team->name }}</span>
                                            <span class="text-xs text-green-600">({{ __('season.place', ['position' => $promoted->position]) }})</span>
                                        </div>
                                    @endforeach
                                    @if($playoffWinner)
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $playoffWinner->image }}" class="w-5 h-5">
                                            <span class="text-sm text-slate-900">{{ $playoffWinner->name }}</span>
                                            <span class="text-xs text-green-600">({{ __('season.playoff_winner') }})</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @endif

                            {{-- Relegated --}}
                            @if($relegatedTeams->isNotEmpty())
                            <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                                <div class="flex items-center gap-2 text-red-700 font-semibold text-sm mb-3">
                                    <span>&#9660;</span> {{ __('season.relegated_to', ['league' => $lowerTierLeague?->name ?? 'Lower Division']) }}
                                </div>
                                <div class="space-y-2">
                                    @foreach($relegatedTeams as $relegated)
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $relegated->team->image }}" class="w-5 h-5">
                                            <span class="text-sm text-slate-900">{{ $relegated->team->name }}</span>
                                            <span class="text-xs text-red-600">({{ __('season.place', ['position' => $relegated->position]) }})</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Player Development Preview --}}
                    @if($developmentPreview->isNotEmpty())
                    <div class="border-t pt-6">
                        <div class="text-center text-slate-500 font-semibold text-sm uppercase tracking-wide mb-4">{{ __('season.player_development') }}</div>

                        <div class="space-y-2">
                            @foreach($developmentPreview as $item)
                                @php
                                    $change = $item['overallChange'];
                                    $icon = $change > 0 ? '&#9650;' : ($change < 0 ? '&#9660;' : '&#8212;');
                                    $colorClass = $change > 0 ? 'text-green-600' : ($change < 0 ? 'text-red-600' : 'text-slate-400');
                                    $statusLabel = match($item['status']) {
                                        'growing' => __('season.growing'),
                                        'peak' => __('season.peak'),
                                        'declining' => __('season.declining'),
                                        default => '',
                                    };
                                    $statusClass = match($item['status']) {
                                        'growing' => 'text-green-600',
                                        'peak' => 'text-slate-600',
                                        'declining' => 'text-orange-600',
                                        default => '',
                                    };
                                @endphp
                                <div class="flex items-center justify-between py-2 px-3 rounded {{ $loop->even ? 'bg-slate-50' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <span class="{{ $colorClass }}">{!! $icon !!}</span>
                                        <span class="font-medium">{{ $item['player']->name }}</span>
                                        <span class="text-slate-400 text-sm">({{ $item['age'] }})</span>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-sm">
                                            <span class="text-slate-500">{{ $item['overallBefore'] }}</span>
                                            <span class="text-slate-400 mx-1">&#8594;</span>
                                            <span class="font-semibold">{{ $item['overallAfter'] }}</span>
                                            <span class="{{ $colorClass }} font-medium ml-1">
                                                ({{ $change > 0 ? '+' : '' }}{{ $change }})
                                            </span>
                                        </div>
                                        <span class="text-xs {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Start New Season CTA --}}
                    <div class="border-t pt-8 text-center">
                        <form method="post" action="{{ route('game.start-new-season', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-2 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-700 hover:to-red-600 text-white px-8 py-4 rounded-lg text-xl font-bold shadow-lg transition-all transform hover:scale-105"
                                    :disabled="loading">
                                <span x-show="!loading">{{ __('season.start_new_season', ['season' => (int)$game->season + 1]) }}</span>
                                <span x-show="loading" x-cloak>
                                    <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
