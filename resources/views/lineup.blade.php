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
        currentSlotAssignments: @js($currentSlotAssignments ?? (object) []),
        gridConfig: @js($gridConfig),
        currentPitchPositions: @js($currentPitchPositions ?? (object) []),
        playersData: @js($playersData),
        formationSlots: @js($formationSlots),
        slotCompatibility: @js($slotCompatibility),
        autoLineupUrl: '{{ route('game.lineup.auto', $game->id) }}',
        teamColors: @js($teamColors),
        formationModifiers: @js($formationModifiers),
        opponentAverage: {{ $opponentData['teamAverage'] ?: 0 }},
        opponentFormation: @js($opponentData['formation'] ?? null),
        opponentMentality: @js($opponentData['mentality'] ?? null),
        userTeamAverage: {{ $userTeamAverage ?: 0 }},
        isHome: @js($isHome),
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
            mentality_defensive: @js(__('squad.mentality_defensive')),
            mentality_balanced: @js(__('squad.mentality_balanced')),
            mentality_attacking: @js(__('squad.mentality_attacking')),
        },
    })">
        <div class="max-w-7xl mx-auto px-4 pb-8">

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
                            <x-secondary-button type="button" @click="quickSelect()">
                                {{ __('squad.auto_select') }}
                            </x-secondary-button>
                            <x-primary-button x-bind:disabled="selectedCount !== 11">
                                {{ __('app.confirm') }}
                            </x-primary-button>
                        </div>
                    </div>

                    <div class="border-t border-border-default"></div>

                    {{-- Formation inline controls --}}
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                        <span class="text-[10px] text-text-muted uppercase tracking-wider shrink-0">{{ __('squad.formation') }}</span>
                        <div class="flex gap-1">
                            <template x-for="option in formationOptions" :key="'fo-' + option.value">
                                <x-pill-button size="sm"
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
                    <div class="lg:flex-2 space-y-4"
                         :class="{ 'hidden lg:block': activeLineupTab !== 'pitch' }">

                        {{-- PITCH VISUALIZATION --}}
                        <div>
                            <div id="pitch-container" class="pitch aspect-3/4 sm:aspect-2/3 lg:aspect-3/4 w-full max-w-lg mx-auto lg:max-w-none relative"
                                :style="(positioningSlotId !== null || draggingSlotId !== null) ? 'touch-action: none' : ''">

                                {{-- Field area with sideline padding --}}
                                <div id="pitch-field" class="absolute inset-x-[4%] inset-y-[3%]">

                                {{-- Sideline border --}}
                                <div class="absolute inset-0 border border-pitch-line pointer-events-none"></div>

                                {{-- Pitch markings --}}
                                <div class="pitch-center-line"></div>
                                <div class="pitch-center-circle"></div>
                                <div class="pitch-box-top"></div>
                                <div class="pitch-box-bottom"></div>
                                <div class="pitch-six-top"></div>
                                <div class="pitch-six-bottom"></div>
                                <div class="pitch-arc-top"></div>
                                <div class="pitch-arc-bottom"></div>
                                <div class="absolute left-1/2 top-1/2 w-2 h-2 rounded-full bg-white/20 -translate-x-1/2 -translate-y-1/2"></div>
                                <div class="pitch-penalty-spot-top"></div>
                                <div class="pitch-penalty-spot-bottom"></div>

                                {{-- Grid Overlay (invisible until repositioning/dragging) --}}
                                <template x-if="gridConfig">
                                    <div class="absolute inset-0 pointer-events-none" :class="{ 'pointer-events-auto': positioningSlotId !== null }">
                                        <template x-if="positioningSlotId !== null || draggingSlotId !== null">
                                            <div class="absolute inset-0">
                                                <template x-for="row in gridConfig.rows" :key="'gr-' + row">
                                                    <template x-for="col in gridConfig.cols" :key="'gc-' + (row-1) + '-' + (col-1)">
                                                        <div
                                                            x-data="{ get state() { return getGridCellState(col-1, row-1) } }"
                                                            class="absolute transition-colors duration-150"
                                                            :style="`left: ${((col-1) / gridConfig.cols) * 100}%; top: ${(1 - (row / gridConfig.rows)) * 100}%; width: ${100 / gridConfig.cols}%; height: ${100 / gridConfig.rows}%; ${(positioningSlotId !== null && state === 'valid') ? 'cursor: pointer; pointer-events: auto' : ''}`"
                                                            :class="{
                                                                [getZoneColorClass(currentSlots.find(s => s.id === (positioningSlotId ?? draggingSlotId))?.role)]: state === 'valid',
                                                                'bg-surface-800/5': state === 'occupied',
                                                                'bg-black/15': state === 'invalid',
                                                            }"
                                                            @click="positioningSlotId !== null && state === 'valid' && handleGridCellClick(col-1, row-1)"
                                                        ></div>
                                                    </template>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Player Slots --}}
                                <template x-for="slot in slotAssignments" :key="slot.id">
                                    <div
                                        class="absolute transform -translate-x-1/2 -translate-y-1/2 transition-all duration-300 group/slot"
                                        :class="{ 'opacity-30': draggingSlotId === slot.id }"
                                        :style="(() => { const pos = getEffectivePosition(slot.id); return pos ? `left: ${pos.x}%; top: ${100 - pos.y}%; z-index: ${positioningSlotId === slot.id ? 20 : 10}` : '' })()"
                                        @mouseenter="$el.style.zIndex = 30"
                                        @mouseleave="$el.style.zIndex = positioningSlotId === slot.id ? 20 : 10"
                                    >
                                        {{-- Empty Slot --}}
                                        <div
                                            x-show="!slot.player"
                                            @click="assigningSlotId = assigningSlotId === slot.id ? null : slot.id; positioningSlotId = null; if (assigningSlotId !== null) activeLineupTab = 'squad'"
                                            class="w-11 h-11 rounded-full border-2 flex items-center justify-center backdrop-blur-xs cursor-pointer transition-all duration-200"
                                            :class="assigningSlotId === slot.id
                                                ? 'border-white bg-surface-800/30 ring-2 ring-white/60 scale-110 animate-pulse'
                                                : 'border-dashed border-white/40 bg-surface-800/5 hover:border-white/70 hover:bg-surface-800/15'"
                                        >
                                            <span class="text-[10px] font-semibold tracking-wide" :class="assigningSlotId === slot.id ? 'text-white' : 'text-white/60'" x-text="slot.displayLabel"></span>
                                        </div>

                                        {{-- Filled Slot --}}
                                        <div
                                            x-show="slot.player"
                                            class="relative cursor-pointer flex flex-col items-center"
                                            @click="selectForRepositioning(slot.id)"
                                            @mousedown="startDrag(slot.id, $event)"
                                            @touchstart="startDrag(slot.id, $event)"
                                        >
                                            {{-- Shirt badge --}}
                                            <div
                                                class="relative w-11 h-11 rounded-xl shadow-lg border border-white/20 transform transition-all duration-200 hover:scale-110 hover:shadow-xl"
                                                :class="{
                                                    'ring-2 ring-white ring-offset-1 ring-offset-emerald-600 scale-110': positioningSlotId === slot.id,
                                                    'ring-2 ring-white/70 scale-110 shadow-xl': hoveredPlayerId && hoveredPlayerId === slot.player?.id && positioningSlotId !== slot.id,
                                                }"
                                                :style="getShirtStyle(slot.role)"
                                            >
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" :style="getNumberStyle(slot.role)" x-text="slot.player?.number || getInitials(slot.player?.name)"></span>
                                                </div>

                                                {{-- OVR badge --}}
                                                <span class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-0.5 rounded-sm text-[9px] font-bold leading-none flex items-center justify-center shadow-sm"
                                                    :class="{
                                                        'bg-accent-green text-white': slot.player?.overallScore >= 80,
                                                        'bg-lime-500 text-white': slot.player?.overallScore >= 70 && slot.player?.overallScore < 80,
                                                        'bg-accent-gold text-white': slot.player?.overallScore >= 60 && slot.player?.overallScore < 70,
                                                        'bg-accent-orange text-white': slot.player?.overallScore < 60,
                                                    }"
                                                    x-text="slot.player?.overallScore"></span>
                                            </div>

                                            {{-- Player name --}}
                                            <span class="mt-0.5 text-[8px] font-semibold text-white uppercase tracking-wide leading-tight text-center max-w-[66px] line-clamp-2 break-words drop-shadow-[0_1px_2px_rgba(0,0,0,0.8)]" x-text="slot.player?.name"></span>
                                        </div>
                                    </div>
                                </template>

                                {{-- Drag ghost --}}
                                <div
                                    x-show="draggingSlotId !== null && dragPosition"
                                    x-cloak
                                    class="absolute transform -translate-x-1/2 -translate-y-1/2 z-40 pointer-events-none flex flex-col items-center"
                                    :style="dragPosition ? `left: ${dragPosition.x}%; top: ${dragPosition.y}%` : ''"
                                >
                                    <template x-if="draggingSlotId !== null">
                                        <div class="flex flex-col items-center">
                                            <div class="relative w-11 h-11 rounded-xl shadow-xl border-2 border-white/30 opacity-80"
                                                :style="getShirtStyle(currentSlots.find(s => s.id === draggingSlotId)?.role || 'Midfielder')">
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full"
                                                        :style="getNumberStyle(currentSlots.find(s => s.id === draggingSlotId)?.role || 'Midfielder')"
                                                        x-text="(() => { const s = slotAssignments.find(sa => sa.id === draggingSlotId); return s?.player?.number || getInitials(s?.player?.name); })()"></span>
                                                </div>
                                            </div>
                                            <span class="mt-0.5 text-[8px] font-semibold text-white uppercase tracking-wide leading-tight text-center max-w-[66px] line-clamp-2 break-words drop-shadow-[0_1px_2px_rgba(0,0,0,0.8)]"
                                                x-text="(() => { const s = slotAssignments.find(sa => sa.id === draggingSlotId); return s?.player?.name; })()"></span>
                                        </div>
                                    </template>
                                </div>

                                </div> {{-- /pitch-field --}}

                                {{-- Grid positioning indicator banner --}}
                                <div
                                    x-show="positioningSlotId !== null"
                                    x-cloak
                                    class="absolute bottom-2 left-1/2 -translate-x-1/2 px-4 py-2 bg-surface-800/95 backdrop-blur-xs rounded-lg shadow-lg text-xs font-medium text-text-body flex items-center gap-2 z-20"
                                >
                                    <span class="w-2 h-2 rounded-full bg-accent-green animate-pulse"></span>
                                    {{ __('squad.drag_or_tap') }}
                                    <x-icon-button size="sm" type="button" @click="positioningSlotId = null" class="ml-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </x-icon-button>
                                </div>

                                {{-- Slot assignment indicator banner --}}
                                <div
                                    x-show="assigningSlotId !== null"
                                    x-cloak
                                    class="absolute bottom-2 left-1/2 -translate-x-1/2 px-4 py-2 bg-surface-800/95 backdrop-blur-xs rounded-lg shadow-lg text-xs font-medium text-text-body flex items-center gap-2 z-20"
                                >
                                    <span class="w-2 h-2 rounded-full bg-accent-blue animate-pulse"></span>
                                    {{ __('squad.select_player_for_slot') }}
                                    <x-icon-button size="sm" type="button" @click="assigningSlotId = null" class="ml-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </x-icon-button>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- CENTER: Available Players sidebar --}}
                    <div class="lg:flex-2 lg:min-w-[280px]" :class="{ 'hidden lg:block': activeLineupTab !== 'squad' }">
                        <div class="bg-surface-800 border border-border-default rounded-xl overflow-hidden" x-data="{ posTab: 'all' }">
                            {{-- Sidebar header --}}
                            <div class="flex items-center justify-between px-4 py-3 border-b border-border-default">
                                <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary">{{ __('squad.available_players') }}</h3>
                                <span class="text-[10px] text-text-faint" x-text="Object.keys(playersData).length + ' {{ __('squad.players_count') }}'"></span>
                            </div>

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
                            <div>
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
                                                $matchData = $matchesMissedMap[$player->id] ?? null;
                                                $unavailabilityReason = $player->getUnavailabilityReason(
                                                    $matchDate,
                                                    $competitionId,
                                                    $matchData['count'] ?? null,
                                                    $matchData['approx'] ?? false,
                                                );
                                                $posAbbrev = \App\Support\PositionMapper::toAbbreviation($player->position);
                                                $posGroup = \App\Support\PositionMapper::getPositionGroup($player->position);
                                            @endphp
                                            <div
                                                x-show="posTab === 'all' || posTab === '{{ $posGroup }}'"
                                                @click="toggle('{{ $player->id }}', {{ $isUnavailable ? 'true' : 'false' }})"
                                                @mouseenter="hoveredPlayerId = '{{ $player->id }}'"
                                                @mouseleave="hoveredPlayerId = null"
                                                class="available-player px-3 py-2.5 border-b border-border-default {{ $isUnavailable ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }}"
                                                :class="{
                                                    'bg-accent-blue/10 border-accent-blue/20': isSelected('{{ $player->id }}'),
                                                    'opacity-50': !isSelected('{{ $player->id }}') && selectedCount >= 11 && !{{ $isUnavailable ? 'true' : 'false' }}
                                                }"
                                            >
                                                <div class="flex items-center gap-2.5">
                                                    <x-player-avatar :name="$player->player->name ?? $player->name" :position-group="$posGroup" :number="$player->number" size="sm" />
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-xs font-medium text-text-primary truncate">{{ $player->name }}</span>
                                                            @if($unavailabilityReason)
                                                                <span class="text-[8px] px-1 py-0.5 rounded-sm bg-red-500/10 text-accent-red font-medium shrink-0">{{ $unavailabilityReason }}</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-center gap-2 mt-0.5">
                                                            <span class="text-[9px] text-text-muted font-heading uppercase">{{ $posAbbrev }}</span>
                                                            <span class="text-[9px] text-text-secondary">{{ $player->overall_score }}</span>
                                                            @if(!$isUnavailable)
                                                            <div class="flex items-center gap-1">
                                                                <div class="w-8 h-1 rounded-full bg-surface-600 overflow-hidden">
                                                                    <div class="h-full rounded-full fitness-bar @if($player->fitness >= 80) bg-accent-green @elseif($player->fitness >= 60) bg-accent-gold @elseif($player->fitness >= 40) bg-accent-orange @else bg-accent-red @endif" style="width: {{ $player->fitness }}%"></div>
                                                                </div>
                                                                <span class="text-[8px] text-text-faint">{{ $player->fitness }}%</span>
                                                            </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @if(!$isUnavailable)
                                                    <div class="w-5 h-5 rounded-sm border flex items-center justify-center transition-colors shrink-0"
                                                        :class="isSelected('{{ $player->id }}') ? 'border-accent-blue bg-accent-blue' : 'border-border-strong'">
                                                        <svg x-show="isSelected('{{ $player->id }}')" x-cloak class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                @endforeach
                            </div>
                        </div>

                    </div>

                    {{-- RIGHT: Lineup Overview + Tactical Controls --}}
                    <div :class="{ 'hidden lg:block': activeLineupTab !== 'tactics' }" class="space-y-4 lg:w-64 lg:shrink-0">

                        {{-- Lineup Overview --}}
                        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                            <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary mb-3">{{ __('squad.lineup_overview') }}</h3>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <p class="text-[10px] text-text-muted uppercase tracking-wider mb-1">{{ __('squad.avg_ovr') }}</p>
                                    <p class="font-heading text-2xl font-bold"
                                       :class="teamAverage >= 75 ? 'text-accent-green' : (teamAverage >= 65 ? 'text-text-primary' : 'text-accent-gold')"
                                       x-text="teamAverage || '-'"></p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-text-muted uppercase tracking-wider mb-1">{{ __('squad.fitness_full') }}</p>
                                    <p class="font-heading text-2xl font-bold"
                                       :class="averageFitness >= 85 ? 'text-accent-green' : (averageFitness >= 70 ? 'text-text-primary' : 'text-accent-gold')"
                                       x-text="averageFitness ? averageFitness + '%' : '-'"></p>
                                </div>
                            </div>

                            {{-- Formation modifier badges --}}
                            <template x-if="formationModifiers[selectedFormation]">
                                <div class="mt-3 pt-3 border-t border-border-default">
                                    <div class="flex items-center gap-3 text-[10px] font-medium">
                                        <span :class="formationModifiers[selectedFormation]?.attack > 0 ? 'text-accent-green' : formationModifiers[selectedFormation]?.attack < 0 ? 'text-accent-red' : 'text-text-secondary'">
                                            ATK <span x-text="(formationModifiers[selectedFormation]?.attack > 0 ? '+' : '') + formationModifiers[selectedFormation]?.attack + '%'"></span>
                                        </span>
                                        <span :class="formationModifiers[selectedFormation]?.defense > 0 ? 'text-accent-green' : formationModifiers[selectedFormation]?.defense < 0 ? 'text-accent-red' : 'text-text-secondary'">
                                            DEF <span x-text="(formationModifiers[selectedFormation]?.defense > 0 ? '+' : '') + formationModifiers[selectedFormation]?.defense + '%'"></span>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Opponent Preview Card --}}
                        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <x-team-crest :team="$game->team" class="w-5 h-5 shrink-0" />
                                    <span class="text-sm font-bold text-text-primary" x-text="teamAverage || '-'"></span>
                                </div>

                                {{-- Advantage badge (reactive) --}}
                                <template x-if="teamAverage && {{ $opponentData['teamAverage'] ?: 0 }}">
                                    <span
                                        class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full shrink-0"
                                        :class="{
                                            'bg-accent-green/10 text-accent-green': teamAverage > {{ $opponentData['teamAverage'] ?: 0 }},
                                            'bg-accent-red/10 text-accent-red': teamAverage < {{ $opponentData['teamAverage'] ?: 0 }},
                                            'bg-surface-700 text-text-secondary': teamAverage === {{ $opponentData['teamAverage'] ?: 0 }}
                                        }"
                                        x-text="teamAverage > {{ $opponentData['teamAverage'] ?: 0 }} ? '+' + (teamAverage - {{ $opponentData['teamAverage'] ?: 0 }}) : (teamAverage < {{ $opponentData['teamAverage'] ?: 0 }} ? (teamAverage - {{ $opponentData['teamAverage'] ?: 0 }}) : '=')"
                                    ></span>
                                </template>
                                <template x-if="!teamAverage || !{{ $opponentData['teamAverage'] ?: 0 }}">
                                    <span class="text-[10px] text-text-secondary">vs</span>
                                </template>

                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm font-bold text-text-primary">{{ $opponentData['teamAverage'] ?: '-' }}</span>
                                    <x-team-crest :team="$opponent" class="w-5 h-5 shrink-0" />
                                </div>
                            </div>

                            @if(!empty($opponentData['formation']))
                                <div class="text-center text-[10px] text-text-muted mt-2">
                                    <span class="font-semibold text-text-body bg-surface-700 px-1.5 py-0.5 rounded-sm">{{ $opponentData['formation'] }}</span>
                                    <span class="text-text-body mx-0.5">&middot;</span>
                                    <span class="font-medium
                                        @if($opponentData['mentality'] === 'defensive') text-accent-blue
                                        @elseif($opponentData['mentality'] === 'attacking') text-accent-red
                                        @else text-text-secondary
                                        @endif">{{ __('squad.mentality_' . $opponentData['mentality']) }}</span>
                                </div>
                            @endif

                            <x-ghost-button type="button" @click="$dispatch('open-modal', 'coach-assistant')" size="xs" class="w-full mt-3">
                                {{ __('squad.coach_full_report') }} &rarr;
                            </x-ghost-button>
                        </div>

                        <div class="bg-surface-800 border border-border-default rounded-xl p-4 space-y-4">

                            {{-- Team Instructions --}}
                            <div class="space-y-3">
                                <h4 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary">{{ __('game.instructions_title') }}</h4>

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
                    </div>
                </div>
            </form>
        </div>

        {{-- Coach Assistant Modal --}}
        <x-modal name="coach-assistant" max-width="lg">
            <x-modal-header modal-name="coach-assistant">{{ __('squad.coach_assistant') }}</x-modal-header>
            <div class="p-5 max-h-[80vh] overflow-y-auto">
                @include('partials.lineup-coach-panel')
            </div>
        </x-modal>
    </div>

    @include('partials.tactical-guide-modal')
    <x-player-detail-modal />
</x-app-layout>
