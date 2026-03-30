{{--
    Shared Pitch Visualization Component
    Used by both the lineup page (mode="lineup") and live match tactical panel (mode="live").

    This component renders within an existing Alpine.js scope and expects the parent to provide:
    - slotAssignments (computed array of slot objects with player data)
    - getEffectivePosition(slotId) method
    - getShirtStyle(role) method
    - getNumberStyle(role) method
    - getInitials(name) method
    - teamColors object

    Mode-specific:
    - lineup: drag-and-drop, grid repositioning, empty slot click-to-assign
    - live: tap-to-select for substitution, energy overlays, no drag-and-drop

    Props:
    @param string $mode - 'lineup' or 'live'
    @param bool $compact - Use compact sizing (for live match panel)
--}}

@props(['mode' => 'lineup', 'compact' => false])

@php
    $isLive = $mode === 'live';
    $isLineup = $mode === 'lineup';
    $aspectClass = $compact ? 'aspect-4/3' : 'aspect-3/4 sm:aspect-2/3 lg:aspect-3/4';
@endphp

<div>
    <div id="{{ $isLive ? 'live-pitch-container' : 'pitch-container' }}"
         class="pitch {{ $aspectClass }} w-full {{ $compact ? '' : 'max-w-lg mx-auto lg:max-w-none' }} relative"
         :style="(positioningSlotId !== null || draggingSlotId !== null) ? 'touch-action: none' : ''"
    >

        {{-- Field area with sideline padding --}}
        <div id="{{ $isLive ? 'live-pitch-field' : 'pitch-field' }}" class="absolute inset-x-[4%] inset-y-[3%]">

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
                                    :style="`left: ${((col-1) / gridConfig.cols) * 100}%; top: ${(1 - (row / gridConfig.rows)) * 100}%; width: ${100 / gridConfig.cols}%; height: ${100 / gridConfig.rows}%; ${(positioningSlotId !== null && state.startsWith('valid')) ? 'cursor: pointer; pointer-events: auto' : ''}`"
                                    :class="{
                                        'bg-blue-500/25': state === 'valid-def',
                                        'bg-emerald-500/25': state === 'valid-mid',
                                        'bg-red-500/25': state === 'valid-fwd',
                                        'bg-amber-500/25': state === 'valid-gk',
                                        [getZoneColorClass('Goalkeeper')]: state === 'valid',
                                        'bg-surface-800/5': state === 'occupied',
                                        'bg-black/15': state === 'invalid',
                                    }"
                                    @click="positioningSlotId !== null && state.startsWith('valid') && handleGridCellClick(col-1, row-1)"
                                ></div>
                            </template>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        {{-- Player Slots --}}
        <template x-for="slot in slotAssignments" :key="'pitch-slot-' + slot.id">
            <div
                class="absolute transform -translate-x-1/2 -translate-y-1/2 transition-all duration-300 group/slot"
                :class="{
                    'opacity-30': draggingSlotId === slot.id,
                    @if($isLive) 'opacity-30': slot.player && livePitchSelectedOutId === slot.player?.id && draggingSlotId !== slot.id, @endif
                }"
                :style="(() => { const pos = getEffectivePosition(slot.id); return pos ? `left: ${pos.x}%; top: ${100 - pos.y}%; z-index: ${positioningSlotId === slot.id ? 20 : 10}` : '' })()"
                @if($isLineup)
                    @mouseenter="$el.style.zIndex = 30"
                    @mouseleave="$el.style.zIndex = positioningSlotId === slot.id ? 20 : 10"
                @endif
            >
                @if($isLineup)
                {{-- Empty Slot (lineup mode only) --}}
                <div
                    x-show="!slot.player"
                    @click="assigningSlotId = assigningSlotId === slot.id ? null : slot.id; positioningSlotId = null; if (assigningSlotId !== null) activeLineupTab = 'squad'"
                    class="w-11 h-11 rounded-full border-2 flex items-center justify-center backdrop-blur-xs cursor-pointer transition-all duration-200"
                    :class="listDragPlayerId
                        ? (listDragNearestSlotId === slot.id
                            ? 'border-white bg-surface-800/30 ring-2 ring-white/60 scale-110 animate-pulse'
                            : 'border-dashed border-white/60 bg-surface-800/15')
                        : (assigningSlotId === slot.id
                            ? 'border-white bg-surface-800/30 ring-2 ring-white/60 scale-110 animate-pulse'
                            : 'border-dashed border-white/40 bg-surface-800/5 hover:border-white/70 hover:bg-surface-800/15')"
                >
                    <span class="text-[10px] font-semibold tracking-wide" :class="assigningSlotId === slot.id ? 'text-white' : 'text-white/60'" x-text="slot.displayLabel"></span>
                </div>
                @endif

                {{-- Filled Slot --}}
                <div
                    x-show="slot.player"
                    class="relative cursor-pointer flex flex-col items-center"
                    @if($isLineup)
                        @click="selectForRepositioning(slot.id)"
                        @mousedown="startDrag(slot.id, $event)"
                        @touchstart="startDrag(slot.id, $event)"
                    @endif
                    @if($isLive)
                        @click="handlePitchPlayerClick(slot)"
                        @mousedown="startDrag(slot.id, $event)"
                        @touchstart="startDrag(slot.id, $event)"
                    @endif
                >
                    {{-- Shirt badge --}}
                    <div
                        class="relative {{ $compact ? 'w-9 h-9 rounded-lg' : 'w-11 h-11 rounded-xl' }} shadow-lg border border-white/20 transform transition-all duration-200 hover:scale-110 hover:shadow-xl"
                        :class="{
                            @if($isLineup)
                                'ring-2 ring-white ring-offset-1 ring-offset-emerald-600 scale-110': positioningSlotId === slot.id,
                                'ring-2 ring-white/70 scale-110 shadow-xl': hoveredPlayerId && hoveredPlayerId === slot.player?.id && positioningSlotId !== slot.id,
                            @endif
                            @if($isLive)
                                'ring-2 ring-red-400 ring-offset-1 ring-offset-red-600/50 scale-110': livePitchSelectedOutId === slot.player?.id && positioningSlotId !== slot.id,
                                'ring-2 ring-green-400 ring-offset-1 ring-offset-green-600/50 scale-105': slot.player?.isPendingSub && livePitchSelectedOutId !== slot.player?.id && positioningSlotId !== slot.id,
                                'ring-2 ring-white ring-offset-1 ring-offset-emerald-600 scale-110': positioningSlotId === slot.id,
                            @endif
                        }"
                        :style="getShirtStyle(slot.role)"
                    >
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="font-bold {{ $compact ? 'text-[10px] leading-none inline-flex items-center justify-center w-6 h-6' : 'text-xs leading-none inline-flex items-center justify-center w-7 h-7' }} rounded-full" :style="getNumberStyle(slot.role)" x-text="slot.player?.number || getInitials(slot.player?.name)"></span>
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

                        @if($isLineup)
                        {{-- Remove button (lineup mode) --}}
                        <button
                            type="button"
                            @mousedown.stop @touchstart.stop
                            @click.stop="removeFromSlot(slot.player?.id); positioningSlotId = null"
                            class="absolute -top-1.5 -left-1.5 w-[18px] h-[18px] rounded-full bg-red-500 text-white flex items-center justify-center shadow-sm transition-opacity duration-150"
                            :class="positioningSlotId === slot.id ? 'opacity-100' : 'opacity-0 group-hover/slot:opacity-100'"
                        >
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                        @endif

                        @if($isLive)
                        {{-- Card indicator (live mode) --}}
                        <span
                            x-show="redCardedPlayerIds.includes(slot.player?.id)"
                            x-cloak
                            class="absolute -top-1.5 -left-1.5 w-3 h-4 rounded-[2px] bg-accent-red shadow-sm border border-black/20 z-10"
                        ></span>
                        <span
                            x-show="isPlayerYellowCarded(slot.player?.id) && !redCardedPlayerIds.includes(slot.player?.id)"
                            x-cloak
                            class="absolute -top-1.5 -left-1.5 w-3 h-4 rounded-[2px] bg-accent-gold shadow-sm border border-black/20 z-10"
                        ></span>
                        @endif

                        @if($isLive)
                        {{-- Energy indicator ring (live mode) --}}
                        <span
                            class="absolute -bottom-0.5 left-1/2 -translate-x-1/2 h-1 rounded-full shadow-sm"
                            :class="getPitchEnergyColor(slot.player)"
                            :style="'width: ' + Math.max(8, Math.round(getPitchPlayerEnergy(slot.player) / 100 * 30)) + 'px'"
                        ></span>
                        @endif

                        @if($isLineup)
                        {{-- Compatibility dot (lineup mode) --}}
                        <span
                            x-show="slot.compatibility > 0 && slot.compatibility < 60"
                            x-cloak
                            class="absolute -bottom-0.5 left-1/2 -translate-x-1/2 w-2 h-2 rounded-full shadow-sm border border-black/20"
                            :class="slot.compatibility < 40 ? 'bg-accent-red' : 'bg-accent-gold'"
                        ></span>
                        @endif
                    </div>

                    {{-- Player name --}}
                    <span class="{{ $compact ? 'mt-0 text-[7px] max-w-[52px]' : 'mt-0.5 text-[8px] max-w-[66px]' }} font-semibold text-white uppercase tracking-wide leading-tight text-center line-clamp-2 break-words drop-shadow-[0_1px_2px_rgba(0,0,0,0.8)]" x-text="slot.player?.name"></span>
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
                    <div class="relative {{ $compact ? 'w-9 h-9 rounded-lg' : 'w-11 h-11 rounded-xl' }} shadow-xl border-2 border-white/30 opacity-80"
                        :style="getShirtStyle(currentSlots.find(s => s.id === draggingSlotId)?.role || 'Midfielder')">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="font-bold {{ $compact ? 'text-[10px] leading-none inline-flex items-center justify-center w-6 h-6' : 'text-xs leading-none inline-flex items-center justify-center w-7 h-7' }} rounded-full"
                                :style="getNumberStyle(currentSlots.find(s => s.id === draggingSlotId)?.role || 'Midfielder')"
                                x-text="(() => { const s = slotAssignments.find(sa => sa.id === draggingSlotId); return s?.player?.number || getInitials(s?.player?.name); })()"></span>
                        </div>
                    </div>
                    <span class="{{ $compact ? 'mt-0 text-[7px] max-w-[52px]' : 'mt-0.5 text-[8px] max-w-[66px]' }} font-semibold text-white uppercase tracking-wide leading-tight text-center line-clamp-2 break-words drop-shadow-[0_1px_2px_rgba(0,0,0,0.8)]"
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

        @if($isLineup)
        {{-- List-drag drop zone overlay (lineup mode only) --}}
        <div
            x-show="listDragPlayerId && listDragOverPitch"
            x-cloak
            class="absolute inset-0 z-[5] rounded-inherit border-2 border-dashed border-accent-green/40 bg-accent-green/5 pointer-events-none transition-opacity duration-200"
        ></div>

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
        @endif
    </div>
</div>
