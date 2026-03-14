{{-- Tactical Control Center - Full screen overlay --}}
{{-- This partial lives inside the liveMatch Alpine scope and shares all its reactive state --}}

<div
    x-show="tacticalPanelOpen"
    x-cloak
    class="fixed inset-0 z-50 overflow-hidden"
    x-on:keydown.escape.window="if (tacticalPanelOpen && !subProcessing && !tacticsProcessing) safeCloseTacticalPanel()"
>
    {{-- Backdrop --}}
    <div
        x-show="tacticalPanelOpen"
        class="fixed inset-0 transform transition-all"
        x-on:click="if (!subProcessing && !tacticsProcessing) safeCloseTacticalPanel()"
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
    <div class="flex h-full sm:min-h-full items-stretch sm:items-center justify-center p-0 sm:p-4">
        <div
            x-show="tacticalPanelOpen"
            class="relative w-full max-h-full sm:max-h-none sm:max-w-4xl bg-surface-800 sm:rounded-xl shadow-2xl transform transition-all overflow-hidden flex flex-col"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
            x-on:click.stop
        >
            {{-- Header with match context --}}
            <div class="bg-surface-900 border-b border-border-default text-text-primary px-4 py-3 sm:px-6 sm:py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <h2 class="text-sm sm:text-base font-heading font-semibold uppercase tracking-wider truncate">{{ __('game.tactical_center') }}</h2>
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-2.5 py-0.5 bg-accent-gold/100/20 text-accent-gold shrink-0">
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
                            <span class="text-text-secondary">-</span>
                            <span class="font-semibold tabular-nums" x-text="awayScore"></span>
                            <span class="text-text-secondary ml-1" x-text="displayMinute + '\''"></span>
                        </div>
                        {{-- Close button --}}
                        <x-icon-button
                            @click="safeCloseTacticalPanel()"
                            x-bind:disabled="subProcessing || tacticsProcessing"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </x-icon-button>
                    </div>
                </div>

                {{-- Mobile score context --}}
                <div class="flex sm:hidden items-center gap-2 text-xs text-text-body mt-1">
                    <span class="font-semibold tabular-nums" x-text="homeScore + ' - ' + awayScore"></span>
                    <span class="text-text-muted">&middot;</span>
                    <span x-text="displayMinute + '\''"></span>
                </div>
            </div>

            {{-- Tab bar --}}
            <div class="border-b border-border-strong bg-surface-700/50">
                <div class="flex overflow-x-auto scrollbar-hide">
                    <x-tab-button
                        @click="tacticalTab = 'substitutions'"
                        class="relative px-4 sm:px-6 py-3 text-xs sm:text-sm font-semibold shrink-0 min-h-[44px]"
                        x-bind:class="tacticalTab === 'substitutions'
                            ? 'text-text-primary border-transparent'
                            : 'text-text-secondary hover:text-text-secondary border-transparent'"
                    >
                        {{ __('game.tactical_tab_substitutions') }}
                        <span class="text-xs font-normal ml-1" :class="tacticalTab === 'substitutions' ? 'text-text-muted' : 'text-text-secondary'"
                              x-text="'(' + substitutionsMade.length + '/' + effectiveMaxSubstitutions + ' · ' + windowsUsed + '/' + effectiveMaxWindows + ')'"></span>
                        {{-- Active indicator --}}
                        <div
                            x-show="tacticalTab === 'substitutions'"
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-surface-800"
                        ></div>
                    </x-tab-button>
                    <x-tab-button
                        @click="tacticalTab = 'tactics'"
                        class="relative px-4 sm:px-6 py-3 text-xs sm:text-sm font-semibold shrink-0 min-h-[44px]"
                        x-bind:class="tacticalTab === 'tactics'
                            ? 'text-text-primary border-transparent'
                            : 'text-text-secondary hover:text-text-secondary border-transparent'"
                    >
                        {{ __('game.tactical_tab_tactics') }}
                        <div
                            x-show="tacticalTab === 'tactics'"
                            class="absolute bottom-0 left-0 right-0 h-0.5 bg-surface-800"
                        ></div>
                    </x-tab-button>
                </div>
            </div>

            {{-- Tab panels --}}
            <div class="flex-1 min-h-0 sm:max-h-[60vh] overflow-y-auto">
              <div class="grid [&>div]:col-start-1 [&>div]:row-start-1">

                {{-- Substitutions tab --}}
                <div class="p-4 sm:p-6 transition-opacity duration-150"
                     :class="tacticalTab === 'substitutions' ? 'opacity-100 relative z-10' : 'opacity-0 invisible pointer-events-none'"
                >

                    {{-- Injury alert banner --}}
                    <div x-show="injuryAlertPlayer" x-transition class="flex items-center gap-2.5 p-3 mb-4 bg-accent-red/10 border border-accent-red/20 rounded-lg">
                        <span class="flex items-center justify-center w-8 h-8 rounded-full bg-accent-red/10 shrink-0">
                            <svg class="w-4 h-4 text-accent-red" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </span>
                        <p class="text-sm font-medium text-accent-red">
                            <span x-text="injuryAlertPlayer"></span> {{ __('game.live_injury_alert') }}
                        </p>
                        <x-icon-button size="sm" @click="injuryAlertPlayer = null" class="ml-auto text-red-400 hover:text-accent-red shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </x-icon-button>
                    </div>

                    {{-- All windows exhausted --}}
                    <template x-if="!hasWindowsLeft && pendingSubs.length === 0">
                        <div class="text-center py-8">
                            <div class="text-text-secondary text-sm">{{ __('game.sub_error_windows_reached') }}</div>
                        </div>
                    </template>

                    {{-- All subs used --}}
                    <template x-if="hasWindowsLeft && !canSubstitute && pendingSubs.length === 0">
                        <div class="text-center py-8">
                            <div class="text-text-secondary text-sm">{{ __('game.sub_limit_reached') }}</div>
                        </div>
                    </template>

                    {{-- Pending subs for this window --}}
                    <template x-if="pendingSubs.length > 0">
                        <div class="mb-4 space-y-1">
                            <h4 class="text-xs font-semibold text-text-muted uppercase">{{ __('game.sub_pending') }}</h4>
                            <template x-for="(sub, idx) in pendingSubs" :key="idx">
                                <div class="flex items-center gap-2 px-3 py-0 bg-accent-blue/10 border border-accent-blue/20 rounded-md text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-accent-blue shrink-0">
                                        <path fill-rule="evenodd" d="M13.2 2.24a.75.75 0 0 0 .04 1.06l2.1 1.95H6.75a.75.75 0 0 0 0 1.5h8.59l-2.1 1.95a.75.75 0 1 0 1.02 1.1l3.5-3.25a.75.75 0 0 0 0-1.1l-3.5-3.25a.75.75 0 0 0-1.06.04Zm-6.4 8a.75.75 0 0 0-1.06-.04l-3.5 3.25a.75.75 0 0 0 0 1.1l3.5 3.25a.75.75 0 1 0 1.02-1.1l-2.1-1.95h8.59a.75.75 0 0 0 0-1.5H4.66l2.1-1.95a.75.75 0 0 0 .04-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="truncate font-medium text-text-body" x-text="sub.playerOut.name"></span>
                                    <svg class="w-3 h-3 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                    <span class="truncate font-medium text-text-body" x-text="sub.playerIn.name"></span>
                                    <x-icon-button
                                        @click="removePendingSub(idx)"
                                        class="ml-auto shrink-0 hover:text-red-500"
                                        x-bind:disabled="subProcessing"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </x-icon-button>
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
                                    <h4 class="text-xs font-semibold text-text-muted uppercase mb-2">{{ __('game.sub_player_out') }}</h4>
                                    <div class="space-y-1">
                                        <template x-for="player in availableLineupForPicker" :key="player.id">
                                            <button
                                                @click="selectedPlayerOut = player"
                                                class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-left text-sm transition-colors min-h-[44px]"
                                                :class="selectedPlayerOut?.id === player.id
                                                    ? 'bg-accent-red/10 border border-accent-red/20 text-accent-red'
                                                    : 'bg-surface-800 border border-border-strong hover:border-border-strong text-text-body'"
                                            >
                                                <span class="inline-flex items-center justify-center w-7 h-7 text-xs -skew-x-12 font-semibold text-white shrink-0"
                                                      :class="getPositionBadgeColor(player.positionGroup)">
                                                    <span class="skew-x-12" x-text="player.positionAbbr"></span>
                                                </span>
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-semibold shrink-0"
                                                      :class="getOvrBadgeClasses(player.overallScore)"
                                                      x-text="player.overallScore"></span>
                                                <span class="flex-1 truncate font-medium" x-text="player.name"></span>
                                                {{-- Yellow card indicator --}}
                                                <span x-show="isPlayerYellowCarded(player.id)"
                                                      x-tooltip.raw="{{ __('game.player_booked') }}"
                                                      class="shrink-0 w-2 h-3 rounded-[1px] bg-yellow-400 border border-yellow-500"></span>
                                                {{-- Energy bar --}}
                                                <span class="ml-auto flex items-center gap-1 shrink-0">
                                                    <span class="text-[10px] tabular-nums font-semibold"
                                                          :class="getEnergyTextColor(getPlayerEnergy(player))"
                                                          x-text="getPlayerEnergy(player) + '%'"></span>
                                                    <span class="w-10 h-1.5 rounded-full overflow-hidden"
                                                          :class="getEnergyBarBg(getPlayerEnergy(player))">
                                                        <span class="h-full rounded-full block transition-all duration-300"
                                                              :class="getEnergyColor(getPlayerEnergy(player))"
                                                              :style="'width:' + getPlayerEnergy(player) + '%'"></span>
                                                    </span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                {{-- Player In --}}
                                <div>
                                    <h4 class="text-xs font-semibold text-text-muted uppercase mb-2">{{ __('game.sub_player_in') }}</h4>
                                    <div class="space-y-1">
                                        <template x-for="player in availableBenchForPicker" :key="player.id">
                                            <button
                                                @click="selectedPlayerIn = player"
                                                class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-left text-sm transition-colors min-h-[44px]"
                                                :class="selectedPlayerIn?.id === player.id
                                                    ? 'bg-accent-green/10 border border-green-300 text-green-800'
                                                    : 'bg-surface-800 border border-border-strong hover:border-border-strong text-text-body'"
                                            >
                                                <span class="inline-flex items-center justify-center w-7 h-7 text-xs -skew-x-12 font-semibold text-white shrink-0"
                                                      :class="getPositionBadgeColor(player.positionGroup)">
                                                    <span class="skew-x-12" x-text="player.positionAbbr"></span>
                                                </span>
                                                <span class="flex-1 truncate font-medium" x-text="player.name"></span>
                                                {{-- OVR badge with fitness/morale tooltip --}}
                                                <span class="ml-auto inline-flex items-center justify-center w-6 h-6 rounded-full text-[10px] font-semibold shrink-0"
                                                      :class="getOvrBadgeClasses(player.overallScore)"
                                                      :x-tooltip="'{{ __('game.ovr_fitness') }}: ' + player.fitness + ' · {{ __('game.ovr_morale') }}: ' + player.morale"
                                                      x-text="player.overallScore"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </template>

                    {{-- Made substitutions list --}}
                    <template x-if="substitutionsMade.length > 0">
                        <div class="mt-4 pt-4 border-t border-border-default">
                            <h4 class="text-xs font-semibold text-text-secondary uppercase mb-2">{{ __('game.tactical_subs_made') }}</h4>
                            <div class="space-y-1">
                                <template x-for="(sub, idx) in substitutionsMade" :key="idx">
                                    <div class="flex items-center gap-2 text-xs text-text-muted py-1">
                                        <span class="font-heading font-bold w-7 text-right text-text-secondary shrink-0" x-text="sub.minute + '\''"></span>
                                        <span class="text-accent-red text-[10px] font-semibold">OFF</span>
                                        <span class="truncate" x-text="sub.playerOutName"></span>
                                        <span class="text-accent-green text-[10px] font-semibold">ON</span>
                                        <span class="truncate" x-text="sub.playerInName"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Tactics tab --}}
                <div class="p-4 sm:p-6 transition-opacity duration-150"
                     :class="tacticalTab === 'tactics' ? 'opacity-100 relative z-10' : 'opacity-0 invisible pointer-events-none'"
                >
                    <div class="space-y-5">
                        {{-- Formation picker --}}
                        <div>
                            <h4 class="text-xs font-semibold text-text-muted uppercase mb-2 flex items-center gap-1.5">
                                {{ __('game.tactical_formation') }}
                                <span x-tooltip.raw="{{ __('game.tactical_formation_hint') }}" class="cursor-help shrink-0"><svg class="w-3.5 h-3.5 text-text-body hover:text-text-muted" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                            </h4>
                            <x-tactical-lever model="(pendingFormation ?? activeFormation)" set="pendingFormation" options="availableFormations" :columns="4" />
                            <p class="mt-2 text-xs text-text-secondary italic min-h-5" x-text="getFormationTooltip()"></p>
                        </div>

                        {{-- Mentality picker --}}
                        <div>
                            <h4 class="text-xs font-semibold text-text-muted uppercase mb-2 flex items-center gap-1.5">
                                {{ __('game.tactical_mentality') }}
                                <span x-tooltip.raw="{{ __('game.tactical_mentality_hint') }}" class="cursor-help shrink-0"><svg class="w-3.5 h-3.5 text-text-body hover:text-text-muted" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                            </h4>
                            <x-tactical-lever model="(pendingMentality ?? activeMentality)" set="pendingMentality" options="availableMentalities" :columns="3" />
                            <p class="mt-2 text-xs text-text-secondary italic min-h-5" x-text="getMentalityTooltip(pendingMentality ?? activeMentality)"></p>
                        </div>

                        {{-- Team Instructions --}}
                        <div class="pt-3 border-t border-border-default">
                            <h4 class="text-xs font-semibold text-text-muted uppercase mb-3 flex items-center gap-1.5">
                                {{ __('game.instructions_title') }}
                                <span x-tooltip.raw="{{ __('game.tactical_guide_link') }}" class="cursor-help shrink-0"><svg class="w-3.5 h-3.5 text-text-body hover:text-text-muted" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg></span>
                            </h4>

                            {{-- Playing Style (In Possession) --}}
                            <div class="mb-3">
                                <p class="text-[10px] font-medium text-text-secondary uppercase mb-1.5">{{ __('game.instructions_in_possession') }}</p>
                                <x-tactical-lever model="(pendingPlayingStyle ?? activePlayingStyle)" set="pendingPlayingStyle" options="availablePlayingStyles" :columns="4" summary-field="tooltip" />
                            </div>

                            {{-- Pressing (Out of Possession) --}}
                            <div class="mb-3">
                                <p class="text-[10px] font-medium text-text-secondary uppercase mb-1.5">{{ __('game.instructions_out_of_possession') }}</p>
                                <x-tactical-lever model="(pendingPressing ?? activePressing)" set="pendingPressing" options="availablePressing" :columns="3" summary-field="tooltip" />
                            </div>

                            {{-- Defensive Line --}}
                            <div>
                                <x-tactical-lever model="(pendingDefLine ?? activeDefLine)" set="pendingDefLine" options="availableDefLine" :columns="3" summary-field="tooltip" />
                            </div>
                        </div>

                        {{-- Action buttons moved to sticky footer --}}
                    </div>
                </div>

              </div>{{-- /grid --}}
            </div>

            {{-- Sticky footer: actions + resume --}}
            <div class="border-t border-border-strong bg-surface-900 px-4 py-3 sm:px-6 space-y-2">

                {{-- Substitutions tab actions --}}
                <div x-show="tacticalTab === 'substitutions'" class="flex items-center gap-2">
                    <x-secondary-button
                        @click="resetSubstitutions()"
                        x-show="selectedPlayerOut || selectedPlayerIn || pendingSubs.length > 0"
                        class="gap-1.5"
                    >
                        {{ __('game.sub_reset') }}
                    </x-secondary-button>

                    <x-secondary-button
                        @click="addPendingSub()"
                        x-show="selectedPlayerOut && selectedPlayerIn && canAddMoreToPending && subsRemaining > 1"
                        class="gap-1.5"
                    >
                        {{ __('game.sub_add_another') }}
                    </x-secondary-button>

                    <x-primary-button
                        color="sky"
                        type="button"
                        @click="confirmSubstitutions()"
                        x-bind:disabled="(!selectedPlayerOut || !selectedPlayerIn) && pendingSubs.length === 0 || subProcessing"
                        x-show="(canSubstitute && hasWindowsLeft) || pendingSubs.length > 0"
                        class="ml-auto gap-1.5"
                    >
                        <span x-show="!subProcessing">{{ __('game.sub_confirm') }}</span>
                        <span x-show="subProcessing">{{ __('game.sub_processing') }}</span>
                    </x-primary-button>
                </div>

                {{-- Tactics tab actions --}}
                <div x-show="tacticalTab === 'tactics'" class="flex items-center gap-2">
                    <x-secondary-button
                        @click="resetTactics()"
                        x-show="hasTacticalChanges"
                        class="gap-1.5"
                    >
                        {{ __('game.sub_reset') }}
                    </x-secondary-button>

                    <x-primary-button
                        color="sky"
                        type="button"
                        @click="confirmTacticalChanges()"
                        x-bind:disabled="!hasTacticalChanges || tacticsProcessing"
                        class="ml-auto gap-1.5"
                    >
                        <span x-show="!tacticsProcessing">{{ __('game.tactical_apply') }}</span>
                        <span x-show="tacticsProcessing">{{ __('game.sub_processing') }}</span>
                    </x-primary-button>
                </div>

                {{-- Resume match (always visible) --}}
                <x-secondary-button
                    @click="safeCloseTacticalPanel()"
                    x-bind:disabled="subProcessing || tacticsProcessing"
                    class="w-full justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    {{ __('game.tactical_resume') }}
                </x-secondary-button>
            </div>
        </div>
    </div>
</div>
