@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Sub-navigation --}}
        <x-section-nav :items="[
            ['href' => route('game.squad', $game->id), 'label' => __('squad.first_team'), 'active' => false],
            ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => true],
        ]" />

        {{-- Flash Messages --}}
        <x-flash-message type="success" :message="session('success')" class="mt-4" />
        <x-flash-message type="error" :message="session('error')" class="mt-4" />

        {{-- Page Title --}}
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('squad.academy') }}</h2>
        </div>

        {{-- Summary strip --}}
        <div x-data="{ open: false }" class="mb-6">
            <div class="flex items-center gap-2.5 overflow-x-auto scrollbar-hide pb-1">
                <x-summary-card :label="__('squad.academy_tier')" :value="$tierDescription" :value-class="$tier >= 3 ? 'text-accent-green' : ($tier >= 1 ? 'text-accent-blue' : 'text-text-secondary')" />
                <x-summary-card :label="__('squad.academy_players')" :value="$academyCount" />
                <div class="ml-auto shrink-0">
                    <x-ghost-button color="slate" @click="open = !open" class="gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-text-secondary shrink-0">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ __('squad.academy_help_toggle') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </x-ghost-button>
                </div>
            </div>

            <div x-show="open" x-transition class="mt-3 bg-surface-700/50 border border-border-default rounded-lg p-4 text-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {{-- Overview --}}
                    <div>
                        <p class="text-text-secondary mb-3">{{ __('squad.academy_help_development') }}</p>
                        <p class="text-text-secondary">{{ __('squad.academy_help_age_rule') }}</p>
                    </div>

                    {{-- Actions --}}
                    <div>
                        <p class="font-semibold text-text-body mb-2">{{ __('squad.academy_help_actions_title') }}</p>
                        <ul class="space-y-2">
                            <li class="flex gap-2">
                                <span class="text-accent-green shrink-0">↑</span>
                                <span class="text-text-secondary">{{ __('squad.academy_help_promote') }}</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="text-indigo-400 shrink-0">⇄</span>
                                <span class="text-text-secondary">{{ __('squad.academy_help_loan') }}</span>
                            </li>
                            <li class="flex gap-2">
                                <span class="text-accent-red shrink-0">✕</span>
                                <span class="text-text-secondary">{{ __('squad.academy_help_dismiss') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        @if($academyCount === 0 && $loanedPlayers->isEmpty())
            <div class="text-center py-16">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-surface-700 rounded-full mb-4">
                    <svg class="w-8 h-8 fill-surface-600" stroke="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M48 195.8l209.2 86.1c9.8 4 20.2 6.1 30.8 6.1s21-2.1 30.8-6.1l242.4-99.8c9-3.7 14.8-12.4 14.8-22.1s-5.8-18.4-14.8-22.1L318.8 38.1C309 34.1 298.6 32 288 32s-21 2.1-30.8 6.1L14.8 137.9C5.8 141.6 0 150.3 0 160L0 456c0 13.3 10.7 24 24 24s24-10.7 24-24l0-260.2zm48 71.7L96 384c0 53 86 96 192 96s192-43 192-96l0-116.6-142.9 58.9c-15.6 6.4-32.2 9.7-49.1 9.7s-33.5-3.3-49.1-9.7L96 267.4z"/></svg>
                </div>
                <p class="text-text-muted text-sm">{{ __('squad.no_academy_prospects') }}</p>
                <p class="text-text-secondary text-xs mt-2">{{ __('squad.academy_explanation') }}</p>
            </div>
        @else
            {{-- Active academy players --}}
            @if($academyCount > 0)
                <div x-data class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                    {{-- Table header --}}
                    <div class="hidden md:block">
                        <div class="grid grid-cols-[40px_1fr_48px_48px_48px_56px_56px] gap-1.5 items-center px-4 py-2 bg-surface-700/30 border-b border-border-default text-[10px] text-text-muted uppercase tracking-widest font-semibold">
                            <span></span>
                            <span>{{ __('app.name') }}</span>
                            <span class="text-center">{{ __('app.age') }}</span>
                            <span class="text-center">{{ __('squad.technical') }}</span>
                            <span class="text-center">{{ __('squad.physical') }}</span>
                            <span class="text-center">{{ __('squad.pot') }}</span>
                            <span class="text-center">{{ __('squad.overall') }}</span>
                        </div>
                    </div>

                    @foreach([
                        ['name' => __('squad.goalkeepers'), 'players' => $goalkeepers],
                        ['name' => __('squad.defenders'), 'players' => $defenders],
                        ['name' => __('squad.midfielders'), 'players' => $midfielders],
                        ['name' => __('squad.forwards'), 'players' => $forwards],
                    ] as $group)
                        @if($group['players']->isNotEmpty())
                            {{-- Position group header --}}
                            <div class="px-4 py-2 bg-surface-700/30 border-b border-border-default">
                                <div class="flex items-center justify-between">
                                    <span class="font-heading text-[11px] font-semibold uppercase tracking-widest text-text-muted">{{ $group['name'] }}</span>
                                    <span class="text-[10px] text-text-faint">{{ $group['players']->count() }}</span>
                                </div>
                            </div>

                            @foreach($group['players'] as $prospect)
                                {{-- Mobile row --}}
                                <div class="md:hidden px-4 py-3 border-b border-border-default cursor-pointer hover:bg-surface-700/30 transition-colors" @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')">
                                    <div class="flex items-center gap-3">
                                        <x-player-avatar :name="$prospect->name" :position-group="\App\Support\PositionMapper::getPositionGroup($prospect->position)" :position-abbrev="\App\Support\PositionMapper::toAbbreviation($prospect->position)" />
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium text-text-primary truncate">{{ $prospect->name }}</span>
                                                <span class="text-[10px] text-text-faint">{{ $prospect->age }}</span>
                                            </div>
                                        </div>
                                        <x-rating-badge :value="$prospect->overall" class="shrink-0" />
                                    </div>
                                </div>

                                {{-- Desktop row --}}
                                <div class="hidden md:grid grid-cols-[40px_1fr_48px_48px_48px_56px_56px] gap-1.5 items-center px-4 py-2.5 border-b border-border-default hover:bg-surface-700/30 transition-colors cursor-pointer" @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')">
                                    {{-- Position --}}
                                    <div class="flex justify-center">
                                        <x-position-badge :position="$prospect->position" size="sm" :tooltip="\App\Support\PositionMapper::toDisplayName($prospect->position)" class="cursor-help" />
                                    </div>
                                    {{-- Name --}}
                                    <div class="flex items-center gap-2 min-w-0">
                                        @if($prospect->nationality_flag)
                                            <img src="{{ Storage::disk('assets')->url('flags/' . $prospect->nationality_flag['code'] . '.svg') }}" class="w-4 h-3 rounded-sm shadow-xs shrink-0" title="{{ $prospect->nationality_flag['name'] }}">
                                        @endif
                                        <span class="text-sm font-medium text-text-primary truncate">{{ $prospect->name }}</span>
                                    </div>
                                    {{-- Age --}}
                                    <span class="text-xs text-text-secondary text-center tabular-nums">{{ $prospect->age }}</span>
                                    {{-- Technical --}}
                                    <div class="flex justify-center">
                                        <span class="text-xs font-medium tabular-nums @if($prospect->technical_ability >= 80) text-accent-green @elseif($prospect->technical_ability >= 70) text-lime-500 @elseif($prospect->technical_ability >= 60) text-text-body @else text-text-secondary @endif">{{ $prospect->technical_ability }}</span>
                                    </div>
                                    {{-- Physical --}}
                                    <div class="flex justify-center">
                                        <span class="text-xs font-medium tabular-nums @if($prospect->physical_ability >= 80) text-accent-green @elseif($prospect->physical_ability >= 70) text-lime-500 @elseif($prospect->physical_ability >= 60) text-text-body @else text-text-secondary @endif">{{ $prospect->physical_ability }}</span>
                                    </div>
                                    {{-- Potential range --}}
                                    <span class="text-xs text-center tabular-nums text-text-muted">{{ $prospect->potential_range }}</span>
                                    {{-- Overall --}}
                                    <div class="flex justify-center">
                                        <x-rating-badge :value="$prospect->overall" size="sm" />
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- Loaned players section --}}
            @if($loanedPlayers->isNotEmpty())
                <div x-data class="mt-6">
                    <x-section-card :title="__('squad.academy_on_loan') . ' (' . $loanedPlayers->count() . ')'">
                        <div class="divide-y divide-border-default">
                            @foreach($loanedPlayers as $prospect)
                                <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-surface-700/30 transition-colors"
                                     @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')"
                                >
                                    <x-player-avatar :name="$prospect->name" :position-group="\App\Support\PositionMapper::getPositionGroup($prospect->position)" :position-abbrev="\App\Support\PositionMapper::toAbbreviation($prospect->position)" size="sm" />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-text-primary truncate">{{ $prospect->name }}</span>
                                            <span class="text-[10px] font-semibold bg-violet-500/10 text-violet-400 px-1.5 py-0.5 rounded-full">{{ __('squad.academy_on_loan') }}</span>
                                        </div>
                                    </div>
                                    @if($prospect->nationality_flag)
                                        <img src="{{ Storage::disk('assets')->url('flags/' . $prospect->nationality_flag['code'] . '.svg') }}" class="w-5 h-4 rounded-sm shadow-xs shrink-0 hidden md:block" title="{{ $prospect->nationality_flag['name'] }}">
                                    @endif
                                    <span class="text-xs text-text-secondary hidden md:block">{{ $prospect->age }}</span>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-semibold bg-surface-600 text-text-secondary shrink-0">—</span>
                                </div>
                            @endforeach
                        </div>
                    </x-section-card>
                </div>
            @endif
        @endif

    </div>

    <x-player-detail-modal />
</x-app-layout>
