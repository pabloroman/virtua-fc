@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch $match */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$match"></x-game-header>
    </x-slot>

    <div x-data="{
        selectedPlayers: @js($currentLineup ?? []),
        selectedFormation: @js($currentFormation),
        autoLineup: @js($autoLineup ?? []),
        playersData: @js($playersData),
        formationSlots: @js($formationSlots),
        slotCompatibility: @js($slotCompatibility),

        get selectedCount() { return this.selectedPlayers.length },
        get currentSlots() { return this.formationSlots[this.selectedFormation] || [] },

        get teamAverage() {
            if (this.selectedPlayers.length === 0) return 0;
            let total = 0;
            this.selectedPlayers.forEach(id => {
                if (this.playersData[id]) {
                    total += this.playersData[id].overallScore;
                }
            });
            return Math.round(total / this.selectedPlayers.length);
        },

        get slotAssignments() {
            // Map selected players to slots based on slot compatibility scores
            const slots = this.currentSlots.map(slot => ({ ...slot, player: null, compatibility: 0 }));
            const assigned = new Set();

            // Get all selected players
            const selectedPlayerData = this.selectedPlayers
                .map(id => this.playersData[id])
                .filter(p => p);

            const rolePriority = { 'Goalkeeper': 0, 'Forward': 1, 'Defender': 2, 'Midfielder': 3 };
            const sortedSlots = [...slots].sort((a, b) => {
                const aPriority = rolePriority[a.role] ?? 99;
                const bPriority = rolePriority[b.role] ?? 99;
                if (aPriority !== bPriority) return aPriority - bPriority;

                // Within same role, sort by specificity (fewer compatible positions first)
                const aCompat = Object.keys(this.slotCompatibility[a.label] || {}).length;
                const bCompat = Object.keys(this.slotCompatibility[b.label] || {}).length;
                return aCompat - bCompat;
            });

            // Assign best player to each slot
            sortedSlots.forEach(slot => {
                let bestPlayer = null;
                let bestScore = -1;

                selectedPlayerData.forEach(player => {
                    if (assigned.has(player.id)) return;

                    const compatibility = this.getSlotCompatibility(player.position, slot.label);
                    if (compatibility === 0) return;

                    // Weighted score: 70% player rating, 30% compatibility
                    const weightedScore = (player.overallScore * 0.7) + (compatibility * 0.3);

                    if (weightedScore > bestScore) {
                        bestScore = weightedScore;
                        bestPlayer = { ...player, compatibility };
                    }
                });

                // Find the original slot and assign
                const originalSlot = slots.find(s => s.id === slot.id);
                if (originalSlot && bestPlayer) {
                    originalSlot.player = bestPlayer;
                    originalSlot.compatibility = bestPlayer.compatibility;
                    assigned.add(bestPlayer.id);
                }
            });

            return slots;
        },

        getSlotCompatibility(position, slotCode) {
            return this.slotCompatibility[slotCode]?.[position] ?? 0;
        },

        getCompatibilityDisplay(position, slotCode) {
            const score = this.getSlotCompatibility(position, slotCode);
            if (score >= 100) return { label: 'Natural', class: 'text-green-600', ring: 'ring-green-500', score };
            if (score >= 80) return { label: 'Very Good', class: 'text-emerald-600', ring: 'ring-emerald-500', score };
            if (score >= 60) return { label: 'Good', class: 'text-lime-600', ring: 'ring-lime-500', score };
            if (score >= 40) return { label: 'Okay', class: 'text-yellow-600', ring: 'ring-yellow-500', score };
            if (score >= 20) return { label: 'Poor', class: 'text-orange-500', ring: 'ring-orange-500', score };
            return { label: 'Unsuitable', class: 'text-red-600', ring: 'ring-red-500', score };
        },

        isSelected(id) { return this.selectedPlayers.includes(id) },

        toggle(id, isUnavailable) {
            if (isUnavailable) return;
            if (this.isSelected(id)) {
                this.selectedPlayers = this.selectedPlayers.filter(p => p !== id)
            } else if (this.selectedCount < 11) {
                this.selectedPlayers.push(id)
            }
        },

        quickSelect() { this.selectedPlayers = [...this.autoLineup] },
        clearSelection() { this.selectedPlayers = [] },

        async updateAutoLineup() {
            try {
                const response = await fetch(`{{ route('game.lineup.auto', [$game->id, $match->id]) }}?formation=${this.selectedFormation}`);
                const data = await response.json();
                this.autoLineup = data.autoLineup;
            } catch (e) {
                console.error('Failed to fetch auto lineup', e);
            }
        },

        getPositionColor(role) {
            return {
                'Goalkeeper': 'bg-amber-500',
                'Defender': 'bg-blue-500',
                'Midfielder': 'bg-green-500',
                'Forward': 'bg-red-500',
            }[role] || 'bg-slate-500';
        },

        removeFromSlot(playerId) {
            this.selectedPlayers = this.selectedPlayers.filter(p => p !== playerId);
        },

        // Get best matching slot label for a player's position
        getNaturalSlot(position) {
            const mapping = {
                'Goalkeeper': 'GK',
                'Centre-Back': 'CB',
                'Left-Back': 'LB',
                'Right-Back': 'RB',
                'Defensive Midfield': 'DM',
                'Central Midfield': 'CM',
                'Attacking Midfield': 'AM',
                'Left Midfield': 'LM',
                'Right Midfield': 'RM',
                'Left Winger': 'LW',
                'Right Winger': 'RW',
                'Centre-Forward': 'ST',
                'Second Striker': 'ST',
            };
            return mapping[position] || 'CM';
        },

        // Find which slot a player is assigned to
        getPlayerSlot(playerId) {
            const assignment = this.slotAssignments.find(s => s.player?.id === playerId);
            return assignment?.label || null;
        },

        getInitials(name) {
            if (!name) return '??';
            const parts = name.trim().split(/\s+/);
            if (parts.length === 1) {
                // Single name: take first 2 characters
                return parts[0].substring(0, 2).toUpperCase();
            }
            // Multiple names: first letter of first name + first letter of last name
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        },

        // Get gradient colors for position
        getPositionGradient(role) {
            return {
                'Goalkeeper': 'from-amber-400 to-amber-600',
                'Defender': 'from-blue-400 to-blue-600',
                'Midfielder': 'from-emerald-400 to-emerald-600',
                'Forward': 'from-rose-400 to-rose-600',
            }[role] || 'from-slate-400 to-slate-600';
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
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

                    <form method="POST" action="{{ route('game.lineup.save', [$game->id, $match->id]) }}">
                        @csrf

                        {{-- Hidden inputs --}}
                        <template x-for="playerId in selectedPlayers" :key="playerId">
                            <input type="hidden" name="players[]" :value="playerId">
                        </template>
                        <input type="hidden" name="formation" :value="selectedFormation">

                        {{-- Top Bar: Formation, Stats, Actions --}}
                        <div class="flex items-center justify-between mb-6 p-4 bg-slate-50 rounded-lg sticky top-0 z-10">
                            <div class="flex items-center gap-6">
                                {{-- Formation Selector --}}
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-slate-700">Formation:</label>
                                    <select
                                        x-model="selectedFormation"
                                        @change="updateAutoLineup()"
                                        class="text-sm font-semibold border-slate-300 rounded-md focus:border-sky-500 focus:ring-sky-500"
                                    >
                                        @foreach($formations as $formation)
                                            <option value="{{ $formation->value }}">{{ $formation->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>


                                {{-- Selection Count --}}
                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-md" :class="selectedCount === 11 ? 'bg-green-100' : 'bg-slate-200'">
                                    <span class="font-semibold" :class="selectedCount === 11 ? 'text-green-700' : 'text-slate-700'" x-text="selectedCount"></span>
                                    <span class="text-slate-500">/ 11</span>
                                </div>

                                {{-- Team Average --}}
                                <div class="flex items-center gap-2 px-3 py-1.5 bg-slate-200 rounded-md">
                                    <span class="text-sm text-slate-600">Team Rating:</span>
                                    <span class="font-semibold text-slate-900" x-text="teamAverage || '-'"></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="button" @click="clearSelection()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-200 rounded transition-colors">
                                    Clear
                                </button>
                                <button type="button" @click="quickSelect()" class="px-4 py-2 text-sm bg-slate-200 text-slate-700 hover:bg-slate-300 rounded transition-colors">
                                    Auto Select
                                </button>
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-white uppercase tracking-wide hover:bg-red-700 focus:bg-red-700 active:bg-red-900 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="selectedCount !== 11"
                                >
                                    Confirm
                                </button>
                            </div>
                        </div>

                        {{-- Main Content: Pitch + Player List --}}
                        <div class="grid grid-cols-1 grid-cols-3 gap-6">
                            {{-- Pitch Visualization --}}

{{--                            <div class="relative w-[600px] h-[400px] bg-emerald-600 border-4 border-white mx-auto my-10 rounded-sm shadow-lg">--}}

{{--                                <!-- Midline -->--}}
{{--                                <div class="absolute top-0 bottom-0 left-1/2 w-0.5 bg-white transform -translate-x-1/2"></div>--}}

{{--                                <!-- Center Circle -->--}}
{{--                                <div class="absolute top-1/2 left-1/2 w-24 h-24 border-2 border-white rounded-full transform -translate-x-1/2 -translate-y-1/2"></div>--}}

{{--                                <!-- Penalty Area (Left) -->--}}
{{--                                <div class="absolute top-1/4 bottom-1/4 left-0 w-16 border-2 border-white border-l-0"></div>--}}

{{--                                <!-- Penalty Area (Right) -->--}}
{{--                                <div class="absolute top-1/4 bottom-1/4 right-0 w-16 border-2 border-white border-r-0"></div>--}}

{{--                                <!-- Goal Area (Left) -->--}}
{{--                                <div class="absolute top-[35%] bottom-[35%] left-0 w-6 border-2 border-white border-l-0"></div>--}}

{{--                                <!-- Goal Area (Right) -->--}}
{{--                                <div class="absolute top-[35%] bottom-[35%] right-0 w-6 border-2 border-white border-r-0"></div>--}}

{{--                            </div>--}}

                            <div class="col-span-1">
                                <div class="bg-emerald-600 rounded-lg p-4 relative" style="aspect-ratio: 3/4;">
                                    {{-- Pitch markings --}}
                                    <div class="absolute inset-4 border-2 border-emerald-400/50 rounded">
                                        {{-- Goal area (top) --}}
                                        <div class="absolute top-0 left-1/2 -ml-12 w-24 h-8 border-2 border-t-0 border-emerald-400/50"></div>
                                        {{-- Penalty area (toop) --}}
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

                                    {{-- Player Slots --}}
                                    <template x-for="slot in slotAssignments" :key="slot.id">
                                        <div
                                            class="absolute transform -translate-x-1/2 -translate-y-1/2 transition-all duration-300"
                                            :style="`left: ${slot.x}%; top: ${100 - slot.y}%`"
                                        >
                                            {{-- Empty Slot --}}
                                            <div
                                                x-show="!slot.player"
                                                class="w-11 h-11 rounded-full border-2 border-dashed border-white/40 flex items-center justify-center backdrop-blur-sm bg-white/5"
                                            >
                                                <span class="text-[10px] text-white/60 font-semibold tracking-wide" x-text="slot.label"></span>
                                            </div>

                                            {{-- Filled Slot - Modern card style --}}
                                            <div
                                                x-show="slot.player"
                                                class="group relative cursor-pointer"
                                                @click="removeFromSlot(slot.player?.id)"
                                            >
                                                {{-- Main player badge --}}
                                                <div
                                                    class="relative w-11 h-11 rounded-xl bg-gradient-to-br shadow-lg transform transition-all duration-200 hover:scale-110 hover:shadow-xl"
                                                    :class="getPositionGradient(slot.role)"
                                                >
                                                    {{-- Initials --}}
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="text-white font-semibold text-sm tracking-tight drop-shadow-sm" x-text="getInitials(slot.player?.name)"></span>
                                                    </div>
                                                    {{-- Compatibility indicator dot --}}
                                                    <div
                                                        class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-emerald-600"
                                                        :class="{
                                                            'bg-green-400': getCompatibilityDisplay(slot.player?.position, slot.label).score >= 100,
                                                            'bg-lime-400': getCompatibilityDisplay(slot.player?.position, slot.label).score >= 60 && getCompatibilityDisplay(slot.player?.position, slot.label).score < 100,
                                                            'bg-yellow-400': getCompatibilityDisplay(slot.player?.position, slot.label).score >= 40 && getCompatibilityDisplay(slot.player?.position, slot.label).score < 60,
                                                            'bg-orange-400': getCompatibilityDisplay(slot.player?.position, slot.label).score >= 20 && getCompatibilityDisplay(slot.player?.position, slot.label).score < 40,
                                                            'bg-red-400': getCompatibilityDisplay(slot.player?.position, slot.label).score < 20,
                                                        }"
                                                        x-show="getCompatibilityDisplay(slot.player?.position, slot.label).score < 100"
                                                    ></div>
                                                    {{-- Overall rating badge --}}
                                                    <div class="absolute -bottom-1 left-1/2 -translate-x-1/2 px-1.5 py-0.5 bg-slate-900/80 rounded text-[9px] font-semibold text-white shadow-sm">
                                                        <span x-text="slot.player?.overallScore"></span>
                                                    </div>
                                                </div>

                                                {{-- Hover tooltip --}}
                                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-slate-900/95 backdrop-blur-sm text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-20 shadow-xl">
                                                    <div class="font-semibold" x-text="slot.player?.name"></div>
                                                    <div class="flex items-center gap-2 mt-1 text-slate-300">
                                                        <span x-text="slot.label"></span>
                                                        <span class="text-slate-500">·</span>
                                                        <span :class="getCompatibilityDisplay(slot.player?.position, slot.label).class" x-text="getCompatibilityDisplay(slot.player?.position, slot.label).label"></span>
                                                    </div>
                                                    {{-- Arrow --}}
                                                    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900/95"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Position Slots Summary --}}
                                <div class="mt-4 grid grid-cols-4 gap-2 text-center text-xs">
                                    <template x-for="[role, count] in Object.entries({
                                        'Goalkeeper': currentSlots.filter(s => s.role === 'Goalkeeper').length,
                                        'Defender': currentSlots.filter(s => s.role === 'Defender').length,
                                        'Midfielder': currentSlots.filter(s => s.role === 'Midfielder').length,
                                        'Forward': currentSlots.filter(s => s.role === 'Forward').length,
                                    })" :key="role">
                                        <div class="p-2 rounded" :class="getPositionColor(role).replace('bg-', 'bg-') + '/20'">
                                            <div class="font-semibold" :class="getPositionColor(role).replace('bg-', 'text-').replace('-500', '-700')" x-text="role.substring(0, 3).toUpperCase()"></div>
                                            <div class="text-slate-600">
                                                <span x-text="slotAssignments.filter(s => s.role === role && s.player).length"></span>/<span x-text="count"></span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Player List --}}
                            <div class="col-span-2 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-left border-b sticky top-0 bg-white">
                                        <tr class="text-slate-900">
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2">Name</th>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2 text-center w-12">OVR</th>
                                            <th class="font-semibold py-2 text-center w-12">TEC</th>
                                            <th class="font-semibold py-2 text-center w-12">PHY</th>
                                            <th class="font-semibold py-2 text-center w-12">FIT</th>
                                            <th class="font-semibold py-2 text-center w-12">MOR</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach([
                                            ['name' => 'Goalkeepers', 'players' => $goalkeepers, 'role' => 'Goalkeeper'],
                                            ['name' => 'Defenders', 'players' => $defenders, 'role' => 'Defender'],
                                            ['name' => 'Midfielders', 'players' => $midfielders, 'role' => 'Midfielder'],
                                            ['name' => 'Forwards', 'players' => $forwards, 'role' => 'Forward'],
                                        ] as $group)
                                            @if($group['players']->isNotEmpty())
                                                <tr class="bg-slate-100">
                                                    <td colspan="9" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                                        {{ $group['name'] }}
                                                        <span class="font-normal text-slate-400">
                                                            (need <span x-text="currentSlots.filter(s => s.role === '{{ $group['role'] }}').length"></span>)
                                                        </span>
                                                    </td>
                                                </tr>
                                                @foreach($group['players'] as $player)
                                                    @php
                                                        $isUnavailable = !$player->isAvailable($matchDate, $matchday);
                                                        $unavailabilityReason = $player->getUnavailabilityReason($matchDate, $matchday);
                                                        $positionDisplay = $player->position_display;
                                                    @endphp
                                                    <tr
                                                        @click="toggle('{{ $player->id }}', {{ $isUnavailable ? 'true' : 'false' }})"
                                                        class="border-b border-slate-100 transition-colors
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
                                                            <span class="inline-flex items-center justify-center w-8 h-8 rounded text-xs font-semibold {{ $positionDisplay['bg'] }} {{ $positionDisplay['text'] }}">
                                                                {{ $positionDisplay['abbreviation'] }}
                                                            </span>
                                                        </td>
                                                        {{-- Name --}}
                                                        <td class="py-2">
                                                            <div class="flex items-center gap-2">
                                                                <div class="font-medium @if($isUnavailable) text-slate-400 @else text-slate-900 @endif">
                                                                    {{ $player->name }}
                                                                </div>
                                                                @if($player->nationality_flag)
                                                                    <img src="/flags/{{ $player->nationality_flag['code'] }}.svg" class="w-4 h-3 rounded-sm shadow-sm" title="{{ $player->nationality_flag['name'] }}">
                                                                @endif
                                                            </div>
                                                            @if($unavailabilityReason)
                                                                <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                                            @endif
                                                        </td>
                                                        {{-- Compatibility indicator (only when selected, shows assigned slot) --}}
                                                        <td class="py-2 text-right">
                                                            <template x-if="isSelected('{{ $player->id }}') && getPlayerSlot('{{ $player->id }}')">
                                                                <span
                                                                    class="text-xs font-medium px-1.5 py-0.5 rounded whitespace-nowrap"
                                                                    :class="getCompatibilityDisplay('{{ $player->position }}', getPlayerSlot('{{ $player->id }}')).class"
                                                                    x-text="getPlayerSlot('{{ $player->id }}') + (getCompatibilityDisplay('{{ $player->position }}', getPlayerSlot('{{ $player->id }}')).label !== 'Natural' ? ' (' + getCompatibilityDisplay('{{ $player->position }}', getPlayerSlot('{{ $player->id }}')).label + ')' : '')"
                                                                ></span>
                                                            </template>
                                                        </td>
                                                        {{-- Overall --}}
                                                        <td class="py-2 text-center">
                                                            <span class="font-semibold @if($player->overall_score >= 80) text-green-600 @elseif($player->overall_score >= 70) text-lime-600 @elseif($player->overall_score >= 60) text-yellow-600 @else text-slate-500 @endif">
                                                                {{ $player->overall_score }}
                                                            </span>
                                                        </td>
                                                        {{-- Technical --}}
                                                        <td class="py-2 text-center @if($player->technical_ability >= 80) text-green-600 @elseif($player->technical_ability >= 70) text-lime-600 @elseif($player->technical_ability < 60) text-slate-400 @endif">
                                                            {{ $player->technical_ability }}
                                                        </td>
                                                        {{-- Physical --}}
                                                        <td class="py-2 text-center @if($player->physical_ability >= 80) text-green-600 @elseif($player->physical_ability >= 70) text-lime-600 @elseif($player->physical_ability < 60) text-slate-400 @endif">
                                                            {{ $player->physical_ability }}
                                                        </td>
                                                        {{-- Fitness --}}
                                                        <td class="py-2 text-center @if($player->fitness < 70) text-yellow-600 @elseif($player->fitness < 50) text-red-500 @endif">
                                                            {{ $player->fitness }}
                                                        </td>
                                                        {{-- Morale --}}
                                                        <td class="py-2 text-center @if($player->morale < 60) text-red-500 @elseif($player->morale < 70) text-yellow-600 @endif">
                                                            {{ $player->morale }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
