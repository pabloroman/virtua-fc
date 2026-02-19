@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div x-data="academyEvaluation()" class="max-w-7xl mx-auto sm:px-6 lg:px-8 pb-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4 md:p-8">
                {{-- Header --}}
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <h3 class="font-semibold text-xl text-slate-900">{{ __('squad.academy_evaluation') }}</h3>
                        <p class="text-sm text-slate-500 mt-1">
                            {{ __('squad.academy_explanation') }}
                        </p>
                    </div>

                    {{-- Capacity bar --}}
                    <div class="flex items-center gap-3 bg-slate-50 rounded-lg px-4 py-3">
                        <div class="text-sm">
                            <span class="text-slate-500">{{ __('squad.academy_capacity') }}:</span>
                            <span class="font-bold" :class="seatsUsed > {{ $capacity }} ? 'text-red-600' : 'text-slate-900'">
                                <span x-text="seatsUsed"></span>/{{ $capacity }}
                            </span>
                        </div>
                        <div class="w-24 h-2 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-300"
                                 :class="seatsUsed > {{ $capacity }} ? 'bg-red-500' : (seatsUsed >= {{ $capacity }} - 1 ? 'bg-amber-500' : 'bg-emerald-500')"
                                 :style="'width: ' + Math.min(100, (seatsUsed / {{ max($capacity, 1) }}) * 100) + '%'">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Info cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
                    @if($loanedCount > 0)
                        <div class="flex items-center gap-2 bg-sky-50 border border-sky-200 rounded-lg px-3 py-2">
                            <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 17l-4 4m0 0l-4-4m4 4V3"/></svg>
                            <span class="text-sm text-sky-700">{{ trans_choice('squad.academy_returning_loans', $loanedCount, ['count' => $loanedCount]) }}</span>
                        </div>
                    @endif

                    @if($arrivalsRange['max'] > 0)
                        <div class="flex items-center gap-2 bg-lime-50 border border-lime-200 rounded-lg px-3 py-2">
                            <svg class="w-4 h-4 text-lime-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span class="text-sm text-lime-700">{{ __('squad.academy_incoming', ['min' => $arrivalsRange['min'], 'max' => $arrivalsRange['max']]) }}</span>
                        </div>
                    @endif

                    @if($occupiedSeats > $capacity && $capacity > 0)
                        <div class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                            <span class="text-sm text-red-700">{{ __('squad.academy_over_capacity') }}</span>
                        </div>
                    @endif
                </div>

                {{-- Action legend --}}
                <div class="mb-6 border border-slate-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="flex items-start gap-2.5">
                            <span class="shrink-0 mt-0.5 w-5 h-5 rounded bg-emerald-600 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <div>
                                <span class="text-sm font-medium text-slate-900">{{ __('squad.academy_keep') }}</span>
                                <p class="text-xs text-slate-500">{{ __('squad.academy_keep_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="shrink-0 mt-0.5 w-5 h-5 rounded bg-sky-600 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                            </span>
                            <div>
                                <span class="text-sm font-medium text-slate-900">{{ __('squad.academy_promote') }}</span>
                                <p class="text-xs text-slate-500">{{ __('squad.academy_promote_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="shrink-0 mt-0.5 w-5 h-5 rounded bg-indigo-600 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            </span>
                            <div>
                                <span class="text-sm font-medium text-slate-900">{{ __('squad.academy_loan_out') }}</span>
                                <p class="text-xs text-slate-500">{{ __('squad.academy_loan_desc') }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="shrink-0 mt-0.5 w-5 h-5 rounded bg-red-600 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </span>
                            <div>
                                <span class="text-sm font-medium text-slate-900">{{ __('squad.academy_dismiss') }}</span>
                                <p class="text-xs text-slate-500">{{ __('squad.academy_dismiss_desc') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Evaluation form --}}
                <form method="POST" action="{{ route('game.squad.academy.evaluate.submit', $game->id) }}">
                    @csrf

                    <div class="space-y-2">
                        {{-- Table header (desktop) --}}
                        <div class="hidden md:grid md:grid-cols-12 gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wide px-3 py-2 border-b">
                            <div class="col-span-1"></div>
                            <div class="col-span-3">{{ __('app.name') }}</div>
                            <div class="col-span-1 text-center">{{ __('app.age') }}</div>
                            <div class="col-span-1 text-center">{{ __('squad.technical') }}</div>
                            <div class="col-span-1 text-center">{{ __('squad.physical') }}</div>
                            <div class="col-span-1 text-center">{{ __('squad.pot') }}</div>
                            <div class="col-span-1 text-center">{{ __('squad.overall') }}</div>
                            <div class="col-span-3 text-center">{{ __('squad.academy_evaluation') }}</div>
                        </div>

                        @foreach($players as $prospect)
                            @php
                                $mustDecide = \App\Modules\Academy\Services\YouthAcademyService::mustDecide($prospect);
                                $playerReveal = $prospect->seasons_in_academy > 1 ? 2 : $revealPhase;
                            @endphp
                            <div class="rounded-lg border border-slate-200 hover:border-slate-300 transition-colors {{ $mustDecide ? 'bg-amber-50/50 border-amber-200' : '' }}">
                                <input type="hidden" name="decisions[{{ $prospect->id }}]" :value="decisions['{{ $prospect->id }}'] || ''" />

                                {{-- Desktop row --}}
                                <div class="hidden md:grid md:grid-cols-12 gap-2 items-center px-3 py-3">
                                    {{-- Position --}}
                                    <div class="col-span-1 flex justify-center">
                                        <x-position-badge :position="$prospect->position" :tooltip="\App\Support\PositionMapper::toDisplayName($prospect->position)" class="cursor-help" />
                                    </div>

                                    {{-- Name + info --}}
                                    <div class="col-span-3">
                                        <div class="flex items-center gap-2">
                                            <button type="button" x-data @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')" class="p-1 text-slate-300 rounded hover:text-slate-400 shrink-0">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                    <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                            @if($prospect->nationality_flag)
                                                <img src="/flags/{{ $prospect->nationality_flag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm shrink-0" title="{{ $prospect->nationality_flag['name'] }}">
                                            @endif
                                            <span class="font-medium text-slate-900 truncate">{{ $prospect->name }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-slate-400">{{ trans_choice('squad.academy_seasons', $prospect->seasons_in_academy, ['count' => $prospect->seasons_in_academy]) }}</span>
                                            @if($mustDecide)
                                                <span class="text-xs font-semibold text-amber-600 bg-amber-100 px-1.5 py-0.5 rounded">{{ __('squad.academy_must_decide') }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Age --}}
                                    <div class="col-span-1 text-center text-sm">{{ $prospect->age }}</div>

                                    {{-- Technical --}}
                                    <div class="col-span-1 text-center text-sm @if($playerReveal >= 1) @if($prospect->technical_ability >= 80) text-green-600 @elseif($prospect->technical_ability >= 70) text-lime-600 @elseif($prospect->technical_ability < 60) text-slate-400 @endif @endif">
                                        {{ $playerReveal >= 1 ? $prospect->technical_ability : '?' }}
                                    </div>

                                    {{-- Physical --}}
                                    <div class="col-span-1 text-center text-sm @if($playerReveal >= 1) @if($prospect->physical_ability >= 80) text-green-600 @elseif($prospect->physical_ability >= 70) text-lime-600 @elseif($prospect->physical_ability < 60) text-slate-400 @endif @endif">
                                        {{ $playerReveal >= 1 ? $prospect->physical_ability : '?' }}
                                    </div>

                                    {{-- Potential --}}
                                    <div class="col-span-1 text-center text-xs text-slate-500">
                                        {{ $playerReveal >= 2 ? $prospect->potential_range : '?' }}
                                    </div>

                                    {{-- Overall --}}
                                    <div class="col-span-1 flex justify-center">
                                        @if($playerReveal >= 1)
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold
                                                @if($prospect->overall >= 80) bg-emerald-500 text-white
                                                @elseif($prospect->overall >= 70) bg-lime-500 text-white
                                                @elseif($prospect->overall >= 60) bg-amber-500 text-white
                                                @else bg-slate-300 text-slate-700
                                                @endif">
                                                {{ $prospect->overall }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold bg-slate-200 text-slate-400">?</span>
                                        @endif
                                    </div>

                                    {{-- Decision buttons --}}
                                    <div class="col-span-3 flex justify-center gap-1">
                                        @unless($mustDecide)
                                            <button type="button"
                                                @click="setDecision('{{ $prospect->id }}', 'keep')"
                                                :class="decisions['{{ $prospect->id }}'] === 'keep' ? 'bg-emerald-600 text-white ring-2 ring-emerald-300' : 'bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700'"
                                                class="px-2.5 py-1.5 text-xs font-medium rounded-md transition-all min-h-[36px]">
                                                {{ __('squad.academy_keep') }}
                                            </button>
                                        @endunless

                                        <button type="button"
                                            @click="setDecision('{{ $prospect->id }}', 'promote')"
                                            :class="decisions['{{ $prospect->id }}'] === 'promote' ? 'bg-sky-600 text-white ring-2 ring-sky-300' : 'bg-slate-100 text-slate-600 hover:bg-sky-50 hover:text-sky-700'"
                                            class="px-2.5 py-1.5 text-xs font-medium rounded-md transition-all min-h-[36px]">
                                            {{ __('squad.academy_promote') }}
                                        </button>

                                        @unless($mustDecide)
                                            <button type="button"
                                                @click="setDecision('{{ $prospect->id }}', 'loan')"
                                                :class="decisions['{{ $prospect->id }}'] === 'loan' ? 'bg-indigo-600 text-white ring-2 ring-indigo-300' : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-700'"
                                                class="px-2.5 py-1.5 text-xs font-medium rounded-md transition-all min-h-[36px]">
                                                {{ __('squad.academy_loan_out') }}
                                            </button>
                                        @endunless

                                        <button type="button"
                                            @click="setDecision('{{ $prospect->id }}', 'dismiss')"
                                            :class="decisions['{{ $prospect->id }}'] === 'dismiss' ? 'bg-red-600 text-white ring-2 ring-red-300' : 'bg-slate-100 text-slate-600 hover:bg-red-50 hover:text-red-700'"
                                            class="px-2.5 py-1.5 text-xs font-medium rounded-md transition-all min-h-[36px]">
                                            {{ __('squad.academy_dismiss') }}
                                        </button>
                                    </div>
                                </div>

                                {{-- Mobile card --}}
                                <div class="md:hidden p-3">
                                    <div class="flex items-center gap-2 mb-2">
                                        <button type="button" x-data @click="$dispatch('show-player-detail', '{{ route('game.academy.detail', [$game->id, $prospect->id]) }}')" class="p-1 text-slate-300 rounded hover:text-slate-400 shrink-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <x-position-badge :position="$prospect->position" :tooltip="\App\Support\PositionMapper::toDisplayName($prospect->position)" />
                                        @if($prospect->nationality_flag)
                                            <img src="/flags/{{ $prospect->nationality_flag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm shrink-0" title="{{ $prospect->nationality_flag['name'] }}">
                                        @endif
                                        <span class="font-medium text-slate-900 truncate">{{ $prospect->name }}</span>
                                        <span class="text-xs text-slate-400 shrink-0">{{ $prospect->age }} {{ __('app.age') }}</span>
                                    </div>

                                    @if($mustDecide)
                                        <div class="mb-2">
                                            <span class="text-xs font-semibold text-amber-600 bg-amber-100 px-1.5 py-0.5 rounded">{{ __('squad.academy_must_decide') }}</span>
                                        </div>
                                    @endif

                                    <div class="flex items-center gap-4 text-xs text-slate-500 mb-3">
                                        <span>{{ __('squad.technical') }}: <strong class="text-slate-700">{{ $playerReveal >= 1 ? $prospect->technical_ability : '?' }}</strong></span>
                                        <span>{{ __('squad.physical') }}: <strong class="text-slate-700">{{ $playerReveal >= 1 ? $prospect->physical_ability : '?' }}</strong></span>
                                        <span>{{ __('squad.pot') }}: <strong class="text-slate-700">{{ $playerReveal >= 2 ? $prospect->potential_range : '?' }}</strong></span>
                                        @if($playerReveal >= 1)
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                                @if($prospect->overall >= 80) bg-emerald-500 text-white
                                                @elseif($prospect->overall >= 70) bg-lime-500 text-white
                                                @elseif($prospect->overall >= 60) bg-amber-500 text-white
                                                @else bg-slate-300 text-slate-700
                                                @endif">
                                                {{ $prospect->overall }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex gap-1.5">
                                        @unless($mustDecide)
                                            <button type="button"
                                                @click="setDecision('{{ $prospect->id }}', 'keep')"
                                                :class="decisions['{{ $prospect->id }}'] === 'keep' ? 'bg-emerald-600 text-white ring-2 ring-emerald-300' : 'bg-slate-100 text-slate-600'"
                                                class="flex-1 px-2 py-2 text-xs font-medium rounded-md transition-all min-h-[44px]">
                                                {{ __('squad.academy_keep') }}
                                            </button>
                                        @endunless

                                        <button type="button"
                                            @click="setDecision('{{ $prospect->id }}', 'promote')"
                                            :class="decisions['{{ $prospect->id }}'] === 'promote' ? 'bg-sky-600 text-white ring-2 ring-sky-300' : 'bg-slate-100 text-slate-600'"
                                            class="flex-1 px-2 py-2 text-xs font-medium rounded-md transition-all min-h-[44px]">
                                            {{ __('squad.academy_promote') }}
                                        </button>

                                        @unless($mustDecide)
                                            <button type="button"
                                                @click="setDecision('{{ $prospect->id }}', 'loan')"
                                                :class="decisions['{{ $prospect->id }}'] === 'loan' ? 'bg-indigo-600 text-white ring-2 ring-indigo-300' : 'bg-slate-100 text-slate-600'"
                                                class="flex-1 px-2 py-2 text-xs font-medium rounded-md transition-all min-h-[44px]">
                                                {{ __('squad.academy_loan_out') }}
                                            </button>
                                        @endunless

                                        <button type="button"
                                            @click="setDecision('{{ $prospect->id }}', 'dismiss')"
                                            :class="decisions['{{ $prospect->id }}'] === 'dismiss' ? 'bg-red-600 text-white ring-2 ring-red-300' : 'bg-slate-100 text-slate-600'"
                                            class="flex-1 px-2 py-2 text-xs font-medium rounded-md transition-all min-h-[44px]">
                                            {{ __('squad.academy_dismiss') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Submit --}}
                    <div class="mt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <p class="text-xs text-slate-400" x-show="!allDecided">
                            {{ __('messages.academy_evaluation_required') }}
                        </p>
                        <div class="text-xs text-emerald-600 font-medium" x-show="allDecided" x-cloak>
                            &#10003; {{ __('messages.academy_evaluation_complete') }}
                        </div>
                        <button type="submit"
                                :disabled="!allDecided"
                                :class="allDecided ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                                class="w-full md:w-auto px-6 py-3 font-semibold rounded-lg transition-colors min-h-[44px]">
                            {{ __('app.confirm') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
                    return kept + undecided + {{ $loanedCount }};
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
