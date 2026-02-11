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
        selectedMentality: @js($currentMentality),
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

            // First pass: assign players with matching position group and compatibility > 0
            sortedSlots.forEach(slot => {
                let bestPlayer = null;
                let bestScore = -1;

                selectedPlayerData.forEach(player => {
                    if (assigned.has(player.id)) return;

                    // Only consider players whose position group matches the slot's role
                    // (Defenders in defense, Midfielders in midfield, etc.)
                    if (player.positionGroup !== slot.role) return;

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

            // Second pass: fill any remaining empty slots with unassigned players (even with 0 compatibility)
            const emptySlots = slots.filter(s => !s.player);
            const unassignedPlayers = selectedPlayerData.filter(p => !assigned.has(p.id));

            emptySlots.forEach((slot, index) => {
                if (unassignedPlayers[index]) {
                    const player = unassignedPlayers[index];
                    const compatibility = this.getSlotCompatibility(player.position, slot.label);
                    slot.player = { ...player, compatibility };
                    slot.compatibility = compatibility;
                }
            });

            return slots;
        },

        getSlotCompatibility(position, slotCode) {
            return this.slotCompatibility[slotCode]?.[position] ?? 0;
        },

        getCompatibilityDisplay(position, slotCode) {
            const score = this.getSlotCompatibility(position, slotCode);
            if (score >= 100) return { label: '{{ __('squad.natural') }}', class: 'text-green-600', ring: 'ring-green-500', score };
            if (score >= 80) return { label: '{{ __('squad.very_good') }}', class: 'text-emerald-600', ring: 'ring-emerald-500', score };
            if (score >= 60) return { label: '{{ __('squad.good') }}', class: 'text-lime-600', ring: 'ring-lime-500', score };
            if (score >= 40) return { label: '{{ __('squad.okay') }}', class: 'text-yellow-600', ring: 'ring-yellow-500', score };
            if (score >= 20) return { label: '{{ __('squad.poor') }}', class: 'text-orange-500', ring: 'ring-orange-500', score };
            return { label: '{{ __('squad.unsuitable') }}', class: 'text-red-600', ring: 'ring-red-500', score };
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
                'Defender': 'bg-blue-600',
                'Midfielder': 'bg-emerald-600',
                'Forward': 'bg-red-600',
            }[role] || 'bg-slate-500';
        },

        removeFromSlot(playerId) {
            this.selectedPlayers = this.selectedPlayers.filter(p => p !== playerId);
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
                'Defender': 'from-blue-500 to-blue-700',
                'Midfielder': 'from-emerald-500 to-emerald-700',
                'Forward': 'from-red-500 to-red-700',
            }[role] || 'from-slate-400 to-slate-600';
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
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

                    <form method="POST" action="{{ route('game.lineup.save', [$game->id, $match->id]) }}">
                        @csrf

                        {{-- Hidden inputs --}}
                        <template x-for="playerId in selectedPlayers" :key="playerId">
                            <input type="hidden" name="players[]" :value="playerId">
                        </template>
                        <input type="hidden" name="formation" :value="selectedFormation">
                        <input type="hidden" name="mentality" :value="selectedMentality">

                        {{-- Top Bar: Formation, Stats, Actions --}}
                        <div class="flex items-center justify-between mb-6 p-4 bg-slate-50 rounded-lg sticky top-0 z-10">
                            <div class="flex items-center gap-6">
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


                                {{-- Selection Count --}}
                                <div class="flex items-center gap-2 px-3 py-1.5 rounded-md" :class="selectedCount === 11 ? 'bg-green-100' : 'bg-slate-200'">
                                    <span class="font-semibold" :class="selectedCount === 11 ? 'text-green-700' : 'text-slate-700'" x-text="selectedCount"></span>
                                    <span class="text-slate-500">/ 11</span>
                                </div>

                                {{-- Team Average with Opponent Comparison --}}
                                <div class="flex items-center gap-3 px-3 py-1.5 bg-slate-200 rounded-md">
                                    <div class="flex items-center gap-1.5">
                                        <img src="{{ $game->team->image }}" class="w-6 h-6" alt="{{ $game->team->name }}">
                                        <span class="font-semibold text-slate-900" x-text="teamAverage || '-'"></span>
                                    </div>
                                    <span class="text-slate-400">vs</span>
                                    <div class="flex items-center gap-1.5">
                                        <img src="{{ $opponent->image }}" class="w-6 h-6" alt="{{ $opponent->name }}">
                                        <span class="font-semibold {{ $opponentData['teamAverage'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ $opponentData['teamAverage'] ?: '-' }}</span>
                                    </div>
                                    {{-- Advantage indicator --}}
                                    <template x-if="teamAverage && {{ $opponentData['teamAverage'] }}">
                                        <span
                                            class="text-xs font-medium px-1.5 py-0.5 rounded"
                                            :class="{
                                                'bg-green-100 text-green-700': teamAverage > {{ $opponentData['teamAverage'] }},
                                                'bg-red-100 text-red-700': teamAverage < {{ $opponentData['teamAverage'] }},
                                                'bg-slate-100 text-slate-600': teamAverage === {{ $opponentData['teamAverage'] }}
                                            }"
                                            x-text="teamAverage > {{ $opponentData['teamAverage'] }} ? '+' + (teamAverage - {{ $opponentData['teamAverage'] }}) : (teamAverage < {{ $opponentData['teamAverage'] }} ? (teamAverage - {{ $opponentData['teamAverage'] }}) : '=')"
                                        ></span>
                                    </template>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <button type="button" @click="clearSelection()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-200 rounded transition-colors">
                                    {{ __('app.clear') }}
                                </button>
                                <x-secondary-button type="button" @click="quickSelect()">
                                    {{ __('squad.auto_select') }}
                                </x-secondary-button>
                                <x-primary-button x-bind:disabled="selectedCount !== 11">
                                    {{ __('app.confirm') }}
                                </x-primary-button>
                            </div>
                        </div>

                        {{-- Main Content: Pitch + Player List --}}
                        <div class="grid grid-cols-1 grid-cols-3 gap-6">
                            {{-- Pitch Visualization --}}

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
                                                    {{-- Number or Initials --}}
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="text-white font-semibold text-sm tracking-tight drop-shadow-sm" x-text="slot.player?.number || getInitials(slot.player?.name)"></span>
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

                                {{-- Opponent Scout Card --}}
                                <div class="mt-4 bg-slate-50 rounded-lg p-4 border border-slate-200">
                                    <div class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ __('squad.opponent') }}</div>

                                    {{-- Team Info --}}
                                    <div class="flex items-center gap-3 mb-3">
                                        <img src="{{ $opponent->image }}" class="w-10 h-10" alt="{{ $opponent->name }}">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $opponent->name }}</div>
                                            <div class="text-sm text-slate-600">
                                                {{ __('squad.team_rating') }}: <span class="font-semibold">{{ $opponentData['teamAverage'] ?: '-' }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Form --}}
                                    @if(count($opponentData['form']) > 0)
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="text-xs text-slate-500">{{ __('game.form') }}:</span>
                                            <div class="flex gap-1">
                                                @foreach($opponentData['form'] as $result)
                                                    <span class="w-5 h-5 rounded-full text-xs font-semibold flex items-center justify-center
                                                        @if($result === 'W') bg-green-500 text-white
                                                        @elseif($result === 'D') bg-slate-400 text-white
                                                        @else bg-red-500 text-white @endif">
                                                        {{ $result }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Key Info Row --}}
                                    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs">
                                        {{-- Top Scorer --}}
                                        @if($opponentData['topScorer'])
                                            <div class="flex items-center gap-1">
                                                <span class="text-slate-500">{{ __('squad.top_scorer') }}:</span>
                                                <span class="font-medium text-slate-700">{{ $opponentData['topScorer']['name'] }}</span>
                                                <span class="text-slate-500">({{ $opponentData['topScorer']['goals'] }})</span>
                                            </div>
                                        @endif

                                        {{-- Unavailable --}}
                                        @if($opponentData['injuredCount'] > 0 || $opponentData['suspendedCount'] > 0)
                                            <div class="flex items-center gap-2">
                                                @if($opponentData['injuredCount'] > 0)
                                                    <span class="flex items-center gap-1 text-amber-600">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                        </svg>
                                                        {{ $opponentData['injuredCount'] }} {{ __('squad.injured') }}
                                                    </span>
                                                @endif
                                                @if($opponentData['suspendedCount'] > 0)
                                                    <span class="flex items-center gap-1 text-red-600">
                                                        <span class="w-2.5 h-3 bg-red-500 rounded-sm"></span>
                                                        {{ $opponentData['suspendedCount'] }} {{ __('squad.suspended') }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Player List --}}
                            <div class="col-span-2 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-left border-b sticky top-0 bg-white">
                                        <tr>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2">{{ __('app.name') }}</th>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2 text-center w-8">{{ __('squad.overall') }}</th>
                                            <th class="font-semibold py-2 text-center w-8">{{ __('squad.technical') }}</th>
                                            <th class="font-semibold py-2 text-center w-8">{{ __('squad.physical') }}</th>
                                            <th class="font-semibold py-2 text-center w-8">{{ __('squad.fitness') }}</th>
                                            <th class="font-semibold py-2 text-center w-8">{{ __('squad.morale') }}</th>
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
                                                    <td colspan="9" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
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
                                                                <span class="text-xs text-slate-400 w-4 text-right">{{ $player->number ?? '-' }}</span>
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
                                                                    x-text="getPlayerSlot('{{ $player->id }}') + (getCompatibilityDisplay('{{ $player->position }}', getPlayerSlot('{{ $player->id }}')).label !== '{{ __('squad.natural') }}' ? ' (' + getCompatibilityDisplay('{{ $player->position }}', getPlayerSlot('{{ $player->id }}')).label + ')' : '')"
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
                                                        <td class="py-2 text-center text-slate-400">
                                                            {{ $player->technical_ability }}
                                                        </td>
                                                        {{-- Physical --}}
                                                        <td class="py-2 text-center text-slate-400">
                                                            {{ $player->physical_ability }}
                                                        </td>
                                                        {{-- Fitness --}}
                                                        <td class="py-2 text-center @if($player->fitness < 70) text-yellow-500 @elseif($player->fitness < 50) text-red-500 @else text-slate-400 @endif">
                                                            {{ $player->fitness }}
                                                        </td>
                                                        {{-- Morale --}}
                                                        <td class="py-2 text-center @if($player->morale < 60) text-red-500 @elseif($player->morale < 70) text-yellow-500 @else text-slate-400 @endif">
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
