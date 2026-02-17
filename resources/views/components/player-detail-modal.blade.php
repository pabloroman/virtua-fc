@php
    $devLabels = [
        'growing' => __('squad.growing'),
        'peak' => __('squad.peak'),
        'declining' => __('squad.declining'),
    ];
@endphp

<div x-data="{
    player: null,
    devLabels: @js($devLabels),
    abilityColor(val) {
        if (val >= 80) return 'text-green-600';
        if (val >= 70) return 'text-lime-600';
        if (val >= 60) return 'text-amber-600';
        return 'text-slate-400';
    },
    abilityBg(val) {
        if (val >= 80) return 'bg-green-500';
        if (val >= 70) return 'bg-lime-500';
        if (val >= 60) return 'bg-amber-500';
        return 'bg-slate-400';
    },
    overallClasses(val) {
        if (val >= 80) return 'bg-emerald-500 text-white';
        if (val >= 70) return 'bg-lime-500 text-white';
        if (val >= 60) return 'bg-amber-500 text-white';
        return 'bg-slate-300 text-slate-700';
    },
    devColor(status) {
        if (status === 'growing') return 'text-green-600';
        if (status === 'peak') return 'text-sky-600';
        return 'text-orange-600';
    },
    fitnessColor(val) {
        if (val >= 90) return 'text-green-600';
        if (val >= 80) return 'text-lime-600';
        if (val >= 70) return 'text-yellow-600';
        return 'text-red-500';
    },
    moraleColor(val) {
        if (val >= 85) return 'text-green-600';
        if (val >= 75) return 'text-lime-600';
        if (val >= 65) return 'text-yellow-600';
        return 'text-red-500';
    },
}" @show-player-detail.window="player = $event.detail; $dispatch('open-modal', 'player-detail')">

    <x-modal name="player-detail" maxWidth="2xl">
        <template x-if="player">
            <div class="p-4 md:p-8">
                {{-- Header --}}
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs -skew-x-12 font-semibold"
                                  :class="player.positionDisplay.bg + ' ' + player.positionDisplay.text">
                                <span class="skew-x-12" x-text="player.positionDisplay.abbreviation"></span>
                            </span>
                            <h3 class="font-semibold text-xl md:text-2xl text-slate-900" x-text="player.name"></h3>
                            <template x-if="player.number">
                                <span class="text-slate-400 text-lg" x-text="'#' + player.number"></span>
                            </template>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mt-1.5 text-sm text-slate-500">
                            <span x-text="player.positionName"></span>
                            <span>&middot;</span>
                            <span x-text="player.age + ' {{ __('app.years') }}'"></span>
                            <template x-if="player.nationalityFlag">
                                <span class="inline-flex items-center gap-1">
                                    <span>&middot;</span>
                                    <img :src="'/flags/' + player.nationalityFlag.code + '.svg'" class="w-5 h-4 rounded shadow-sm">
                                    <span x-text="player.nationalityFlag.name"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                    <button @click="$dispatch('close-modal', 'player-detail')" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100 shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Two columns: Abilities + Details --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">

                    {{-- Abilities Card --}}
                    <div class="border border-slate-200 rounded-lg p-4">
                        <h4 class="font-semibold text-xs text-slate-400 uppercase tracking-wide mb-4">{{ __('squad.abilities') }}</h4>
                        <div class="space-y-3">
                            {{-- Technical --}}
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-600">{{ __('squad.technical_full') }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold tabular-nums w-6 text-right" :class="abilityColor(player.technicalAbility)" x-text="player.technicalAbility"></span>
                                    <div class="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-1.5 rounded-full" :class="abilityBg(player.technicalAbility)" :style="'width: ' + (player.technicalAbility / 99 * 100) + '%'"></div>
                                    </div>
                                </div>
                            </div>
                            {{-- Physical --}}
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-slate-600">{{ __('squad.physical_full') }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold tabular-nums w-6 text-right" :class="abilityColor(player.physicalAbility)" x-text="player.physicalAbility"></span>
                                    <div class="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                        <div class="h-1.5 rounded-full" :class="abilityBg(player.physicalAbility)" :style="'width: ' + (player.physicalAbility / 99 * 100) + '%'"></div>
                                    </div>
                                </div>
                            </div>
                            {{-- Fitness (squad only) --}}
                            <template x-if="player.type === 'squad'">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">{{ __('squad.fitness_full') }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold tabular-nums w-6 text-right" :class="fitnessColor(player.fitness)" x-text="player.fitness"></span>
                                        <div class="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-1.5 rounded-full" :class="abilityBg(player.fitness)" :style="'width: ' + player.fitness + '%'"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            {{-- Morale (squad only) --}}
                            <template x-if="player.type === 'squad'">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-slate-600">{{ __('squad.morale_full') }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold tabular-nums w-6 text-right" :class="moraleColor(player.morale)" x-text="player.morale"></span>
                                        <div class="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-1.5 rounded-full" :class="abilityBg(player.morale)" :style="'width: ' + player.morale + '%'"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        {{-- Overall badge --}}
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-center">
                            <div class="w-14 h-14 rounded-full flex items-center justify-center text-xl font-bold" :class="overallClasses(player.overallScore)" x-text="player.overallScore"></div>
                        </div>
                    </div>

                    {{-- Details Card --}}
                    <div class="border border-slate-200 rounded-lg p-4">
                        <h4 class="font-semibold text-xs text-slate-400 uppercase tracking-wide mb-4">{{ __('app.details') }}</h4>
                        <div class="space-y-2.5 text-sm">
                            {{-- Potential --}}
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">{{ __('game.potential') }}</span>
                                <span class="font-semibold text-slate-900" x-text="player.potentialRange"></span>
                            </div>
                            {{-- Development status (squad only) --}}
                            <template x-if="player.type === 'squad' && player.developmentStatus">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('squad.phase') }}</span>
                                    <span class="font-semibold" :class="devColor(player.developmentStatus)" x-text="devLabels[player.developmentStatus] || player.developmentStatus"></span>
                                </div>
                            </template>
                            {{-- Market value (career mode) --}}
                            <template x-if="player.isCareerMode && player.marketValue">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('app.value') }}</span>
                                    <span class="font-semibold text-slate-900" x-text="player.marketValue"></span>
                                </div>
                            </template>
                            {{-- Wage (career mode) --}}
                            <template x-if="player.isCareerMode && player.wage">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('app.wage') }}</span>
                                    <span class="font-semibold text-slate-900"><span x-text="player.wage"></span>{{ __('squad.per_year') }}</span>
                                </div>
                            </template>
                            {{-- Contract (career mode) --}}
                            <template x-if="player.isCareerMode && player.contractUntil">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('app.contract') }}</span>
                                    <span class="font-semibold text-slate-900" x-text="player.contractUntil"></span>
                                </div>
                            </template>
                            {{-- Appeared at (academy only) --}}
                            <template x-if="player.type === 'academy' && player.appearedAt">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('squad.discovered') }}</span>
                                    <span class="font-semibold text-slate-900" x-text="player.appearedAt"></span>
                                </div>
                            </template>
                            {{-- Status indicators --}}
                            <template x-if="player.isInjured">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('app.status') }}</span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                        {{ __('game.injured') }}
                                    </span>
                                </div>
                            </template>
                            <template x-if="player.isRetiring">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-500">{{ __('app.status') }}</span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                        {{ __('squad.retiring') }}
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Season Stats (squad players only) --}}
                <template x-if="player.type === 'squad'">
                    <div class="border border-slate-200 rounded-lg p-4 mt-4 md:mt-6">
                        <h4 class="font-semibold text-xs text-slate-400 uppercase tracking-wide mb-4">{{ __('squad.season_stats') }}</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-slate-900" x-text="player.appearances"></div>
                                <div class="text-xs text-slate-500">{{ __('squad.appearances') }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600" x-text="player.goals"></div>
                                <div class="text-xs text-slate-500">{{ __('squad.legend_goals') }}</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-sky-600" x-text="player.assists"></div>
                                <div class="text-xs text-slate-500">{{ __('squad.legend_assists') }}</div>
                            </div>
                            <div class="text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span class="flex items-center gap-0.5">
                                        <span class="w-2.5 h-3.5 bg-yellow-400 rounded-sm"></span>
                                        <span class="text-lg font-bold text-yellow-600" x-text="player.yellowCards"></span>
                                    </span>
                                    <span class="flex items-center gap-0.5">
                                        <span class="w-2.5 h-3.5 bg-red-500 rounded-sm"></span>
                                        <span class="text-lg font-bold text-red-600" x-text="player.redCards"></span>
                                    </span>
                                </div>
                                <div class="text-xs text-slate-500">{{ __('squad.cards') }}</div>
                            </div>
                            {{-- GK stats --}}
                            <template x-if="player.isGoalkeeper">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600" x-text="player.cleanSheets"></div>
                                    <div class="text-xs text-slate-500">{{ __('squad.clean_sheets_full') }}</div>
                                </div>
                            </template>
                            <template x-if="player.isGoalkeeper">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-red-500" x-text="player.goalsConceded"></div>
                                    <div class="text-xs text-slate-500">{{ __('squad.goals_conceded_full') }}</div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

            </div>
        </template>
    </x-modal>
</div>
