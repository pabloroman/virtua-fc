@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch $match */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$match"></x-game-header>
    </x-slot>

    <div x-data="lineupManager({
        currentLineup: @js($currentLineup ?? []),
        currentFormation: @js($currentFormation),
        currentMentality: @js($currentMentality),
        currentPlayingStyle: @js($currentPlayingStyle ?? 'balanced'),
        currentPressing: @js($currentPressing ?? 'standard'),
        currentDefLine: @js($currentDefLine ?? 'normal'),
        formationOptions: @js($formationOptions),
        mentalityOptions: @js($mentalityOptions),
        playingStyles: @js($playingStyles),
        pressingOptions: @js($pressingOptions),
        defensiveLineOptions: @js($defensiveLineOptions),
        autoLineup: @js($autoLineup ?? []),
        currentSlotMap: @js($currentSlotMap ?? (object) []),
        gridConfig: @js($gridConfig),
        currentPitchPositions: @js($currentPitchPositions ?? (object) []),
        playersData: @js($playersData),
        formationSlots: @js($formationSlots),
        slotCompatibility: @js($slotCompatibility),
        autoLineupUrl: '{{ route('game.lineup.auto', $game->id) }}',
        computeSlotsUrl: '{{ $computeSlotsUrl }}',
        teamColors: @js($teamColors),
        formationModifiers: @js($formationModifiers),
        opponentAverage: {{ $opponentData['teamAverage'] ?: 0 }},
        opponentFormation: @js($opponentData['formation'] ?? null),
        opponentMentality: @js($opponentData['mentality'] ?? null),
        opponentPlayingStyle: @js($opponentData['playingStyle'] ?? 'balanced'),
        opponentPressing: @js($opponentData['pressing'] ?? 'standard'),
        opponentDefLine: @js($opponentData['defensiveLine'] ?? 'normal'),
        xgConfig: @js($xgConfig),
        userTeamAverage: {{ $userTeamAverage ?: 0 }},
        isHome: @js($isHome),
        presets: @js($presetsConfig),
        activePresetIdOnLoad: @js(session('active_preset_id')),
        translations: {
            natural: '{{ __('squad.natural') }}',
            veryGood: '{{ __('squad.very_good') }}',
            good: '{{ __('squad.good') }}',
            okay: '{{ __('squad.okay') }}',
            poor: '{{ __('squad.poor') }}',
            unsuitable: '{{ __('squad.unsuitable') }}',
            coach_defensive_recommended: @js(__('squad.coach_defensive_recommended')),
            coach_attacking_recommended: @js(__('squad.coach_attacking_recommended')),
            coach_risky_formation: @js(__('squad.coach_risky_formation')),
            coach_home_advantage: @js(__('squad.coach_home_advantage')),
            coach_critical_fitness: @js(__('squad.coach_critical_fitness')),
            coach_low_fitness: @js(__('squad.coach_low_fitness')),
            coach_low_morale: @js(__('squad.coach_low_morale')),
            coach_bench_frustration: @js(__('squad.coach_bench_frustration')),
            coach_no_tips: @js(__('squad.coach_no_tips')),
            coach_opponent_defensive_setup: @js(__('squad.coach_opponent_defensive_setup')),
            coach_opponent_attacking_setup: @js(__('squad.coach_opponent_attacking_setup')),
            coach_opponent_deep_block: @js(__('squad.coach_opponent_deep_block')),
            coach_out_of_position: @js(__('squad.coach_out_of_position')),
            mentality_defensive: @js(__('squad.mentality_defensive')),
            mentality_balanced: @js(__('squad.mentality_balanced')),
            mentality_attacking: @js(__('squad.mentality_attacking')),
        },
    })">
        <div class="max-w-7xl mx-auto px-4 pb-8">

            {{-- Lineup reconciliation banner: appears when the saved lineup
                 referenced players who are no longer available (sold,
                 transferred, retired, long-term injured) and the system
                 auto-replaced them on load. Lets the user see which slots
                 were affected so they can review before playing. --}}
            @if(!empty($reconciliationBanner))
                <x-status-banner color="gold"
                    :title="__('squad.lineup_reconciled_title')"
                    :description="trans_choice('squad.lineup_reconciled_subtitle', $reconciliationBanner['count'], ['count' => $reconciliationBanner['count']])"
                    class="mt-4">
                    <x-slot name="icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </x-slot>
                </x-status-banner>
                <ul class="mt-2 mb-2 text-xs md:text-sm text-text-secondary space-y-1 pl-3">
                    @foreach($reconciliationBanner['items'] as $item)
                        <li>
                            @if($item['in_name'])
                                {{ __('squad.lineup_reconciled_replacement', ['out' => $item['out_name'], 'in' => $item['in_name']]) }}
                            @else
                                {{ __('squad.lineup_reconciled_removed', ['out' => $item['out_name']]) }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Errors --}}
            @if ($errors->any())
                <div class="mt-4 p-4 bg-accent-red/10 border border-accent-red/20 rounded-lg">
                    <ul class="text-sm text-accent-red">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('game.lineup.save', $game->id) }}" @submit="_isSaving = true">
                @csrf

                {{-- Hidden inputs --}}
                <template x-for="playerId in selectedPlayers" :key="playerId">
                    <input type="hidden" name="players[]" :value="playerId">
                </template>
                <input type="hidden" name="formation" :value="selectedFormation">
                <input type="hidden" name="mentality" :value="selectedMentality">
                <input type="hidden" name="playing_style" :value="selectedPlayingStyle">
                <input type="hidden" name="pressing" :value="selectedPressing">
                <input type="hidden" name="defensive_line" :value="selectedDefLine">
                <template x-for="slot in slotAssignments" :key="'sa-' + slot.id">
                    <input x-show="slot.player" type="hidden" :name="'slot_assignments[' + slot.id + ']'" :value="slot.player?.id">
                </template>
                <template x-for="(pos, slotId) in pitchPositions" :key="'pp-' + slotId">
                    <input type="hidden" :name="'pitch_positions[' + slotId + ']'" :value="pos[0] + ',' + pos[1]">
                </template>

                {{-- ===== Page Header + Controls ===== --}}
                <div class="mt-6 flex flex-col gap-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('squad.tactics') }}</h2>
                            <div class="flex items-baseline gap-1">
                                <span class="font-heading text-lg font-bold tabular-nums"
                                      :class="selectedCount === 11 ? 'text-accent-green' : 'text-text-primary'"
                                      x-text="selectedCount">0</span>
                                <span class="text-xs text-text-muted font-medium">/11</span>
                            </div>
                            <span x-show="isDirty" x-cloak class="w-2 h-2 rounded-full bg-accent-gold shrink-0" title="{{ __('squad.unsaved_changes') }}"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ghost-button color="slate" @click="clearSelection()">
                                {{ __('app.clear') }}
                            </x-ghost-button>
                            <x-secondary-button type="button" size="sm" @click="quickSelect(); $dispatch('open-modal', 'auto-lineup')">
                                {{ __('squad.auto_select') }}
                            </x-secondary-button>
                            <x-primary-button size="sm" x-bind:disabled="selectedCount !== 11">
                                {{ __('app.confirm') }}
                            </x-primary-button>
                        </div>
                    </div>

                    <div class="border-t border-border-default"></div>

                    {{-- Saved tactical presets --}}
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                        <span class="text-[10px] text-text-muted uppercase tracking-wider shrink-0">{{ __('squad.presets') }}</span>
                        <div class="flex gap-1.5">
                            <template x-for="preset in presets" :key="preset.id">
                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button"
                                        @click="loadPreset(preset)"
                                        class="formation-option flex items-center gap-1.5 px-2.5 py-1.5 rounded-md bg-surface-700 border border-border-strong text-sm font-medium text-text-body hover:bg-blue-500/10 hover:border-blue-500/50 min-h-[36px]"
                                        x-bind:class="activePresetId === preset.id && 'active'">
                                        <span class="text-[10px] font-heading tracking-wide" x-bind:class="activePresetId === preset.id ? 'text-blue-200' : 'text-text-muted'" x-text="preset.formation"></span>
                                        <span x-text="preset.name"></span>
                                    </button>
                                    <button type="button"
                                        x-bind:data-id="preset.id"
                                        @click="
                                            if (!confirm('{{ __('squad.preset_delete_confirm') }}')) return;
                                            _isSaving = true;
                                            let f = document.createElement('form');
                                            f.method = 'POST';
                                            f.action = '{{ url('game/' . $game->id . '/tactical-presets') }}/' + preset.id;
                                            f.innerHTML = '<input type=hidden name=_token value={{ csrf_token() }}><input type=hidden name=_method value=DELETE>';
                                            document.body.appendChild(f);
                                            f.submit();
                                        "
                                        class="p-1 text-text-faint hover:text-accent-red transition-colors rounded-sm min-h-[36px]"
                                        title="{{ __('app.remove') }}">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </template>
                            <button type="button"
                                @click="$dispatch('open-modal', 'save-preset')"
                                x-bind:disabled="selectedCount !== 11"
                                class="flex items-center gap-1 px-2.5 py-1.5 rounded-md border border-dashed border-border-default text-sm text-text-muted hover:text-text-body hover:border-border-strong transition-colors shrink-0 min-h-[36px] disabled:opacity-40 disabled:cursor-not-allowed">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                {{ __('squad.save_preset') }}
                            </button>
                        </div>
                    </div>

                    {{-- Formation inline controls --}}
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                        <span class="text-[10px] text-text-muted uppercase tracking-wider shrink-0">{{ __('squad.formation') }}</span>
                        <div class="flex gap-1">
                            <template x-for="option in formationOptions" :key="'fo-' + option.value">
                                <x-pill-button
                                    type="button"
                                    @click="selectedFormation = option.value; updateAutoLineup()"
                                    class="formation-option rounded-md border border-border-strong font-heading tracking-wide font-semibold"
                                    x-bind:class="selectedFormation === option.value && 'active'"
                                    x-text="option.label"></x-pill-button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- ===== MOBILE TAB SWITCHER ===== --}}
                <div class="flex lg:hidden mt-4 bg-surface-700 rounded-lg p-0.5">
                    <x-pill-button size="xs" type="button" @click="activeLineupTab = 'pitch'"
                        class="flex-1 text-center rounded-md min-h-[44px]"
                        x-bind:class="activeLineupTab === 'pitch' ? 'bg-surface-800 shadow-xs text-text-primary' : 'text-text-muted hover:text-text-body'">
                        {{ __('squad.pitch') }}
                    </x-pill-button>
                    <x-pill-button size="xs" type="button" @click="activeLineupTab = 'squad'"
                        class="flex-1 text-center rounded-md min-h-[44px]"
                        x-bind:class="activeLineupTab === 'squad' ? 'bg-surface-800 shadow-xs text-text-primary' : 'text-text-muted hover:text-text-body'">
                        {{ __('app.squad') }}
                    </x-pill-button>
                    <x-pill-button size="xs" type="button" @click="activeLineupTab = 'tactics'"
                        class="flex-1 text-center rounded-md min-h-[44px]"
                        x-bind:class="activeLineupTab === 'tactics' ? 'bg-surface-800 shadow-xs text-text-primary' : 'text-text-muted hover:text-text-body'">
                        {{ __('squad.tactics') }}
                    </x-pill-button>
                </div>

                {{-- ===== MAIN CONTENT: Pitch + Players + Tactics ===== --}}
                <div class="mt-4 flex flex-col lg:flex-row gap-4">

                    {{-- LEFT: Pitch + Coach (sticky on desktop) --}}
                    <div class="lg:flex-2 space-y-4 lg:sticky lg:top-4 lg:self-start"
                         :class="{ 'hidden lg:block': activeLineupTab !== 'pitch' }">

                        {{-- PITCH VISUALIZATION (shared component) --}}
                        <x-pitch-display mode="lineup" />

                    </div>

                    {{-- List-to-pitch drag ghost (fixed position, follows cursor across containers) --}}
                    <div
                        x-show="listDragPlayerId && listDragGhostPos"
                        x-cloak
                        class="fixed z-50 pointer-events-none flex flex-col items-center transform -translate-x-1/2 -translate-y-1/2"
                        :style="listDragGhostPos ? `left: ${listDragGhostPos.x}px; top: ${listDragGhostPos.y}px` : ''"
                    >
                        <template x-if="listDragPlayerId">
                            <div class="flex flex-col items-center">
                                <div class="relative w-11 h-11 rounded-xl shadow-xl border-2 border-white/30 opacity-80"
                                    :style="getShirtStyle(getPlayerRole(listDragPlayerId))">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full"
                                            :style="getNumberStyle(getPlayerRole(listDragPlayerId))"
                                            x-text="playersData[listDragPlayerId]?.number || getInitials(playersData[listDragPlayerId]?.name)"></span>
                                    </div>
                                </div>
                                <span class="mt-0.5 text-[8px] font-semibold text-white uppercase tracking-wide leading-tight text-center max-w-[66px] line-clamp-2 break-words drop-shadow-[0_1px_2px_rgba(0,0,0,0.8)]"
                                    x-text="playersData[listDragPlayerId]?.name"></span>
                            </div>
                        </template>
                    </div>

                    {{-- CENTER: Available Players sidebar --}}
                    <div class="lg:flex-2 lg:min-w-[280px]" :class="{ 'hidden lg:block': activeLineupTab !== 'squad' }">
                        <x-section-card x-data="{ posTab: 'all' }" title="{{ __('squad.available_players') }}">
                            <x-slot:badge>
                                <span class="text-[10px] text-text-faint" x-text="Object.keys(playersData).length + ' {{ __('squad.players_count') }}'"></span>
                            </x-slot:badge>

                            {{-- Position filter tabs --}}
                            <div class="flex items-center gap-0 px-3 py-2 border-b border-border-default overflow-x-auto scrollbar-hide">
                                @foreach(['all' => __('squad.all'), 'Goalkeeper' => __('squad.goalkeepers_short'), 'Defender' => __('squad.defenders_short'), 'Midfielder' => __('squad.midfielders_short'), 'Forward' => __('squad.forwards_short')] as $key => $label)
                                <x-tab-button size="xs" type="button" @click="posTab = '{{ $key }}'"
                                    x-bind:class="posTab === '{{ $key }}' ? 'text-text-primary border-accent-blue' : 'text-text-muted border-transparent hover:text-text-body'">
                                    {{ $label }}
                                </x-tab-button>
                                @endforeach
                            </div>

                            {{-- Player list --}}
                            <div :class="{ 'select-none': listDragPlayerId }">
                                @foreach([
                                    ['name' => __('squad.goalkeepers'), 'players' => $goalkeepers, 'role' => 'Goalkeeper'],
                                    ['name' => __('squad.defenders'), 'players' => $defenders, 'role' => 'Defender'],
                                    ['name' => __('squad.midfielders'), 'players' => $midfielders, 'role' => 'Midfielder'],
                                    ['name' => __('squad.forwards'), 'players' => $forwards, 'role' => 'Forward'],
                                ] as $group)
                                    @if($group['players']->isNotEmpty())
                                        @foreach($group['players'] as $player)
                                            @php
                                                $isUnavailable = !$player->isAvailable($matchDate, $competitionId);
                                                $unavailabilityReason = $player->getUnavailabilityReason(
                                                    $matchDate,
                                                    $competitionId,
                                                );
                                                $posGroup = \App\Support\PositionMapper::getPositionGroup($player->position);
                                            @endphp
                                            <div
                                                x-show="posTab === 'all' || posTab === '{{ $posGroup }}'"
                                                @click="toggle('{{ $player->id }}', {{ $isUnavailable ? 'true' : 'false' }})"
                                                @mousedown="startListDrag('{{ $player->id }}', $event)"
                                                @mouseenter="hoveredPlayerId = '{{ $player->id }}'"
                                                @mouseleave="hoveredPlayerId = null"
                                                class="available-player px-3 py-2.5 border-b border-border-default"
                                                :class="{
                                                    'bg-accent-blue/10 border-accent-blue/20': isSelected('{{ $player->id }}'),
                                                    'opacity-30': listDragPlayerId === '{{ $player->id }}',
                                                    'opacity-50': !isSelected('{{ $player->id }}') && selectedCount >= 11 && !{{ $isUnavailable ? 'true' : 'false' }},
                                                    'opacity-40 cursor-not-allowed': {{ $isUnavailable ? 'true' : 'false' }} && !isSelected('{{ $player->id }}'),
                                                    'cursor-pointer': !{{ $isUnavailable ? 'true' : 'false' }} || isSelected('{{ $player->id }}')
                                                }"
                                            >
                                                <div class="flex items-center gap-2.5">
                                                    <x-player-avatar :name="$player->name ?? $player->name" :position-group="$posGroup" :number="$player->number" size="sm" />
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-xs font-medium text-text-primary truncate">{{ $player->name }}</span>
                                                            <x-player-unavailable-icon :player="$player" :match-date="$matchDate" :competition-id="$competitionId" :reason="$unavailabilityReason" />
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-0.5">
                                                            <div class="flex items-center gap-0.5">
                                                                @foreach($player->positions as $pos)
                                                                    <x-position-badge :position="$pos" size="sm" />
                                                                @endforeach
                                                            </div>
                                                            <div class="flex items-center gap-1">
                                                                <div class="w-8 h-1 rounded-full bg-surface-600 overflow-hidden">
                                                                    <div class="h-full rounded-full fitness-bar @if($player->fitness >= 80) bg-accent-green @elseif($player->fitness >= 60) bg-accent-gold @elseif($player->fitness >= 40) bg-accent-orange @else bg-accent-red @endif" style="width: {{ $player->fitness }}%"></div>
                                                                </div>
                                                                <span class="text-[8px] text-text-secondary">{{ $player->fitness }}%</span>
                                                            </div>
                                                            <x-morale-indicator :value="$player->morale" class="shrink-0" />
                                                        </div>
                                                    </div>
                                                    <x-rating-badge :value="$player->effective_rating" size="sm" class="shrink-0" />
                                                    <div class="w-5 h-5 rounded-sm border flex items-center justify-center transition-colors shrink-0"
                                                        :class="isSelected('{{ $player->id }}') ? 'border-accent-blue bg-accent-blue' : 'border-border-strong'">
                                                        <svg x-show="isSelected('{{ $player->id }}')" x-cloak class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                @endforeach
                            </div>
                        </x-section-card>

                    </div>

                    {{-- RIGHT: Lineup Overview + Tactical Controls --}}
                    <div :class="{ 'hidden lg:block': activeLineupTab !== 'tactics' }" class="space-y-4 lg:w-64 lg:shrink-0">

                        {{-- Opponent Preview Card --}}
                        <x-section-card title="{{ __('squad.opponent') }}">
                            <div class="px-5 py-4">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <x-team-crest :team="$game->team" class="w-7 h-7 shrink-0" />
                                        <div class="flex flex-col leading-none gap-1 items-start">
                                            <span class="font-heading text-xl font-bold tabular-nums leading-none text-text-primary" x-text="teamAverage || '-'"></span>
                                            <div x-show="averageFitness" class="flex items-center gap-1.5">
                                                <div class="w-8 h-1 rounded-full bg-surface-600 overflow-hidden">
                                                    <div class="h-full rounded-full fitness-bar transition-all"
                                                         :class="averageFitness >= 80 ? 'bg-accent-green' : (averageFitness >= 60 ? 'bg-accent-gold' : (averageFitness >= 40 ? 'bg-accent-orange' : 'bg-accent-red'))"
                                                         :style="'width: ' + (averageFitness || 0) + '%'"></div>
                                                </div>
                                                <span class="text-[8px] text-text-muted w-6 text-right tabular-nums" x-text="(averageFitness || 0) + '%'"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <span class="text-[10px] text-text-secondary shrink-0">{{ __('game.vs') }}</span>

                                    <div class="flex items-center gap-1.5">
                                        <div class="flex flex-col leading-none gap-1 items-end">
                                            <span class="font-heading text-xl font-bold tabular-nums leading-none text-text-primary">{{ $opponentData['teamAverage'] ?: '-' }}</span>
                                            @if(!empty($opponentData['avgFitness']))
                                                <x-fitness-bar :value="$opponentData['avgFitness']" size="xs" class="flex-row-reverse" />
                                            @endif
                                        </div>
                                        <x-team-crest :team="$opponent" class="w-7 h-7 shrink-0" />
                                    </div>
                                </div>

                                {{-- Inline xG Preview (reactive) --}}
                                <template x-if="xgPreview">
                                    <div class="mt-4 pt-3 border-t border-border-default">
                                        <div class="flex items-center justify-center gap-1 text-[10px] font-medium text-text-secondary uppercase tracking-wide mb-2">
                                            <span>{{ __('game.xg_preview') }}</span>
                                            <x-info-icon :tooltip="__('game.xg_explanation')" />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-heading text-xl font-bold tabular-nums shrink-0 w-10 text-left"
                                                  :class="xgPreview.userXG > xgPreview.opponentXG ? 'text-accent-green' : (xgPreview.userXG < xgPreview.opponentXG ? 'text-accent-red' : 'text-text-primary')"
                                                  x-text="xgPreview.userXG.toFixed(2)"></span>
                                            <div class="flex-1 flex h-1.5 rounded-full overflow-hidden gap-0.5">
                                                <div class="h-full rounded-l-full transition-all duration-300"
                                                     :class="xgPreview.userXG >= xgPreview.opponentXG ? 'bg-accent-green' : 'bg-accent-red'"
                                                     :style="'width: ' + ((xgPreview.userXG + xgPreview.opponentXG) > 0 ? (xgPreview.userXG / (xgPreview.userXG + xgPreview.opponentXG) * 100) : 50) + '%'"></div>
                                                <div class="h-full rounded-r-full transition-all duration-300"
                                                     :class="xgPreview.opponentXG >= xgPreview.userXG ? 'bg-accent-red' : 'bg-accent-green'"
                                                     :style="'width: ' + ((xgPreview.userXG + xgPreview.opponentXG) > 0 ? (xgPreview.opponentXG / (xgPreview.userXG + xgPreview.opponentXG) * 100) : 50) + '%'"></div>
                                            </div>
                                            <span class="font-heading text-xl font-bold tabular-nums shrink-0 w-10 text-right"
                                                  :class="xgPreview.opponentXG > xgPreview.userXG ? 'text-accent-red' : (xgPreview.opponentXG < xgPreview.userXG ? 'text-accent-green' : 'text-text-primary')"
                                                  x-text="xgPreview.opponentXG.toFixed(2)"></span>
                                        </div>
                                    </div>
                                </template>

                                <x-secondary-button type="button" size="sm" @click="$dispatch('open-modal', 'opponent-analysis')" class="w-full mt-3 gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15.75 15.75l-2.489-2.489m0 0a3.375 3.375 0 10-4.773-4.773 3.375 3.375 0 004.774 4.774zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ __('app.scout_opponent') }}
                                </x-secondary-button>
                            </div>
                        </x-section-card>

                        {{-- Team Instructions --}}
                        <x-section-card title="{{ __('game.instructions_title') }}">
                            <div class="px-5 py-4 space-y-4">
                                {{-- Tactical impact (combined ATK/DEF deltas from formation + mentality + style + pressing + def line) --}}
                                <template x-if="formationModifiers[selectedFormation]">
                                    <div class="grid grid-cols-2 gap-3 pb-4 border-b border-border-default">
                                        <div>
                                            <div class="text-[10px] font-medium text-text-secondary tracking-wide mb-1">{{ __('squad.attack_xg_label') }}</div>
                                            <div class="font-heading text-xl font-bold tabular-nums"
                                                 :class="attackImpactPct > 0 ? 'text-accent-green' : (attackImpactPct < 0 ? 'text-accent-red' : 'text-text-primary')"
                                                 x-text="(attackImpactPct > 0 ? '+' : '') + attackImpactPct + '%'"></div>
                                        </div>
                                        <div>
                                            <div class="text-[10px] font-medium text-text-secondary tracking-wide mb-1">{{ __('squad.defense_xg_label') }}</div>
                                            <div class="font-heading text-xl font-bold tabular-nums"
                                                 :class="defenseImpactPct > 0 ? 'text-accent-green' : (defenseImpactPct < 0 ? 'text-accent-red' : 'text-text-primary')"
                                                 x-text="(defenseImpactPct > 0 ? '+' : '') + defenseImpactPct + '%'"></div>
                                        </div>
                                    </div>
                                </template>

                                <div class="space-y-3">
                                    {{-- Mentality --}}
                                    <div>
                                        <div class="text-[10px] font-medium text-text-secondary uppercase tracking-wide mb-1">{{ __('squad.mentality') }}</div>
                                        <x-tactical-select model="selectedMentality" options="mentalityOptions" label="{{ __('squad.mentality') }}" summary-field="summary" />
                                    </div>

                                    {{-- Playing Style --}}
                                    <div>
                                        <div class="text-[10px] font-medium text-text-secondary uppercase tracking-wide mb-1">{{ __('game.instructions_in_possession') }}</div>
                                        <x-tactical-select model="selectedPlayingStyle" options="playingStyles" label="{{ __('game.instructions_in_possession') }}" summary-field="summary" />
                                    </div>

                                    {{-- Pressing --}}
                                    <div>
                                        <div class="text-[10px] font-medium text-text-secondary uppercase tracking-wide mb-1">{{ __('game.instructions_out_of_possession') }}</div>
                                        <x-tactical-select model="selectedPressing" options="pressingOptions" label="{{ __('game.instructions_out_of_possession') }}" summary-field="summary" />
                                    </div>

                                    {{-- Defensive Line --}}
                                    <div>
                                        <div class="text-[10px] font-medium text-text-secondary uppercase tracking-wide mb-1">{{ __('squad.defensive_line') }}</div>
                                        <x-tactical-select model="selectedDefLine" options="defensiveLineOptions" label="{{ __('squad.defensive_line') }}" summary-field="summary" />
                                    </div>
                                </div>

                                {{-- Tactical guide link --}}
                                <div class="pt-1">
                                    <x-ghost-button type="button" x-on:click="$dispatch('open-modal', 'tactical-guide')" size="xs">
                                        {{ __('game.tactical_guide_link') }} &rarr;
                                    </x-ghost-button>
                                </div>
                            </div>
                        </x-section-card>
                    </div>
                </div>
            </form>
        </div>

        {{-- Scout Opponent Modal — lazy-loads the analysis partial via AJAX
             on first open so the lineup page doesn't pay for it upfront. --}}
        <x-modal name="opponent-analysis" max-width="4xl">
            <x-modal-header modal-name="opponent-analysis">{{ __('opponent.title', ['team' => $opponent->name]) }}</x-modal-header>
            <div class="p-5 max-h-[85vh] overflow-y-auto"
                 x-data="{
                     loaded: false,
                     loading: false,
                     failed: false,
                     async ensureLoaded() {
                         if (this.loaded || this.loading) return;
                         this.loading = true;
                         this.failed = false;
                         try {
                             const res = await fetch(@js(route('game.opponent-analysis', $game->id)), {
                                 headers: { 'X-Requested-With': 'XMLHttpRequest' },
                             });
                             if (!res.ok) throw new Error('HTTP ' + res.status);
                             this.$refs.body.innerHTML = await res.text();
                             window.Alpine.initTree(this.$refs.body);
                             this.loaded = true;
                         } catch (e) {
                             this.failed = true;
                         } finally {
                             this.loading = false;
                         }
                     },
                 }"
                 x-on:open-modal.window="if ($event.detail === 'opponent-analysis') ensureLoaded()">
                <div x-show="loading" class="text-center text-text-muted text-sm py-12">{{ __('app.loading') }}</div>
                <div x-show="failed" x-cloak class="text-center text-accent-red text-sm py-12">
                    {{ __('app.load_failed') }}
                    <div class="mt-3">
                        <x-secondary-button type="button" size="sm" @click="ensureLoaded()">{{ __('app.retry') }}</x-secondary-button>
                    </div>
                </div>
                <div x-ref="body" x-show="loaded" x-cloak></div>
            </div>
        </x-modal>

        {{-- Save Tactical Preset Modal --}}
        <x-modal name="save-preset" maxWidth="sm">
        <form method="POST" action="{{ route('game.tactical-presets.save', $game->id) }}"
              x-data="{
                presetList: @js($presetsConfig),
                presetName: '',
                applyNow: false,
                savePresetMode: 'new',
                replacePresetId: '',
                initPresetModal() {
                    const ps = this.presetList;
                    if (!ps.length) {
                        this.savePresetMode = 'new';
                        this.replacePresetId = '';
                        this.presetName = '';
                        return;
                    }
                    if (ps.length >= 3) {
                        this.savePresetMode = 'replace';
                        this.replacePresetId = ps[0].id;
                    } else {
                        this.savePresetMode = 'new';
                        this.replacePresetId = '';
                    }
                    this.syncPresetNameFromTarget();
                },
                syncPresetNameFromTarget() {
                    if (this.savePresetMode !== 'replace' || !this.replacePresetId) {
                        return;
                    }
                    const p = this.presetList.find(x => x.id === this.replacePresetId);
                    if (p) {
                        this.presetName = p.name;
                    }
                },
                get presetSubmitDisabled() {
                    if (!this.presetName.trim()) {
                        return true;
                    }
                    if (this.presetList.length >= 3) {
                        return this.savePresetMode !== 'replace' || !this.replacePresetId;
                    }
                    if (this.savePresetMode === 'replace') {
                        return !this.replacePresetId;
                    }
                    return false;
                },
              }"
              x-on:open-modal.window="if ($event.detail === 'save-preset') initPresetModal()"
              @submit="_isSaving = true">
            @csrf
            <div class="p-5">
                <h3 class="text-lg font-semibold text-text-primary mb-4">{{ __('squad.save_preset') }}</h3>

                <template x-if="presetList.length > 0">
                    <div class="mb-4 space-y-2">
                        <p class="text-xs text-text-muted" x-show="presetList.length >= 3">{{ __('squad.preset_replace_required_hint') }}</p>
                        <label class="flex items-center gap-2 cursor-pointer select-none" x-show="presetList.length < 3">
                            <input type="checkbox"
                                x-bind:checked="savePresetMode === 'replace'"
                                @change="savePresetMode = $event.target.checked ? 'replace' : 'new'; replacePresetId = $event.target.checked ? (presetList[0]?.id || '') : ''; syncPresetNameFromTarget()"
                                class="rounded border-border-strong bg-surface-700 text-accent-blue focus:ring-accent-blue focus:ring-offset-0">
                            <span class="text-sm text-text-body">{{ __('squad.preset_overwrite_toggle') }}</span>
                        </label>
                        <div x-show="savePresetMode === 'replace' || presetList.length >= 3">
                            <select id="preset-replace-target" x-model="replacePresetId" @change="syncPresetNameFromTarget()"
                                class="w-full px-3 py-2 bg-surface-700 border border-border-strong rounded-lg text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-transparent">
                                <template x-for="p in presetList" :key="'opt-' + p.id">
                                    <option :value="p.id" x-text="p.name + ' (' + p.formation + ')'"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </template>

                <div class="mb-4">
                    <label for="preset-name" class="block text-sm text-text-secondary mb-1.5">{{ __('squad.preset_name') }}</label>
                    <input type="text" id="preset-name" name="name" x-model="presetName"
                        class="w-full px-3 py-2 bg-surface-700 border border-border-strong rounded-lg text-sm text-text-body placeholder-text-faint focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-transparent"
                        placeholder="{{ __('squad.preset_name_placeholder') }}"
                        maxlength="30" required autofocus>
                </div>

                <template x-if="savePresetMode === 'replace' && replacePresetId">
                    <input type="hidden" name="preset_id" :value="replacePresetId">
                </template>

                <label class="flex items-center gap-2 mb-4 cursor-pointer select-none">
                    <input type="checkbox" name="apply_now" value="1" x-model="applyNow"
                        class="rounded border-border-strong bg-surface-700 text-accent-blue focus:ring-accent-blue focus:ring-offset-0">
                    <span class="text-sm text-text-secondary">{{ __('squad.preset_apply_now') }}</span>
                </label>

                {{-- Hidden fields carrying current lineup state --}}
                <input type="hidden" name="formation" :value="selectedFormation">
                <input type="hidden" name="mentality" :value="selectedMentality">
                <input type="hidden" name="playing_style" :value="selectedPlayingStyle">
                <input type="hidden" name="pressing" :value="selectedPressing">
                <input type="hidden" name="defensive_line" :value="selectedDefLine">
                <template x-for="playerId in selectedPlayers" :key="'preset-' + playerId">
                    <input type="hidden" name="lineup[]" :value="playerId">
                </template>
                <input type="hidden" name="slot_assignments" :value="JSON.stringify(
                    Object.fromEntries(slotAssignments.filter(s => s.player).map(s => [s.id, s.player.id]))
                )">
                <input type="hidden" name="pitch_positions" :value="JSON.stringify(pitchPositions)">

                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" @click="$dispatch('close-modal', 'save-preset')">
                        {{ __('app.cancel') }}
                    </x-secondary-button>
                    <x-primary-button type="submit" color="blue" x-bind:disabled="presetSubmitDisabled">
                        <span x-text="applyNow ? '{{ __('squad.save_and_confirm') }}' : '{{ __('app.confirm') }}'"></span>
                    </x-primary-button>
                </div>
            </div>
        </form>
        </x-modal>
    </div>

    @include('partials.tactical-guide-modal')
    <x-player-detail-modal />

    {{-- Auto-lineup preference modal --}}
    <x-modal name="auto-lineup" maxWidth="sm">
        <div class="p-4 md:p-6" x-data="{ autoLineup: localStorage.getItem('autoLineup') === '1' }">
            <p class="text-sm text-text-body">{{ __('messages.pre_match_auto_select_done') }}</p>
            <label class="mt-4 flex items-start gap-2 cursor-pointer group">
                <input type="checkbox" x-model="autoLineup"
                       @change="localStorage.setItem('autoLineup', autoLineup ? '1' : '0')"
                       class="mt-0.5 rounded border-border-strong bg-surface-700 text-accent-blue focus:ring-accent-blue">
                <span class="text-xs text-text-secondary group-hover:text-text-body transition-colors">{{ __('messages.pre_match_auto_lineup') }}</span>
            </label>
            <div class="mt-5 flex justify-end">
                <x-primary-button type="button" @click="$dispatch('close-modal', 'auto-lineup')">
                    {{ __('app.confirm') }}
                </x-primary-button>
            </div>
        </div>
    </x-modal>
</x-app-layout>
