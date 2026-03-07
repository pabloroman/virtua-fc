@php
/** @var App\Models\Game $game */
/** @var App\Models\GameFinances $finances */
/** @var int $availableSurplus */
/** @var array $tiers */
/** @var array $tierThresholds */
/** @var string|null $seasonGoal */
/** @var string|null $seasonGoalLabel */
/** @var int|null $seasonGoalTarget */
/** @var string $reputationLevel */
/** @var \Illuminate\Support\Collection<App\Models\Competition> $competitions */
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Season Header --}}
            <div class="text-center mb-8">
                <x-team-crest :team="$game->team" class="w-20 h-20 mx-auto mb-4" />
                <h1 class="text-3xl font-bold text-white mb-1">{{ __('game.season_n', ['season' => $game->formatted_season]) }}</h1>
                <p class="text-slate-500">{{ $game->team->name }}</p>
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

                {{-- Club Reputation --}}
                <div class="flex items-center justify-between bg-slate-50 border border-slate-200 rounded-xl px-5 py-4 mb-6"
                     x-data="{ showTooltip: false }">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-slate-200 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-slate-400 uppercase tracking-wide font-semibold">{{ __('game.club_reputation') }}</div>
                            <div class="text-base font-bold text-slate-900">{{ __('finances.reputation.' . $reputationLevel) }}</div>
                        </div>
                    </div>
                    <div class="relative">
                        <button type="button" @click="showTooltip = !showTooltip" class="w-7 h-7 rounded-full bg-slate-200 hover:bg-slate-300 flex items-center justify-center text-slate-500 transition-colors min-h-[44px] min-w-[44px]">
                            <span class="text-xs font-bold">?</span>
                        </button>
                        <div x-show="showTooltip" x-cloak @click.outside="showTooltip = false"
                             class="absolute right-0 top-full mt-2 w-72 bg-slate-800 text-white text-xs rounded-lg p-3 shadow-lg z-10 leading-relaxed">
                            {{ __('game.reputation_help') }}
                        </div>
                    </div>
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
                            $compAccent = match(true) {
                                $comp->scope === 'continental' => ['border' => 'border-t-blue-600', 'label' => __('game.competition_role_continental')],
                                $comp->type === 'cup' => ['border' => 'border-t-emerald-500', 'label' => __('game.competition_role_cup')],
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
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-20">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">{{ __('finances.season_budget', ['season' => $game->formatted_season]) }}</h2>
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
