{{-- Tactical Control Center - Full screen overlay --}}
{{-- This partial lives inside the liveMatch Alpine scope and shares all its reactive state --}}

<div
    x-show="tacticalPanelOpen"
    x-cloak
    class="fixed inset-0 z-50 overflow-y-auto"
    x-on:keydown.escape.window="if (tacticalPanelOpen && !subProcessing) closeTacticalPanel()"
>
    {{-- Backdrop --}}
    <div
        x-show="tacticalPanelOpen"
        class="fixed inset-0 transform transition-all"
        x-on:click="if (!subProcessing) closeTacticalPanel()"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div class="absolute inset-0 bg-slate-900 opacity-90"></div>
    </div>

    {{-- Panel --}}
    <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
        <div
            x-show="tacticalPanelOpen"
            class="relative w-full sm:max-w-2xl bg-white sm:rounded-xl shadow-2xl transform transition-all overflow-hidden"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
            x-on:click.stop
        >
            {{-- Header with match context --}}
            <div class="bg-slate-800 text-white px-4 py-3 sm:px-6 sm:py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <h2 class="text-sm sm:text-base font-bold uppercase tracking-wide truncate">{{ __('game.tactical_center') }}</h2>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-2.5 py-0.5 bg-amber-500/20 text-amber-300 shrink-0">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-amber-400"></span>
                            </span>
                            {{ __('game.tactical_paused') }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        {{-- Match score context --}}
                        <div class="hidden sm:flex items-center gap-2 text-sm">
                            <span class="font-semibold tabular-nums" x-text="homeScore"></span>
                            <span class="text-slate-400">-</span>
                            <span class="font-semibold tabular-nums" x-text="awayScore"></span>
                            <span class="text-slate-400 ml-1" x-text="displayMinute + '\''"></span>
                        </div>
                        {{-- Close button --}}
                        <button
                            @click="closeTacticalPanel()"
                            class="p-1.5 rounded-lg hover:bg-white/10 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center"
                            :disabled="subProcessing"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Mobile score context --}}
                <div class="flex sm:hidden items-center gap-2 text-xs text-slate-300 mt-1">
                    <span class="font-semibold tabular-nums" x-text="homeScore + ' - ' + awayScore"></span>
                    <span class="text-slate-500">&middot;</span>
                    <span x-text="displayMinute + '\''"></span>
                </div>
            </div>

            {{-- Tab bar --}}
            <div class="border-b border-slate-200 bg-slate-50">
                <div class="flex overflow-x-auto scrollbar-hide">
                    <button
                        @click="tacticalTab = 'substitutions'"
                        class="relative px-4 sm:px-6 py-3 text-xs sm:text-sm font-semibold shrink-0 transition-colors min-h-[44px]"
                        :class="tacticalTab === 'substitutions'
                            ? 'text-slate-900'
                            : 'text-slate-400 hover:text-slate-600'"
                    >
                        {{ __('game.tactical_tab_substitutions') }}
                        <span class="text-xs font-normal ml-1" :class="tacticalTab === 'substitutions' ? 'text-slate-500' : 'text-slate-400'"
                              x-text="'(' + substitutionsMade.length + '/' + maxSubstitutions + ')'"></span>
                        {{-- Active indicator --}}
                        <div
                            x-show="tacticalTab === 'substitutions'"
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-slate-800"
                        ></div>
                    </button>
                    <button
                        @click="tacticalTab = 'tactics'"
                        class="relative px-4 sm:px-6 py-3 text-xs sm:text-sm font-semibold shrink-0 transition-colors min-h-[44px]"
                        :class="tacticalTab === 'tactics'
                            ? 'text-slate-900'
                            : 'text-slate-400 hover:text-slate-600'"
                    >
                        {{ __('game.tactical_tab_tactics') }}
                        <div
                            x-show="tacticalTab === 'tactics'"
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-slate-800"
                        ></div>
                    </button>
                </div>
            </div>

            {{-- Tab panels --}}
            <div class="max-h-[70vh] sm:max-h-[65vh] overflow-y-auto">

                {{-- Substitutions tab --}}
                <div x-show="tacticalTab === 'substitutions'" class="p-4 sm:p-6">

                    {{-- Window + sub budget summary --}}
                    <div class="flex items-center gap-3 mb-4 text-xs text-slate-500">
                        <span>{{ __('game.sub_title') }}: <span class="font-semibold text-slate-700" x-text="substitutionsMade.length + '/' + maxSubstitutions"></span></span>
                        <span class="text-slate-300">&middot;</span>
                        <span>{{ __('game.sub_windows') }}: <span class="font-semibold text-slate-700" x-text="windowsUsed + '/' + maxWindows"></span></span>
                    </div>

                    {{-- All windows exhausted --}}
                    <template x-if="!hasWindowsLeft && pendingSubs.length === 0">
                        <div class="text-center py-8">
                            <div class="text-slate-400 text-sm">{{ __('game.sub_error_windows_reached') }}</div>
                        </div>
                    </template>

                    {{-- All subs used --}}
                    <template x-if="hasWindowsLeft && !canSubstitute && pendingSubs.length === 0">
                        <div class="text-center py-8">
                            <div class="text-slate-400 text-sm">{{ __('game.sub_limit_reached') }}</div>
                        </div>
                    </template>

                    {{-- Pending subs for this window --}}
                    <template x-if="pendingSubs.length > 0">
                        <div class="mb-4 space-y-2">
                            <h4 class="text-xs font-semibold text-slate-500 uppercase">{{ __('game.sub_pending') }}</h4>
                            <template x-for="(sub, idx) in pendingSubs" :key="idx">
                                <div class="flex items-center gap-2 px-3 py-2 bg-sky-50 border border-sky-200 rounded-md text-sm">
                                    <span class="text-red-500 shrink-0">&#8617;</span>
                                    <span class="truncate font-medium text-slate-700" x-text="sub.playerOut.name"></span>
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                    <span class="truncate font-medium text-slate-700" x-text="sub.playerIn.name"></span>
                                    <span class="text-green-500 shrink-0">&#8618;</span>
                                    <button
                                        @click="removePendingSub(idx)"
                                        class="ml-auto p-1 text-slate-400 hover:text-red-500 transition-colors shrink-0 min-h-[44px] min-w-[44px] flex items-center justify-center"
                                        :disabled="subProcessing"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Player picker (shown when there's room for more subs in this window) --}}
                    <template x-if="canSubstitute && hasWindowsLeft">
                        <div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- Player Out --}}
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('game.sub_player_out') }}</h4>
                                    <div class="space-y-1 max-h-52 overflow-y-auto">
                                        <template x-for="player in availableLineupForPicker" :key="player.id">
                                            <button
                                                @click="selectedPlayerOut = player"
                                                class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-left text-sm transition-colors min-h-[44px]"
                                                :class="selectedPlayerOut?.id === player.id
                                                    ? 'bg-red-100 border border-red-300 text-red-800'
                                                    : 'bg-white border border-slate-200 hover:border-slate-300 text-slate-700'"
                                            >
                                                <span class="inline-flex items-center justify-center w-7 h-7 text-xs -skew-x-12 font-semibold text-white shrink-0"
                                                      :class="getPositionBadgeColor(player.positionGroup)">
                                                    <span class="skew-x-12" x-text="player.positionAbbr"></span>
                                                </span>
                                                <span class="flex-1 truncate font-medium" x-text="player.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Player In --}}
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('game.sub_player_in') }}</h4>
                                    <div class="space-y-1 max-h-52 overflow-y-auto">
                                        <template x-for="player in availableBenchForPicker" :key="player.id">
                                            <button
                                                @click="selectedPlayerIn = player"
                                                class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-left text-sm transition-colors min-h-[44px]"
                                                :class="selectedPlayerIn?.id === player.id
                                                    ? 'bg-green-100 border border-green-300 text-green-800'
                                                    : 'bg-white border border-slate-200 hover:border-slate-300 text-slate-700'"
                                            >
                                                <span class="inline-flex items-center justify-center w-7 h-7 text-xs -skew-x-12 font-semibold text-white shrink-0"
                                                      :class="getPositionBadgeColor(player.positionGroup)">
                                                    <span class="skew-x-12" x-text="player.positionAbbr"></span>
                                                </span>
                                                <span class="flex-1 truncate font-medium" x-text="player.name"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            {{-- Add to pending / Confirm --}}
                            <div class="flex items-center justify-end gap-2 mt-4 pt-4 border-t border-slate-100">
                                <x-secondary-button @click="closeTacticalPanel()">
                                    {{ __('game.sub_cancel') }}
                                </x-secondary-button>

                                {{-- "Add another" button: queues current pair and keeps panel open --}}
                                <x-secondary-button
                                    @click="addPendingSub()"
                                    x-show="selectedPlayerOut && selectedPlayerIn && canAddMoreToPending && subsRemaining > 1"
                                >
                                    {{ __('game.sub_add_another') }}
                                </x-secondary-button>

                                {{-- Confirm button: sends the whole batch --}}
                                <x-primary-button
                                    color="sky"
                                    type="button"
                                    @click="confirmSubstitutions()"
                                    x-bind:disabled="(!selectedPlayerOut || !selectedPlayerIn) && pendingSubs.length === 0 || subProcessing"
                                >
                                    <span x-show="!subProcessing">{{ __('game.sub_confirm') }}</span>
                                    <span x-show="subProcessing">{{ __('game.sub_processing') }}</span>
                                </x-primary-button>
                            </div>
                        </div>
                    </template>

                    {{-- Confirm pending subs when picker is hidden (windows/subs exhausted but pending exists) --}}
                    <template x-if="pendingSubs.length > 0 && (!canSubstitute || !hasWindowsLeft)">
                        <div class="flex items-center justify-end gap-2 mt-4 pt-4 border-t border-slate-100">
                            <x-secondary-button @click="closeTacticalPanel()">
                                {{ __('game.sub_cancel') }}
                            </x-secondary-button>
                            <x-primary-button
                                color="sky"
                                type="button"
                                @click="confirmSubstitutions()"
                                x-bind:disabled="subProcessing"
                            >
                                <span x-show="!subProcessing">{{ __('game.sub_confirm') }}</span>
                                <span x-show="subProcessing">{{ __('game.sub_processing') }}</span>
                            </x-primary-button>
                        </div>
                    </template>

                    {{-- Made substitutions list --}}
                    <template x-if="substitutionsMade.length > 0">
                        <div class="mt-4 pt-4 border-t border-slate-100">
                            <h4 class="text-xs font-semibold text-slate-400 uppercase mb-2">{{ __('game.tactical_subs_made') }}</h4>
                            <div class="space-y-1">
                                <template x-for="(sub, idx) in substitutionsMade" :key="idx">
                                    <div class="flex items-center gap-2 text-xs text-slate-500 py-1">
                                        <span class="font-mono w-6 text-right shrink-0" x-text="sub.minute + '\''"></span>
                                        <span class="text-red-500">&#8617;</span>
                                        <span class="truncate" x-text="sub.playerOutName"></span>
                                        <span class="text-green-500">&#8618;</span>
                                        <span class="truncate" x-text="sub.playerInName"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Tactics tab (read-only overview for now, future: editable) --}}
                <div x-show="tacticalTab === 'tactics'" class="p-4 sm:p-6">
                    <div class="space-y-4">
                        {{-- Current formation --}}
                        <div>
                            <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('game.tactical_formation') }}</h4>
                            <div class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 rounded-lg">
                                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                                <span class="text-sm font-bold text-slate-800 tabular-nums" x-text="activeFormation"></span>
                            </div>
                        </div>

                        {{-- Current mentality --}}
                        <div>
                            <h4 class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('game.tactical_mentality') }}</h4>
                            <div class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg"
                                 :class="{
                                     'bg-blue-50 text-blue-800': activeMentality === 'defensive',
                                     'bg-slate-100 text-slate-800': activeMentality === 'balanced',
                                     'bg-red-50 text-red-800': activeMentality === 'attacking',
                                 }">
                                <svg class="w-4 h-4" :class="{
                                         'text-blue-500': activeMentality === 'defensive',
                                         'text-slate-500': activeMentality === 'balanced',
                                         'text-red-500': activeMentality === 'attacking',
                                     }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span class="text-sm font-bold" x-text="mentalityLabel"></span>
                            </div>
                        </div>

                        {{-- Coming soon hint --}}
                        <div class="mt-2 p-3 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                            <p class="text-xs text-slate-400">{{ __('game.tactical_coming_soon') }}</p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Footer: resume match --}}
            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 sm:px-6 sm:py-4">
                <button
                    @click="closeTacticalPanel()"
                    :disabled="subProcessing"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-800 rounded-lg hover:bg-slate-100 transition-colors min-h-[44px]"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    {{ __('game.tactical_resume') }}
                </button>
            </div>
        </div>
    </div>
</div>
