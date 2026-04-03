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
        opponentPlayingStyle: @js($opponentData['playingStyle'] ?? 'balanced'),
        opponentPressing: @js($opponentData['pressing'] ?? 'standard'),
        opponentDefLine: @js($opponentData['defensiveLine'] ?? 'normal'),
        xgConfig: @js($xgConfig),
        userTeamAverage: {{ $userTeamAverage ?: 0 }},
        isHome: @js($isHome),
        presets: @js($presetsConfig),
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
                            <x-secondary-button type="button" @click="quickSelect(); $dispatch('open-modal', 'auto-lineup')">
                                {{ __('squad.auto_select') }}
                            </x-secondary-button>
                            <x-primary-button x-bind:disabled="selectedCount !== 11">
                                {{ __('app.confirm') }}
                            </x-primary-button>
                        </div>
                    </div>

                    <div class="border-t border-border-default"></div>

                    {{-- Saved tactical presets --}}
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide" x-show="presets.length > 0 || {{ $tacticalPresets->count() < 3 ? 'true' : 'false' }}">
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
                            @if($tacticalPresets->count() < 3)
                                <button type="button"
                                    @click="$dispatch('open-modal', 'save-preset')"
                                    x-bind:disabled="selectedCount !== 11"
                                    class="flex items-center gap-1 px-2.5 py-1.5 rounded-md border border-dashed border-border-default text-sm text-text-muted hover:text-text-body hover:border-border-strong transition-colors shrink-0 min-h-[36px] disabled:opacity-40 disabled:cursor-not-allowed">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                    {{ __('squad.save_preset') }}
                                </button>
                            @endif
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
                                                class="available-player px-3 py-2.5 border-b border-border-default {{ $isUnavailable ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' }}"
                                                :class="{
                                                    'bg-accent-blue/10 border-accent-blue/20': isSelected('{{ $player->id }}'),
                                                    'opacity-30': listDragPlayerId === '{{ $player->id }}',
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
                                                            <x-position-badge :position="$player->position" size="sm" />
                                                            @if(!$isUnavailable)
                                                            <div class="flex items-center gap-1">
                                                                <div class="w-8 h-1 rounded-full bg-surface-600 overflow-hidden">
                                                                    <div class="h-full rounded-full fitness-bar @if($player->fitness >= 80) bg-accent-green @elseif($player->fitness >= 60) bg-accent-gold @elseif($player->fitness >= 40) bg-accent-orange @else bg-accent-red @endif" style="width: {{ $player->fitness }}%"></div>
                                                                </div>
                                                                <span class="text-[8px] text-text-faint">{{ $player->fitness }}%</span>
                                                            </div>
                                                            <x-morale-indicator :value="$player->morale" class="shrink-0" />
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <x-rating-badge :value="$player->overall_score" size="sm" class="shrink-0" />
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

                            {{-- Inline xG Preview (reactive) --}}
                            <template x-if="xgPreview">
                                <div class="mt-3 bg-surface-700/50 border border-border-strong rounded-lg p-3">
                                    <div class="text-[10px] font-semibold text-text-secondary uppercase tracking-wide mb-2 text-center">{{ __('game.xg_preview') }}</div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex-1 text-center">
                                            <div class="text-2xl font-bold tabular-nums"
                                                 :class="xgPreview.userXG > xgPreview.opponentXG ? 'text-accent-green' : (xgPreview.userXG < xgPreview.opponentXG ? 'text-accent-red' : 'text-text-primary')"
                                                 x-text="xgPreview.userXG.toFixed(2)"></div>
                                            <div class="text-[10px] text-text-muted mt-0.5">{{ __('game.xg_your_team') }}</div>
                                        </div>
                                        <div class="text-text-secondary text-xs font-medium shrink-0">xG</div>
                                        <div class="flex-1 text-center">
                                            <div class="text-2xl font-bold tabular-nums"
                                                 :class="xgPreview.opponentXG > xgPreview.userXG ? 'text-accent-red' : (xgPreview.opponentXG < xgPreview.userXG ? 'text-accent-green' : 'text-text-primary')"
                                                 x-text="xgPreview.opponentXG.toFixed(2)"></div>
                                            <div class="text-[10px] text-text-muted mt-0.5">{{ __('game.xg_opponent') }}</div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <div class="flex h-1.5 rounded-full overflow-hidden gap-0.5">
                                            <div class="h-full rounded-l-full transition-all duration-300"
                                                 :class="xgPreview.userXG >= xgPreview.opponentXG ? 'bg-accent-green' : 'bg-accent-red'"
                                                 :style="'width: ' + ((xgPreview.userXG + xgPreview.opponentXG) > 0 ? (xgPreview.userXG / (xgPreview.userXG + xgPreview.opponentXG) * 100) : 50) + '%'"></div>
                                            <div class="h-full rounded-r-full transition-all duration-300"
                                                 :class="xgPreview.opponentXG >= xgPreview.userXG ? 'bg-accent-red' : 'bg-accent-green'"
                                                 :style="'width: ' + ((xgPreview.userXG + xgPreview.opponentXG) > 0 ? (xgPreview.opponentXG / (xgPreview.userXG + xgPreview.opponentXG) * 100) : 50) + '%'"></div>
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-text-secondary mt-2 text-center leading-relaxed">{{ __('game.xg_explanation') }}</p>
                                </div>
                            </template>

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

        {{-- Save Tactical Preset Modal --}}
        <x-modal name="save-preset" maxWidth="sm">
        <form method="POST" action="{{ route('game.tactical-presets.save', $game->id) }}"
              x-data="{ presetName: '', applyNow: false }"
              @submit="_isSaving = true">
            @csrf
            <div class="p-5">
                <h3 class="text-lg font-semibold text-text-primary mb-4">{{ __('squad.save_preset') }}</h3>

                <div class="mb-4">
                    <label for="preset-name" class="block text-sm text-text-secondary mb-1.5">{{ __('squad.preset_name') }}</label>
                    <input type="text" id="preset-name" name="name" x-model="presetName"
                        class="w-full px-3 py-2 bg-surface-700 border border-border-strong rounded-lg text-sm text-text-body placeholder-text-faint focus:outline-none focus:ring-2 focus:ring-accent-blue focus:border-transparent"
                        placeholder="{{ __('squad.preset_name_placeholder') }}"
                        maxlength="30" required autofocus>
                </div>

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
                    <x-primary-button type="submit" color="blue" x-bind:disabled="!presetName.trim()">
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
