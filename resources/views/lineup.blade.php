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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    {{-- Errors --}}
                    @if ($errors->any())
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <ul class="text-sm text-red-600">
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
                        {{-- Slot assignment hidden inputs --}}
                        <template x-for="slot in slotAssignments" :key="'sa-' + slot.id">
                            <input x-show="slot.player" type="hidden" :name="'slot_assignments[' + slot.id + ']'" :value="slot.player?.id">
                        </template>
                        {{-- Pitch position hidden inputs --}}
                        <template x-for="(pos, slotId) in pitchPositions" :key="'pp-' + slotId">
                            <input type="hidden" :name="'pitch_positions[' + slotId + ']'" :value="pos[0] + ',' + pos[1]">
                        </template>

                        {{-- Top Bar: Formation, Stats, Actions --}}
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6 md:p-4 md:bg-slate-50 md:rounded-lg sticky top-0 z-10">
                            <div class="flex flex-wrap items-center gap-2 md:gap-6">
                                {{-- Formation Selector --}}
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-slate-700">{{ __('squad.formation') }}:</label>
                                    <x-select-input
                                        x-model="selectedFormation"
                                        @change="updateAutoLineup()"
                                        class="font-semibold"
                                    >
                                        @foreach($formations as $formation)
                                            <option value="{{ $formation->value }}">{{ $formation->label() }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>

                                {{-- Mentality Selector --}}
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-slate-700">{{ __('squad.mentality') }}:</label>
                                    <x-select-input
                                        x-model="selectedMentality"
                                        class="font-semibold"
                                    >
                                        @foreach($mentalities as $mentality)
                                            <option value="{{ $mentality->value }}">{{ $mentality->label() }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>

                            </div>

                            <div class="flex items-center gap-2 md:gap-3 md:shrink-0 border-t border-slate-200 pt-3 md:border-0 md:pt-0">
                                <button type="button" @click="clearSelection()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-200 rounded transition-colors">
                                    {{ __('app.clear') }}
                                </button>
                                <x-secondary-button type="button" @click="quickSelect()">
                                    {{ __('squad.auto_select') }}
                                </x-secondary-button>
                                <x-primary-button x-bind:disabled="selectedCount !== 11" class="flex-1 md:flex-none justify-center">
                                    {{ __('app.confirm') }}<span class="md:hidden" x-text="'&nbsp;(' + selectedCount + '/11)'"></span>
                                </x-primary-button>
                            </div>
                        </div>

                        {{-- Team Instructions Panel --}}
                        <div class="mb-4 border border-slate-200 rounded-lg overflow-hidden" x-data="{ instructionsOpen: false }">
                            <button type="button" @click="instructionsOpen = !instructionsOpen"
                                class="w-full flex items-center justify-between px-4 py-3 bg-slate-50 hover:bg-slate-100 transition-colors">
                                <span class="text-sm font-semibold text-slate-700 flex items-center gap-1.5">
                                    {{ __('game.instructions_title') }}
                                    <svg class="w-3.5 h-3.5 text-slate-300" fill="currentColor" viewBox="0 0 512 512"><path d="M256 512a256 256 0 1 0 0-512 256 256 0 1 0 0 512zm0-336c-17.7 0-32 14.3-32 32 0 13.3-10.7 24-24 24s-24-10.7-24-24c0-44.2 35.8-80 80-80s80 35.8 80 80c0 47.2-36 67.2-56 74.5l0 3.8c0 13.3-10.7 24-24 24s-24-10.7-24-24l0-8.1c0-20.5 14.8-35.2 30.1-40.2 6.4-2.1 13.2-5.5 18.2-10.3 4.3-4.2 7.7-10 7.7-19.6 0-17.7-14.3-32-32-32zM224 368a32 32 0 1 1 64 0 32 32 0 1 1 -64 0z"/></svg>
                                </span>
                                <svg class="w-4 h-4 text-slate-500 transition-transform" :class="instructionsOpen && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="instructionsOpen" x-collapse class="px-4 py-4 space-y-4">
                                {{-- In Possession --}}
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ __('game.instructions_in_possession') }}</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <template x-for="style in playingStyles" :key="style.value">
                                            <button type="button" @click="selectedPlayingStyle = style.value"
                                                :class="selectedPlayingStyle === style.value
                                                    ? 'bg-sky-100 text-sky-800 border-sky-300'
                                                    : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'"
                                                class="px-3 py-2 rounded-lg border-2 text-sm font-medium min-h-[44px] transition-colors"
                                                x-text="style.label"
                                                x-tooltip="style.tooltip">
                                            </button>
                                        </template>
                                    </div>
                                    <p class="mt-1.5 text-xs text-slate-500"
                                       x-text="playingStyles.find(s => s.value === selectedPlayingStyle)?.summary"></p>
                                </div>

                                {{-- Out of Possession --}}
                                <div>
                                    <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ __('game.instructions_out_of_possession') }}</h4>

                                    {{-- Pressing --}}
                                    <div class="mb-3">
                                        <div class="grid grid-cols-3 gap-2">
                                            <template x-for="p in pressingOptions" :key="p.value">
                                                <button type="button" @click="selectedPressing = p.value"
                                                    :class="selectedPressing === p.value
                                                        ? 'bg-sky-100 text-sky-800 border-sky-300'
                                                        : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'"
                                                    class="px-3 py-2 rounded-lg border-2 text-sm font-medium min-h-[44px] transition-colors"
                                                    x-text="p.label"
                                                    x-tooltip="p.tooltip">
                                                </button>
                                            </template>
                                        </div>
                                        <p class="mt-1.5 text-xs text-slate-500"
                                           x-text="pressingOptions.find(p => p.value === selectedPressing)?.summary"></p>
                                    </div>

                                    {{-- Defensive Line --}}
                                    <div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <template x-for="d in defensiveLineOptions" :key="d.value">
                                                <button type="button" @click="selectedDefLine = d.value"
                                                    :class="selectedDefLine === d.value
                                                        ? 'bg-sky-100 text-sky-800 border-sky-300'
                                                        : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'"
                                                    class="px-3 py-2 rounded-lg border-2 text-sm font-medium min-h-[44px] transition-colors"
                                                    x-text="d.label"
                                                    x-tooltip="d.tooltip">
                                                </button>
                                            </template>
                                        </div>
                                        <p class="mt-1.5 text-xs text-slate-500"
                                           x-text="defensiveLineOptions.find(d => d.value === selectedDefLine)?.summary"></p>
                                    </div>
                                </div>

                                {{-- Tactical Guide button --}}
                                <div class="pt-2 border-t border-slate-100 text-right">
                                    <button type="button" x-on:click="$dispatch('open-modal', 'tactical-guide')" class="text-xs text-sky-600 hover:text-sky-800 transition-colors">
                                        {{ __('game.tactical_guide_link') }} &rarr;
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Mobile Tab Switcher --}}
                        <div class="flex lg:hidden border-b border-slate-200 mb-4">
                            <button type="button" @click="activeLineupTab = 'squad'"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-center border-b-2 transition-colors"
                                :class="activeLineupTab === 'squad' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500'">
                                {{ __('app.squad') }}
                            </button>
                            <button type="button" @click="activeLineupTab = 'pitch'"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-center border-b-2 transition-colors"
                                :class="activeLineupTab === 'pitch' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500'">
                                {{ __('squad.pitch') }}
                            </button>
                            <button type="button" @click="activeLineupTab = 'coach'"
                                class="flex-1 px-4 py-2.5 text-sm font-medium text-center border-b-2 transition-colors"
                                :class="activeLineupTab === 'coach' ? 'border-sky-500 text-sky-600' : 'border-transparent text-slate-500'">
                                {{ __('squad.coach_assistant') }}
                            </button>
                        </div>

                        {{-- Main Content: Pitch + Player List --}}
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            {{-- Pitch Visualization --}}

                            <div class="col-span-1 lg:sticky lg:top-[100px] lg:self-start" :class="{ 'hidden lg:block': activeLineupTab !== 'pitch' }">
                                <div id="pitch-container" class="bg-emerald-600 rounded-lg p-4 relative aspect-[3/4]"
                                    :style="(positioningSlotId !== null || draggingSlotId !== null) ? 'touch-action: none' : ''">
                                    {{-- Pitch markings --}}
                                    <div class="absolute inset-4 border-2 border-emerald-400/50 rounded">
                                        {{-- Goal area (top) --}}
                                        <div class="absolute top-0 left-1/2 -ml-12 w-24 h-8 border-2 border-t-0 border-emerald-400/50"></div>
                                        {{-- Penalty area (top) --}}
                                        <div class="absolute top-0 left-1/2 -ml-20 w-40 h-16 border-2 border-t-0 border-emerald-400/50"></div>
                                        {{-- Center line --}}
                                        <div class="absolute left-0 right-0 top-1/2 border-t-2 border-emerald-400/50"></div>
                                        {{-- Center circle --}}
                                        <div class="absolute left-1/2 top-1/2 w-16 h-16 -ml-8 -mt-8 border-2 border-emerald-400/50 rounded-full"></div>
                                        {{-- Goal area (bottom) --}}
                                        <div class="absolute bottom-0 left-1/2 -ml-12 w-24 h-8 border-2 border-b-0 border-emerald-400/50"></div>
                                        {{-- Penalty area (bottom) --}}
                                        <div class="absolute bottom-0 left-1/2 -ml-20 w-40 h-16 border-2 border-b-0 border-emerald-400/50"></div>
                                    </div>

                                    {{-- Field area (aligned with sidelines) --}}
                                    <div id="pitch-field" class="absolute inset-4">

                                    {{-- Grid Overlay --}}
                                    <template x-if="gridConfig">
                                        <div class="absolute inset-0 pointer-events-none" :class="{ 'pointer-events-auto': positioningSlotId !== null }">
                                            {{-- Grid lines (vertical) --}}
                                            <template x-for="col in gridConfig.cols - 1" :key="'gv-' + col">
                                                <div class="absolute top-0 bottom-0 border-l border-white/10"
                                                    :style="`left: ${(col / gridConfig.cols) * 100}%`"></div>
                                            </template>
                                            {{-- Grid lines (horizontal) --}}
                                            <template x-for="row in gridConfig.rows - 1" :key="'gh-' + row">
                                                <div class="absolute left-0 right-0 border-t border-white/10"
                                                    :style="`top: ${(row / gridConfig.rows) * 100}%`"></div>
                                            </template>
                                            {{-- Grid outer edges (right + bottom) --}}
                                            <div class="absolute top-0 bottom-0 right-0 border-r border-white/10"></div>
                                            <div class="absolute left-0 right-0 bottom-0 border-b border-white/10"></div>

                                            {{-- Clickable zone cells (shown when positioning a player) --}}
                                            <template x-if="positioningSlotId !== null || draggingSlotId !== null">
                                                <div class="absolute inset-0">
                                                    <template x-for="row in gridConfig.rows" :key="'gr-' + row">
                                                        <template x-for="col in gridConfig.cols" :key="'gc-' + (row-1) + '-' + (col-1)">
                                                            <div
                                                                x-data="{ get state() { return getGridCellState(col-1, row-1) } }"
                                                                class="absolute transition-colors duration-150 border-t border-l"
                                                                :style="`left: ${((col-1) / gridConfig.cols) * 100}%; top: ${(1 - (row / gridConfig.rows)) * 100}%; width: ${100 / gridConfig.cols}%; height: ${100 / gridConfig.rows}%; ${(positioningSlotId !== null && state === 'valid') ? 'cursor: pointer; pointer-events: auto' : ''}`"
                                                                :class="{
                                                                    [getZoneColorClass(currentSlots.find(s => s.id === (positioningSlotId ?? draggingSlotId))?.role)]: state === 'valid',
                                                                    'bg-white/5 border-white/5': state === 'occupied',
                                                                    'bg-black/15 border-transparent': state === 'invalid',
                                                                    'border-transparent': state === 'neutral',
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
                                            {{-- Empty Slot (clickable for assignment) --}}
                                            <div
                                                x-show="!slot.player"
                                                @click="assigningSlotId = assigningSlotId === slot.id ? null : slot.id; positioningSlotId = null; if (assigningSlotId !== null) activeLineupTab = 'squad'"
                                                class="w-11 h-11 rounded-full border-2 flex items-center justify-center backdrop-blur-sm cursor-pointer transition-all duration-200"
                                                :class="assigningSlotId === slot.id
                                                    ? 'border-white bg-white/30 ring-2 ring-white/60 scale-110 animate-pulse'
                                                    : 'border-dashed border-white/40 bg-white/5 hover:border-white/70 hover:bg-white/15'"
                                            >
                                                <span class="text-[10px] font-semibold tracking-wide" :class="assigningSlotId === slot.id ? 'text-white' : 'text-white/60'" x-text="slot.displayLabel"></span>
                                            </div>

                                            {{-- Filled Slot (clickable for reassignment or repositioning) --}}
                                            <div
                                                x-show="slot.player"
                                                class="group relative cursor-pointer"
                                                @click="selectForRepositioning(slot.id)"
                                                @mousedown="startDrag(slot.id, $event)"
                                                @touchstart="startDrag(slot.id, $event)"
                                            >
                                                {{-- Main player badge --}}
                                                <div
                                                    class="relative w-11 h-11 rounded-xl shadow-lg border border-white/20 transform transition-all duration-200 hover:scale-110 hover:shadow-xl"
                                                    :class="{
                                                        'ring-2 ring-white ring-offset-1 ring-offset-emerald-600 scale-110': positioningSlotId === slot.id,
                                                        'ring-2 ring-white/70 scale-110 shadow-xl': hoveredPlayerId && hoveredPlayerId === slot.player?.id && positioningSlotId !== slot.id,
                                                    }"
                                                    :style="getShirtStyle(slot.role)"
                                                >
                                                    {{-- Number or Initials --}}
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" :style="getNumberStyle(slot.role)" x-text="slot.player?.number || getInitials(slot.player?.name)"></span>
                                                    </div>
                                                </div>

                                                {{-- Hover tooltip --}}
                                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-slate-900/95 backdrop-blur-sm text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-50 shadow-xl">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-semibold" x-text="slot.player?.name"></span>
                                                        <span class="px-1.5 py-0.5 bg-white/15 rounded font-bold text-[10px]" x-text="slot.player?.overallScore"></span>
                                                    </div>
                                                    <div class="flex items-center gap-2 mt-1 text-slate-300">
                                                        <span x-text="slot.displayLabel"></span>
                                                        <span class="text-slate-500">·</span>
                                                        <span :class="getCompatibilityDisplay(slot.player?.position, slot.label).class" x-text="getCompatibilityDisplay(slot.player?.position, slot.label).label"></span>
                                                    </div>
                                                    {{-- Arrow --}}
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900/95"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Drag ghost badge (follows cursor during drag) --}}
                                    <div
                                        x-show="draggingSlotId !== null && dragPosition"
                                        x-cloak
                                        class="absolute transform -translate-x-1/2 -translate-y-1/2 z-40 pointer-events-none"
                                        :style="dragPosition ? `left: ${dragPosition.x}%; top: ${dragPosition.y}%` : ''"
                                    >
                                        <template x-if="draggingSlotId !== null">
                                            <div class="w-11 h-11 rounded-xl shadow-xl border-2 border-white/50 opacity-80"
                                                :style="getShirtStyle(currentSlots.find(s => s.id === draggingSlotId)?.role || 'Midfielder')">
                                                <div class="absolute inset-0 flex items-center justify-center">
                                                    <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full"
                                                        :style="getNumberStyle(currentSlots.find(s => s.id === draggingSlotId)?.role || 'Midfielder')"
                                                        x-text="(() => { const s = slotAssignments.find(sa => sa.id === draggingSlotId); return s?.player?.number || getInitials(s?.player?.name); })()"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    </div> {{-- /pitch-field --}}

                                    {{-- Grid positioning indicator banner --}}
                                    <div
                                        x-show="positioningSlotId !== null"
                                        x-cloak
                                        class="absolute bottom-2 left-1/2 -translate-x-1/2 px-4 py-2 bg-white/95 backdrop-blur-sm rounded-lg shadow-lg text-xs font-medium text-slate-700 flex items-center gap-2 z-20"
                                    >
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                        {{ __('squad.drag_or_tap') }}
                                        <button type="button" @click="positioningSlotId = null" class="ml-1 text-slate-400 hover:text-slate-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>

                                    {{-- Slot assignment indicator banner --}}
                                    <div
                                        x-show="assigningSlotId !== null"
                                        x-cloak
                                        class="absolute bottom-2 left-1/2 -translate-x-1/2 px-4 py-2 bg-white/95 backdrop-blur-sm rounded-lg shadow-lg text-xs font-medium text-slate-700 flex items-center gap-2 z-20"
                                    >
                                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                        {{ __('squad.select_player_for_slot') }}
                                        <button type="button" @click="assigningSlotId = null" class="ml-1 text-slate-400 hover:text-slate-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Coach Assistant Panel (desktop: below pitch) --}}
                                @include('partials.lineup-coach-panel', ['class' => 'hidden lg:block mt-4'])

                            </div>

                            {{-- Player List --}}
                            <div class="lg:col-span-2 overflow-x-auto" :class="{ 'hidden lg:block': activeLineupTab !== 'squad' }">
                                <table class="w-full text-sm">
                                    <thead class="text-left text-sm border-b sticky top-0 bg-white">
                                        <tr>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="py-2"></th>
                                            <th class="font-semibold py-2 text-center w-16 hidden md:table-cell">{{ __('squad.technical_full') }}</th>
                                            <th class="font-semibold py-2 text-center w-16 hidden md:table-cell">{{ __('squad.physical_full') }}</th>
                                            <th class="font-semibold py-2 text-center w-16">{{ __('squad.fitness_full') }}</th>
                                            <th class="font-semibold py-2 text-center w-16">{{ __('squad.morale_full') }}</th>
                                            <th class="font-semibold py-2 w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach([
                                            ['name' => __('squad.goalkeepers'), 'players' => $goalkeepers, 'role' => 'Goalkeeper'],
                                            ['name' => __('squad.defenders'), 'players' => $defenders, 'role' => 'Defender'],
                                            ['name' => __('squad.midfielders'), 'players' => $midfielders, 'role' => 'Midfielder'],
                                            ['name' => __('squad.forwards'), 'players' => $forwards, 'role' => 'Forward'],
                                        ] as $group)
                                            @if($group['players']->isNotEmpty())
                                                <tr class="bg-slate-200">
                                                    <td colspan="8" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                                        {{ $group['name'] }}
                                                        <span class="font-normal text-slate-400">
                                                            ({{ __('squad.need') }} <span x-text="currentSlots.filter(s => s.role === '{{ $group['role'] }}').length"></span>)
                                                        </span>
                                                    </td>
                                                </tr>
                                                @foreach($group['players'] as $player)
                                                    @php
                                                        $isUnavailable = !$player->isAvailable($matchDate, $competitionId);
                                                        $unavailabilityReason = $player->getUnavailabilityReason($matchDate, $competitionId);
                                                    @endphp
                                                    <tr
                                                        @click="toggle('{{ $player->id }}', {{ $isUnavailable ? 'true' : 'false' }})"
                                                        @mouseenter="hoveredPlayerId = '{{ $player->id }}'"
                                                        @mouseleave="hoveredPlayerId = null"
                                                        class="border-b border-slate-200 transition-colors
                                                            @if($isUnavailable)
                                                                text-slate-400 cursor-not-allowed
                                                            @else
                                                                cursor-pointer hover:bg-slate-50
                                                            @endif"
                                                        :class="{
                                                            'bg-sky-50': isSelected('{{ $player->id }}'),
                                                            'opacity-50': !isSelected('{{ $player->id }}') && selectedCount >= 11 && !{{ $isUnavailable ? 'true' : 'false' }}
                                                        }"
                                                    >
                                                        {{-- Checkbox --}}
                                                        <td class="py-2 text-center">
                                                            @if(!$isUnavailable)
                                                                <div
                                                                    class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors mx-auto"
                                                                    :class="isSelected('{{ $player->id }}') ? 'border-sky-500 bg-sky-500' : 'border-slate-300'"
                                                                >
                                                                    <svg x-show="isSelected('{{ $player->id }}')" x-cloak class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                                    </svg>
                                                                </div>
                                                            @endif
                                                        </td>
                                                        {{-- Position --}}
                                                        <td class="py-2 text-center">
                                                            <x-position-badge :position="$player->position" />
                                                        </td>
                                                        {{-- Name --}}
                                                        <td class="py-2">
                                                            <div class="flex items-center gap-2">
                                                                <button type="button" @click.stop="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $player->id]) }}')" class="p-1 text-slate-300 rounded hover:text-slate-500 transition-colors shrink-0">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                                        <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </button>
                                                                <span class="text-xs text-slate-400 w-4 text-right hidden md:inline">{{ $player->number ?? '-' }}</span>
                                                                @if($player->nationality_flag)
                                                                    <img src="/flags/{{ $player->nationality_flag['code'] }}.svg" class="w-4 h-3 rounded-sm shadow-sm hidden md:inline" title="{{ $player->nationality_flag['name'] }}">
                                                                @endif
                                                                <div class="font-medium @if($isUnavailable) text-slate-400 @else text-slate-900 @endif">
                                                                    {{ $player->name }}
                                                                </div>
                                                            </div>
                                                            @if($unavailabilityReason)
                                                                <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                                            @endif
                                                        </td>
                                                        {{-- Technical --}}
                                                        <td class="py-2 px-2 w-16 hidden md:table-cell">
                                                            <x-ability-bar :value="$player->technical_ability" size="sm" class="text-xs font-medium justify-center @if($player->technical_ability >= 80) text-green-600 @elseif($player->technical_ability >= 70) text-lime-600 @elseif($player->technical_ability < 60) text-slate-400 @endif" />
                                                        </td>
                                                        {{-- Physical --}}
                                                        <td class="py-2 px-2 w-16 hidden md:table-cell">
                                                            <x-ability-bar :value="$player->physical_ability" size="sm" class="text-xs font-medium justify-center @if($player->physical_ability >= 80) text-green-600 @elseif($player->physical_ability >= 70) text-lime-600 @elseif($player->physical_ability < 60) text-slate-400 @endif" />
                                                        </td>
                                                        {{-- Fitness --}}
                                                        <td class="py-2 px-2 w-16">
                                                            <x-ability-bar :value="$player->fitness" :max="100" size="sm" class="text-xs font-medium justify-center @if($player->fitness >= 90) text-green-600 @elseif($player->fitness >= 80) text-lime-600 @elseif($player->fitness < 70) text-amber-600 @endif" />
                                                        </td>
                                                        {{-- Morale --}}
                                                        <td class="py-2 px-2 w-16">
                                                            <x-ability-bar :value="$player->morale" :max="100" size="sm" class="text-xs font-medium justify-center @if($player->morale >= 85) text-green-600 @elseif($player->morale >= 75) text-lime-600 @elseif($player->morale < 65) text-amber-600 @endif" />
                                                        </td>
                                                        {{-- Overall --}}
                                                        <td class="py-2 pr-3 text-center">
                                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold
                                                                @if($player->overall_score >= 80) bg-emerald-500 text-white
                                                                @elseif($player->overall_score >= 70) bg-lime-500 text-white
                                                                @elseif($player->overall_score >= 60) bg-amber-500 text-white
                                                                @else bg-slate-300 text-slate-700
                                                                @endif">{{ $player->overall_score }}</span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Coach Assistant Panel (mobile: own tab) --}}
                            <div :class="{ 'hidden': activeLineupTab !== 'coach' }" class="lg:hidden">
                                @include('partials.lineup-coach-panel')
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @include('partials.tactical-guide-modal')
    <x-player-detail-modal />
</x-app-layout>
