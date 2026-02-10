@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var int $availableSurplus */
/** @var array $tiers */
/** @var array $tierThresholds */
/** @var string|null $seasonGoal */
/** @var string|null $seasonGoalLabel */
/** @var int|null $seasonGoalTarget */
/** @var \Illuminate\Support\Collection<App\Models\Competition> $competitions */
@endphp

<x-app-layout>
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Welcome Header --}}
            <div class="text-center mb-8">
                <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}" class="w-20 h-20 mx-auto mb-4">
                <h1 class="text-3xl font-bold text-white mb-1">{{ __('game.welcome_to_team', ['team' => $game->team->name]) }}</h1>
                <p class="text-slate-500">{{ __('game.season_n', ['season' => $game->season]) }}</p>
            </div>

            {{-- Flash Messages --}}
            @if(session('error'))
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            {{-- Season Preview --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
                <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-6">{{ __('game.season_preview') }}</h2>

                {{-- Board Objective --}}
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-xl p-6 mb-6">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                        <h3 class="text-xs font-semibold text-amber-700 uppercase tracking-wide">{{ __('game.season_objective') }}</h3>
                    </div>
                    <div class="text-xl font-bold text-amber-950">{{ __($seasonGoalLabel ?? 'game.goal_top_half') }}</div>
                    <div class="text-sm text-amber-800 mt-1">{{ __('game.board_expects_position', ['position' => $seasonGoalTarget ?? 10]) }}</div>
                </div>

                {{-- Competitions --}}
                <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">{{ __('game.your_competitions') }}</h3>
                @php
                    $gridCols = match(min($competitions->count(), 4)) {
                        1 => 'grid-cols-1',
                        2 => 'grid-cols-2',
                        4 => 'grid-cols-4',
                        default => 'grid-cols-3',
                    };
                @endphp
                <div class="grid {{ $gridCols }} gap-3">
                    @foreach($competitions as $comp)
                        @php
                            $compAccent = match($comp->role) {
                                'domestic_cup' => ['border' => 'border-t-emerald-500', 'label' => __('game.competition_role_cup')],
                                'continental' => ['border' => 'border-t-blue-600', 'label' => __('game.competition_role_continental')],
                                default => ['border' => 'border-t-amber-500', 'label' => __('game.competition_role_league')],
                            };
                        @endphp
                        <div class="border border-slate-200 rounded-lg p-4 border-t-4 {{ $compAccent['border'] }}">
                            <div class="font-semibold text-slate-900">{{ $comp->name }}</div>
                            <div class="text-xs text-slate-500 mt-1">{{ $compAccent['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Budget Allocation --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('finances.season_budget', ['season' => $game->season]) }}</h2>
                        <p class="text-sm text-slate-500">{{ __('game.allocate_budget_hint') }}</p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-slate-900">{{ \App\Support\Money::format($availableSurplus) }}</div>
                        <div class="text-xs text-slate-500">{{ __('game.available') }}</div>
                    </div>
                </div>

                <x-budget-allocation
                    :available-surplus="$availableSurplus"
                    :tiers="$tiers"
                    :tier-thresholds="$tierThresholds"
                    :form-action="route('game.onboarding.complete', $game->id)"
                    :submit-label="__('game.begin_season')"
                />
            </div>

        </div>
    </div>
</x-app-layout>
