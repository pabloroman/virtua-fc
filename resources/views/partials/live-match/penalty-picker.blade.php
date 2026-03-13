{{-- Penalty Kicker Picker Modal --}}
{{-- Lives inside the liveMatch Alpine scope --}}

<div
    x-show="penaltyPickerOpen"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
>
    {{-- Backdrop --}}
    <div
        x-show="penaltyPickerOpen"
        class="fixed inset-0 transform transition-all"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-black/80"></div>
    </div>

    {{-- Panel --}}
    <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
        <div
            x-show="penaltyPickerOpen"
            class="relative w-full sm:max-w-lg bg-surface-800 sm:rounded-xl shadow-2xl transform transition-all overflow-hidden"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
            x-on:click.stop
        >
            {{-- Header --}}
            <div class="bg-purple-800 text-white px-4 py-3 sm:px-6 sm:py-4">
                <h2 class="text-sm sm:text-base font-bold uppercase tracking-wide">{{ __('game.live_pen_pick_title') }}</h2>
                <p class="text-xs text-purple-200 mt-0.5">{{ __('game.live_pen_pick_desc') }}</p>
            </div>

            {{-- Selected kickers (ordered) --}}
            <div class="px-4 py-3 sm:px-6 border-b border-border-default">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs font-semibold text-text-muted uppercase">{{ __('game.live_penalties') }}</span>
                    <span class="text-xs text-text-secondary" x-text="selectedPenaltyKickers.length + ' / 5'"></span>
                </div>
                <div class="space-y-1">
                    <template x-for="(kicker, idx) in selectedPenaltyKickers" :key="kicker.id">
                        <div class="flex items-center gap-2 py-1.5 px-2 rounded-sm bg-purple-500/10">
                            <span class="text-xs font-bold text-purple-400 w-5 text-center shrink-0" x-text="idx + 1"></span>
                            <span class="text-xs font-semibold rounded-sm px-1.5 py-0.5 text-white shrink-0"
                                  :class="getPositionBadgeColor(kicker.positionGroup)"
                                  x-text="kicker.positionAbbr"></span>
                            <span class="text-sm font-semibold text-text-primary flex-1 truncate" x-text="kicker.name"></span>
                            <span class="text-xs text-text-secondary shrink-0" x-text="'⭐ ' + kicker.technicalAbility"></span>
                            <button @click="removePenaltyKicker(idx)"
                                    class="text-text-secondary hover:text-red-500 transition-colors p-1 shrink-0 min-h-[44px] flex items-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                    {{-- Empty slots --}}
                    <template x-for="i in Math.max(0, 5 - selectedPenaltyKickers.length)" :key="'empty-' + i">
                        <div class="flex items-center gap-2 py-1.5 px-2 rounded-sm border border-dashed border-border-strong">
                            <span class="text-xs font-bold text-text-body w-5 text-center shrink-0" x-text="selectedPenaltyKickers.length + i"></span>
                            <span class="text-xs text-text-body">—</span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Available players --}}
            <div class="px-4 py-3 sm:px-6 max-h-60 overflow-y-auto">
                <div class="space-y-0.5">
                    <template x-for="player in availablePenaltyPlayers" :key="player.id">
                        <button @click="addPenaltyKicker(player)"
                                class="w-full flex items-center gap-2 py-2 px-2 rounded-sm hover:bg-surface-700/50 transition-colors text-left min-h-[44px]"
                                :disabled="selectedPenaltyKickers.length >= 5"
                                :class="selectedPenaltyKickers.length >= 5 ? 'opacity-30 cursor-not-allowed' : ''">
                            <span class="text-xs font-semibold rounded-sm px-1.5 py-0.5 text-white shrink-0"
                                  :class="getPositionBadgeColor(player.positionGroup)"
                                  x-text="player.positionAbbr"></span>
                            <span class="text-sm text-text-primary flex-1 truncate" x-text="player.name"></span>
                            <span class="text-xs text-text-secondary shrink-0" x-text="'⭐ ' + player.technicalAbility"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-4 py-3 sm:px-6 bg-surface-700/50 border-t border-border-default">
                <button @click="confirmPenaltyKickers()"
                        :disabled="selectedPenaltyKickers.length < 5 || penaltyProcessing"
                        class="w-full px-4 py-2.5 text-sm font-bold text-white rounded-lg transition-colors min-h-[44px]"
                        :class="selectedPenaltyKickers.length >= 5 && !penaltyProcessing
                            ? 'bg-purple-700 hover:bg-purple-800'
                            : 'bg-surface-600 text-text-muted cursor-not-allowed'">
                    <span x-show="!penaltyProcessing">{{ __('game.live_pen_pick_confirm') }}</span>
                    <span x-show="penaltyProcessing" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        ...
                    </span>
                </button>
            </div>
        </div>
    </div>
</div>
