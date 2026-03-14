@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div x-data="academyEvaluation()" class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('squad.academy_evaluation') }}</h2>
            <p class="text-xs text-text-muted mt-0.5">{{ __('squad.academy_explanation') }}</p>
        </div>

        {{-- Context strip --}}
        <div class="flex flex-wrap gap-3 mb-6">
            {{-- Capacity --}}
            <div class="flex items-center gap-2.5 bg-surface-700/50 border border-border-default rounded-lg px-3 py-2">
                <span class="text-[10px] text-text-muted uppercase tracking-widest font-semibold">{{ __('squad.academy_capacity') }}</span>
                <span class="font-heading text-sm font-bold" :class="seatsUsed > {{ $capacity }} ? 'text-accent-red' : 'text-text-primary'">
                    <span x-text="seatsUsed"></span>/{{ $capacity }}
                </span>
                <div class="w-12 h-1.5 bg-bar-track rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-300"
                         :class="seatsUsed > {{ $capacity }} ? 'bg-accent-red' : (seatsUsed >= {{ $capacity }} - 1 ? 'bg-accent-gold' : 'bg-emerald-500')"
                         :style="'width: ' + Math.min(100, (seatsUsed / {{ max($capacity, 1) }}) * 100) + '%'">
                    </div>
                </div>
            </div>

            @if($loanedCount > 0)
                <div class="flex items-center gap-2 bg-accent-blue/10 border border-accent-blue/20 rounded-lg px-3 py-2">
                    <svg class="w-4 h-4 text-accent-blue shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 17l-4 4m0 0l-4-4m4 4V3"/></svg>
                    <span class="text-sm text-accent-blue">{{ trans_choice('squad.academy_returning_loans', $loanedCount, ['count' => $loanedCount]) }}</span>
                </div>
            @endif

            @if($arrivalsRange['max'] > 0)
                <div class="flex items-center gap-2 bg-lime-500/10 border border-lime-500/20 rounded-lg px-3 py-2">
                    <svg class="w-4 h-4 text-lime-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="text-sm text-lime-500">{{ __('squad.academy_incoming', ['min' => $arrivalsRange['min'], 'max' => $arrivalsRange['max']]) }}</span>
                </div>
            @endif

            @if($occupiedSeats > $capacity && $capacity > 0)
                <div class="flex items-center gap-2 bg-accent-red/10 border border-accent-red/20 rounded-lg px-3 py-2">
                    <svg class="w-4 h-4 text-accent-red shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <span class="text-sm text-accent-red">{{ __('squad.academy_over_capacity') }}</span>
                </div>
            @endif
        </div>

        {{-- Action legend --}}
        <div class="mb-6 bg-surface-800 border border-border-default rounded-xl overflow-hidden">
            <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="flex items-start gap-2.5">
                    <span class="shrink-0 mt-0.5 w-5 h-5 rounded-sm bg-emerald-600 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </span>
                    <div>
                        <span class="text-sm font-medium text-text-primary">{{ __('squad.academy_keep') }}</span>
                        <p class="text-xs text-text-muted">{{ __('squad.academy_keep_desc') }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <span class="shrink-0 mt-0.5 w-5 h-5 rounded-sm bg-accent-blue flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    </span>
                    <div>
                        <span class="text-sm font-medium text-text-primary">{{ __('squad.academy_promote') }}</span>
                        <p class="text-xs text-text-muted">{{ __('squad.academy_promote_desc') }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <span class="shrink-0 mt-0.5 w-5 h-5 rounded-sm bg-indigo-600 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </span>
                    <div>
                        <span class="text-sm font-medium text-text-primary">{{ __('squad.academy_loan_out') }}</span>
                        <p class="text-xs text-text-muted">{{ __('squad.academy_loan_desc') }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <span class="shrink-0 mt-0.5 w-5 h-5 rounded-sm bg-red-600 flex items-center justify-center">
                        <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </span>
                    <div>
                        <span class="text-sm font-medium text-text-primary">{{ __('squad.academy_dismiss') }}</span>
                        <p class="text-xs text-text-muted">{{ __('squad.academy_dismiss_desc') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Evaluation form --}}
        <form method="POST" action="{{ route('game.squad.academy.evaluate.submit', $game->id) }}">
            @csrf

            <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden">
                {{-- Table header (desktop) --}}
                <div class="hidden md:grid grid-cols-[40px_1fr_48px_48px_48px_56px_56px_240px] gap-1.5 items-center px-4 py-2 bg-surface-700/30 border-b border-border-default text-[10px] text-text-muted uppercase tracking-widest font-semibold font-heading">
                    <span></span>
                    <span>{{ __('app.name') }}</span>
                    <span class="text-center">{{ __('app.age') }}</span>
                    <span class="text-center">{{ __('squad.technical') }}</span>
                    <span class="text-center">{{ __('squad.physical') }}</span>
                    <span class="text-center">{{ __('squad.pot') }}</span>
                    <span class="text-center">{{ __('squad.overall') }}</span>
                    <span class="text-center">{{ __('squad.academy_evaluation') }}</span>
                </div>

                @foreach($players as $prospect)
                    @php
                        $mustDecide = \App\Modules\Academy\Services\YouthAcademyService::mustDecide($prospect);
                        $playerReveal = $prospect->seasons_in_academy > 1 ? 2 : $revealPhase;
                    @endphp
                    <div class="{{ $mustDecide ? 'bg-accent-gold/10' : '' }}">
                        <input type="hidden" name="decisions[{{ $prospect->id }}]" :value="decisions['{{ $prospect->id }}'] || ''" />

                        {{-- Desktop row --}}
                        <div class="hidden md:grid grid-cols-[40px_1fr_48px_48px_48px_56px_56px_240px] gap-1.5 items-center px-4 py-2.5 border-b border-border-default">
                            {{-- Position --}}
                            <div class="flex justify-center">
                                <x-position-badge :position="$prospect->position" size="sm" :tooltip="\App\Support\PositionMapper::toDisplayName($prospect->position)" class="cursor-help" />
                            </div>

                            {{-- Name --}}
                            <div class="flex items-center gap-2 min-w-0 cursor-pointer" @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')">
                                @if($prospect->nationality_flag)
                                    <img src="/flags/{{ $prospect->nationality_flag['code'] }}.svg" class="w-4 h-3 rounded-sm shadow-xs shrink-0" title="{{ $prospect->nationality_flag['name'] }}">
                                @endif
                                <span class="text-sm font-medium text-text-primary truncate">{{ $prospect->name }}</span>
                                <span class="text-xs text-text-secondary shrink-0">{{ trans_choice('squad.academy_seasons', $prospect->seasons_in_academy, ['count' => $prospect->seasons_in_academy]) }}</span>
                                @if($mustDecide)
                                    <span class="text-[10px] font-semibold text-accent-gold bg-accent-gold/10 px-1.5 py-0.5 rounded-sm shrink-0">{{ __('squad.academy_must_decide') }}</span>
                                @endif
                            </div>

                            {{-- Age --}}
                            <span class="text-xs text-text-secondary text-center tabular-nums">{{ $prospect->age }}</span>

                            {{-- Technical --}}
                            <div class="flex justify-center">
                                @if($playerReveal >= 1)
                                    <span class="text-xs font-medium tabular-nums @if($prospect->technical_ability >= 80) text-accent-green @elseif($prospect->technical_ability >= 70) text-lime-500 @elseif($prospect->technical_ability >= 60) text-text-body @else text-text-secondary @endif">{{ $prospect->technical_ability }}</span>
                                @else
                                    <span class="text-xs text-text-body">?</span>
                                @endif
                            </div>

                            {{-- Physical --}}
                            <div class="flex justify-center">
                                @if($playerReveal >= 1)
                                    <span class="text-xs font-medium tabular-nums @if($prospect->physical_ability >= 80) text-accent-green @elseif($prospect->physical_ability >= 70) text-lime-500 @elseif($prospect->physical_ability >= 60) text-text-body @else text-text-secondary @endif">{{ $prospect->physical_ability }}</span>
                                @else
                                    <span class="text-xs text-text-body">?</span>
                                @endif
                            </div>

                            {{-- Potential --}}
                            <span class="text-xs text-center tabular-nums {{ $playerReveal >= 2 ? 'text-text-muted' : 'text-text-body' }}">
                                {{ $playerReveal >= 2 ? $prospect->potential_range : '?' }}
                            </span>

                            {{-- Overall --}}
                            <div class="flex justify-center">
                                @if($playerReveal >= 1)
                                    <x-rating-badge :value="$prospect->overall" size="sm" />
                                @else
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-[11px] font-semibold bg-surface-600 text-text-secondary">?</span>
                                @endif
                            </div>

                            {{-- Decision buttons --}}
                            <div class="flex justify-center gap-1">
                                @unless($mustDecide)
                                    <x-pill-button size="xs" type="button"
                                        @click="setDecision('{{ $prospect->id }}', 'keep')"
                                        x-bind:class="decisions['{{ $prospect->id }}'] === 'keep' ? 'bg-emerald-600 text-white ring-2 ring-emerald-300' : 'bg-surface-700 text-text-secondary hover:bg-accent-green/10 hover:text-accent-green'"
                                        class="rounded-md min-h-[36px]">
                                        {{ __('squad.academy_keep') }}
                                    </x-pill-button>
                                @endunless

                                <x-pill-button size="xs" type="button"
                                    @click="setDecision('{{ $prospect->id }}', 'promote')"
                                    x-bind:class="decisions['{{ $prospect->id }}'] === 'promote' ? 'bg-accent-blue text-white ring-2 ring-sky-300' : 'bg-surface-700 text-text-secondary hover:bg-accent-blue/10 hover:text-accent-blue'"
                                    class="rounded-md min-h-[36px]">
                                    {{ __('squad.academy_promote') }}
                                </x-pill-button>

                                @unless($mustDecide)
                                    <x-pill-button size="xs" type="button"
                                        @click="setDecision('{{ $prospect->id }}', 'loan')"
                                        x-bind:class="decisions['{{ $prospect->id }}'] === 'loan' ? 'bg-indigo-600 text-white ring-2 ring-indigo-300' : 'bg-surface-700 text-text-secondary hover:bg-indigo-500/10 hover:text-indigo-400'"
                                        class="rounded-md min-h-[36px]">
                                        {{ __('squad.academy_loan_out') }}
                                    </x-pill-button>
                                @endunless

                                <x-pill-button size="xs" type="button"
                                    @click="setDecision('{{ $prospect->id }}', 'dismiss')"
                                    x-bind:class="decisions['{{ $prospect->id }}'] === 'dismiss' ? 'bg-red-600 text-white ring-2 ring-red-300' : 'bg-surface-700 text-text-secondary hover:bg-accent-red/10 hover:text-accent-red'"
                                    class="rounded-md min-h-[36px]">
                                    {{ __('squad.academy_dismiss') }}
                                </x-pill-button>
                            </div>
                        </div>

                        {{-- Mobile card --}}
                        <div class="md:hidden px-4 py-3 border-b border-border-default">
                            <div class="flex items-center gap-3 mb-3">
                                <x-player-avatar
                                    :name="$prospect->name"
                                    :position-group="\App\Support\PositionMapper::getPositionGroup($prospect->position)"
                                    :position-abbrev="\App\Support\PositionMapper::toAbbreviation($prospect->position)"
                                    @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')"
                                    class="cursor-pointer"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-text-primary truncate">{{ $prospect->name }}</span>
                                        <span class="text-[10px] text-text-faint">{{ $prospect->age }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-text-secondary">{{ trans_choice('squad.academy_seasons', $prospect->seasons_in_academy, ['count' => $prospect->seasons_in_academy]) }}</span>
                                        @if($mustDecide)
                                            <span class="text-[10px] font-semibold text-accent-gold bg-accent-gold/10 px-1.5 py-0.5 rounded-sm">{{ __('squad.academy_must_decide') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if($playerReveal >= 1)
                                    <x-rating-badge :value="$prospect->overall" class="shrink-0" />
                                @else
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-semibold bg-surface-600 text-text-secondary">?</span>
                                @endif
                            </div>

                            <div class="flex gap-1.5">
                                @unless($mustDecide)
                                    <x-pill-button size="sm" @click="setDecision('{{ $prospect->id }}', 'keep')"
                                        x-bind:class="decisions['{{ $prospect->id }}'] === 'keep' ? 'bg-emerald-600 text-white ring-2 ring-emerald-300' : 'bg-surface-700 text-text-secondary'"
                                        class="flex-1 min-h-[44px]">
                                        {{ __('squad.academy_keep') }}
                                    </x-pill-button>
                                @endunless

                                <x-pill-button size="sm" @click="setDecision('{{ $prospect->id }}', 'promote')"
                                    x-bind:class="decisions['{{ $prospect->id }}'] === 'promote' ? 'bg-accent-blue text-white ring-2 ring-sky-300' : 'bg-surface-700 text-text-secondary'"
                                    class="flex-1 min-h-[44px]">
                                    {{ __('squad.academy_promote') }}
                                </x-pill-button>

                                @unless($mustDecide)
                                    <x-pill-button size="sm" @click="setDecision('{{ $prospect->id }}', 'loan')"
                                        x-bind:class="decisions['{{ $prospect->id }}'] === 'loan' ? 'bg-indigo-600 text-white ring-2 ring-indigo-300' : 'bg-surface-700 text-text-secondary'"
                                        class="flex-1 min-h-[44px]">
                                        {{ __('squad.academy_loan_out') }}
                                    </x-pill-button>
                                @endunless

                                <x-pill-button size="sm" @click="setDecision('{{ $prospect->id }}', 'dismiss')"
                                    x-bind:class="decisions['{{ $prospect->id }}'] === 'dismiss' ? 'bg-red-600 text-white ring-2 ring-red-300' : 'bg-surface-700 text-text-secondary'"
                                    class="flex-1 min-h-[44px]">
                                    {{ __('squad.academy_dismiss') }}
                                </x-pill-button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Submit --}}
            <div class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <p class="text-xs text-text-secondary" x-show="!allDecided">
                    {{ __('messages.academy_evaluation_required') }}
                </p>
                <div class="text-xs text-accent-green font-medium" x-show="allDecided" x-cloak>
                    &#10003; {{ __('messages.academy_evaluation_complete') }}
                </div>
                <x-primary-button color="red" x-bind:disabled="!allDecided" class="w-full md:w-auto">
                    {{ __('app.confirm') }}
                </x-primary-button>
            </div>
        </form>
    </div>

    <script>
        function academyEvaluation() {
            return {
                decisions: {},
                playerCount: {{ $players->count() }},

                get seatsUsed() {
                    let kept = 0;
                    for (const [id, decision] of Object.entries(this.decisions)) {
                        if (decision === 'keep') kept++;
                    }
                    // Players without a decision yet count as kept
                    const undecided = this.playerCount - Object.values(this.decisions).filter(v => v).length;
                    return kept + undecided;
                },

                get allDecided() {
                    const decided = Object.values(this.decisions).filter(v => v).length;
                    return decided === this.playerCount;
                },

                setDecision(playerId, action) {
                    if (this.decisions[playerId] === action) {
                        delete this.decisions[playerId];
                    } else {
                        this.decisions[playerId] = action;
                    }
                }
            }
        }
    </script>

    <x-player-detail-modal />
</x-app-layout>
