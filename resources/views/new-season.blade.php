@php
/** @var App\Models\Game $game */
/** @var int $availableSurplus */
/** @var string $reputationLevel */
/** @var string|null $seasonGoalLabel */
/** @var int|null $seasonGoalTarget */
/** @var array $squad */
/** @var string|null $stadiumName */
/** @var int $stadiumCapacity */
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen py-12 md:py-16">
        <div class="max-w-2xl mx-auto px-4 sm:px-6">

            {{-- Hero --}}
            <div class="text-center mb-8">
                <x-team-crest :team="$game->team" class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-4 drop-shadow-lg" />
                <p class="text-xs font-semibold text-text-muted uppercase tracking-widest mb-1">{{ __('game.season_n', ['season' => $game->formatted_season]) }}</p>
                <h1 class="font-heading text-3xl md:text-4xl font-bold text-text-primary">{{ $game->team->name }}</h1>
            </div>

            <x-flash-message type="error" :message="session('error')" class="mb-6" />

            {{-- Season objective (accent border card) --}}
            <div class="border-l-4 border-l-accent-gold bg-surface-800 border border-border-default rounded-r-xl pl-5 pr-4 py-3.5 mb-6">
                <span class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">{{ __('game.season_objective') }}</span>
                <div class="font-heading text-lg md:text-xl font-bold text-text-primary mt-1">{{ __($seasonGoalLabel ?? 'game.goal_top_half') }}</div>
                <div class="text-sm text-text-secondary mt-0.5">{{ __('game.board_expects_position', ['position' => $seasonGoalTarget ?? 10]) }}</div>
            </div>

            {{-- Team in numbers --}}
            <x-section-card :title="__('season.team_in_numbers')" class="mb-8">
                <div class="p-5">
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <x-summary-card :label="__('game.club_reputation')" :value="__('finances.reputation.' . $reputationLevel)" />
                        <x-summary-card
                            :label="__('game.stadium')"
                            :value="$stadiumName ?? '—'"
                            :caption="$stadiumCapacity > 0 ? __('game.seats', ['count' => number_format($stadiumCapacity)]) : null" />
                        <x-summary-card :label="__('finances.total_budget')" :value="\App\Support\Money::format($availableSurplus)" value-class="text-accent-green" />
                        <x-summary-card :label="__('game.avg_overall')" :value="$squad['avg_overall']" />
                        <x-summary-card :label="__('game.avg_age')" :value="number_format($squad['avg_age'], 1)" />
                        <x-summary-card :label="__('game.squad_size')" :value="$squad['total_players']" :caption="__('game.players')" />
                    </div>
                </div>
            </x-section-card>

            {{-- Begin --}}
            <form action="{{ route('game.new-season.complete', $game->id) }}" method="POST">
                @csrf
                <x-primary-button class="w-full">{{ __('game.begin_season') }}</x-primary-button>
            </form>

        </div>
    </div>
</x-app-layout>
