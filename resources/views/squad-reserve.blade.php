@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Sub-navigation --}}
        <x-section-nav :items="[
            ['href' => route('game.squad', $game->id), 'label' => __('squad.first_team'), 'active' => false],
            ['href' => route('game.squad.planner', $game->id), 'label' => __('planner.planner'), 'active' => false],
            ['href' => route('game.squad.reserve', $game->id), 'label' => __('squad.reserve_team'), 'active' => true],
            ['href' => route('game.squad.registration', $game->id), 'label' => __('squad.registration'), 'active' => false],
        ]" />

        {{-- Flash Messages --}}
        <x-flash-message type="success" :message="session('success')" class="mt-4" />
        <x-flash-message type="error" :message="session('error')" class="mt-4" />

        {{-- Page Title --}}
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ $reserveTeam->name }}</h2>
        </div>

        {{-- Summary strip --}}
        <x-help-disclosure class="mb-6">
            <x-slot name="trigger">
                <div class="flex items-center gap-2.5 overflow-x-auto scrollbar-hide pb-1">
                    <x-summary-card :label="__('squad.squad_size')" :value="$reserveCount" />
                    <x-summary-card :label="__('squad.avg_age')" :value="$avgAge" />
                    <x-summary-card :label="__('squad.avg_ovr')" :value="$avgOverall" x-data x-tooltip.raw="{{ __('squad.tooltip_avg_overall') }}" :value-class="$avgOverall >= 75 ? 'text-accent-green' : ($avgOverall >= 65 ? 'text-text-primary' : 'text-amber-500')" />
                    <div class="ml-auto shrink-0">
                        <x-help-toggle :label="__('squad.reserve_help_toggle')" />
                    </div>
                </div>
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <p class="text-text-secondary mb-3">{{ __('squad.reserve_help_development') }}</p>
                    <p class="text-text-secondary">{{ __('squad.reserve_help_age_rule') }}</p>
                </div>

                <div>
                    <p class="font-semibold text-text-body mb-2">{{ __('squad.reserve_help_actions_title') }}</p>
                    <ul class="space-y-2">
                        <li class="flex gap-2">
                            <span class="text-accent-green shrink-0">↑</span>
                            <span class="text-text-secondary">{{ __('squad.reserve_help_call_up') }}</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="text-amber-400 shrink-0">↓</span>
                            <span class="text-text-secondary">{{ __('squad.reserve_help_send_back') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </x-help-disclosure>

        @if($reserveCount === 0)
            <div class="text-center py-16">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-surface-700 rounded-full mb-4">
                    <svg class="w-8 h-8 fill-surface-600" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M48 195.8l209.2 86.1c9.8 4 20.2 6.1 30.8 6.1s21-2.1 30.8-6.1l242.4-99.8c9-3.7 14.8-12.4 14.8-22.1s-5.8-18.4-14.8-22.1L318.8 38.1C309 34.1 298.6 32 288 32s-21 2.1-30.8 6.1L14.8 137.9C5.8 141.6 0 150.3 0 160L0 456c0 13.3 10.7 24 24 24s24-10.7 24-24l0-260.2zm48 71.7L96 384c0 53 86 96 192 96s192-43 192-96l0-116.6-142.9 58.9c-15.6 6.4-32.2 9.7-49.1 9.7s-33.5-3.3-49.1-9.7L96 267.4z"/></svg>
                </div>
                <p class="text-text-muted text-sm">{{ __('squad.no_reserve_players') }}</p>
            </div>
        @else
            <div x-data class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                {{-- Table header --}}
                <div class="hidden md:block">
                    <div class="grid grid-cols-[40px_1fr_48px_56px_56px_56px] gap-1.5 items-center px-4 py-2 bg-surface-700/30 border-b border-border-default text-[10px] text-text-muted uppercase tracking-widest font-semibold">
                        <span></span>
                        <span>{{ __('app.name') }}</span>
                        <span class="text-center">{{ __('app.age') }}</span>
                        <span class="text-center">{{ __('app.contract') }}</span>
                        <span class="text-center">{{ __('squad.pot') }}</span>
                        <span class="text-center">{{ __('squad.overall_short') }}</span>
                    </div>
                </div>

                @foreach([
                    ['name' => __('squad.goalkeepers'), 'players' => $goalkeepers],
                    ['name' => __('squad.defenders'), 'players' => $defenders],
                    ['name' => __('squad.midfielders'), 'players' => $midfielders],
                    ['name' => __('squad.forwards'), 'players' => $forwards],
                ] as $group)
                    @if($group['players']->isNotEmpty())
                        <div class="px-4 py-2 bg-surface-700/30 border-b border-border-default">
                            <div class="flex items-center justify-between">
                                <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">{{ $group['name'] }}</span>
                                <span class="text-[10px] text-text-faint">{{ $group['players']->count() }}</span>
                            </div>
                        </div>

                        @foreach($group['players'] as $player)
                            @php
                                $isCalledUp = $player->team_id === $game->team_id;
                                $age = $player->age($game->current_date);
                            @endphp

                            {{-- Mobile row --}}
                            <div class="md:hidden px-4 py-3 border-b border-border-default {{ $isCalledUp ? 'opacity-60' : '' }}">
                                <div class="flex items-center gap-3">
                                    <div class="cursor-pointer flex-1 flex items-center gap-3 min-w-0" @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $player->id]) }}')">
                                        <x-player-avatar :name="$player->name" :position-group="\App\Support\PositionMapper::getPositionGroup($player->position)" :position-abbrev="\App\Support\PositionMapper::toAbbreviation($player->position)" />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-text-primary truncate">{{ $player->name }}</span>
                                                <x-origin-badge :player="$player" :current-season="$game->season" />
                                                <span class="text-[10px] text-text-faint">{{ $age }}</span>
                                                @if($player->contract_expiry_year)
                                                    <span class="text-[10px] tabular-nums @if($player->isContractExpiring($game->getSeasonEndDate())) text-accent-red font-medium @else text-text-faint @endif">{{ __('app.contract') }}: {{ $player->contract_expiry_year }}</span>
                                                @endif
                                                @if($isCalledUp)
                                                    <span class="text-[10px] font-semibold bg-accent-blue/10 text-accent-blue px-1.5 py-0.5 rounded-full">{{ __('squad.called_up_indicator') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <x-rating-badge :value="$player->effective_rating" class="shrink-0" />
                                    </div>
                                    @if($isCalledUp)
                                        <form method="POST" action="{{ route('game.reserve.send-back', [$game->id, $player->id]) }}" onsubmit="return confirm('{{ __('squad.send_back_to_reserve') }}?')">
                                            @csrf
                                            <button type="submit" class="text-amber-400 hover:text-amber-300 px-2" title="{{ __('squad.send_back_to_reserve') }}">↓</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('game.reserve.call-up', [$game->id, $player->id]) }}" onsubmit="return confirm('{{ __('squad.call_up_to_first_team') }}?')">
                                            @csrf
                                            <button type="submit" class="text-accent-green hover:text-emerald-400 px-2" title="{{ __('squad.call_up_to_first_team') }}">↑</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            {{-- Desktop row --}}
                            <div class="hidden md:grid grid-cols-[40px_1fr_48px_56px_56px_56px] gap-1.5 items-center px-4 py-2.5 border-b border-border-default hover:bg-surface-700/30 transition-colors cursor-pointer {{ $isCalledUp ? 'opacity-60' : '' }}"
                                 @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $player->id]) }}')">
                                <div class="flex justify-center">
                                    <x-position-badge :position="$player->position" size="sm" :tooltip="\App\Support\PositionMapper::toDisplayName($player->position)" class="cursor-help" />
                                </div>
                                <div class="flex items-center gap-2 min-w-0">
                                    @if($player->nationality_flag)
                                        <img src="{{ Storage::disk('assets')->url('flags/' . $player->nationality_flag['code'] . '.svg') }}" class="w-4 h-3 rounded-xs shadow-xs shrink-0" title="{{ $player->nationality_flag['name'] }}">
                                    @endif
                                    <span class="text-sm font-medium text-text-primary truncate">{{ $player->name }}</span>
                                    <x-origin-badge :player="$player" :current-season="$game->season" />
                                    @if($isCalledUp)
                                        <span class="text-[10px] font-semibold bg-accent-blue/10 text-accent-blue px-1.5 py-0.5 rounded-full">{{ __('squad.called_up_indicator') }}</span>
                                    @endif
                                </div>
                                <span class="text-xs text-text-secondary text-center tabular-nums">{{ $age }}</span>
                                <span class="text-[11px] text-center tabular-nums @if($player->isContractExpiring($game->getSeasonEndDate())) text-accent-red font-medium @else text-text-muted @endif">
                                    {{ $player->contract_expiry_year ?? '—' }}
                                </span>
                                <span class="text-xs text-center tabular-nums text-text-muted">{{ $player->potential_range }}</span>
                                <div class="flex justify-center">
                                    <x-rating-badge :value="$player->effective_rating" size="sm" />
                                </div>
                            </div>
                        @endforeach
                    @endif
                @endforeach
            </div>
        @endif

    </div>

    <x-player-detail-modal />
    <x-negotiation-chat-modal />
    <x-wage-cap-modal :game="$game" />
</x-app-layout>
