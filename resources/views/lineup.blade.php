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
        autoLineup: @js($autoLineup ?? []),
        currentSlotAssignments: @js($currentSlotAssignments ?? (object) []),
        playersData: @js($playersData),
        formationSlots: @js($formationSlots),
        slotCompatibility: @js($slotCompatibility),
        autoLineupUrl: '{{ route('game.lineup.auto', $game->id) }}',
        teamColors: @js($teamColors),
        translations: {
            natural: '{{ __('squad.natural') }}',
            veryGood: '{{ __('squad.very_good') }}',
            good: '{{ __('squad.good') }}',
            okay: '{{ __('squad.okay') }}',
            poor: '{{ __('squad.poor') }}',
            unsuitable: '{{ __('squad.unsuitable') }}',
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

                    {{-- How it works toggle --}}
                    <div x-data="{ open: false }" class="mb-6">
                        <button @click="open = !open" class="flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-slate-400 shrink-0">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ __('squad.lineup_help_toggle') }}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 transition-transform" :class="open ? 'rotate-180' : ''">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-show="open" x-transition class="mt-3 bg-slate-50 border border-slate-200 rounded-lg p-4 text-sm">
                            <p class="text-slate-600 mb-4">{{ __('squad.lineup_help_intro') }}</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                {{-- Formation & Mentality --}}
                                <div>
                                    <p class="font-semibold text-slate-700 mb-2">{{ __('squad.lineup_help_formation_title') }}</p>
                                    <p class="text-slate-500 mb-2">{{ __('squad.lineup_help_formation_desc') }}</p>
                                    <ul class="space-y-2">
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-200 text-emerald-700 text-xs font-bold">&#10003;</span>
                                            <span class="text-slate-600">{{ __('squad.lineup_help_compatibility_natural') }}</span>
                                        </li>
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-sky-200 text-sky-700 text-xs font-bold">~</span>
                                            <span class="text-slate-600">{{ __('squad.lineup_help_compatibility_good') }}</span>
                                        </li>
                                        <li class="flex gap-2">
                                            <span class="mt-0.5 shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-200 text-red-700 text-xs font-bold">&#10007;</span>
                                            <span class="text-slate-600">{{ __('squad.lineup_help_compatibility_poor') }}</span>
                                        </li>
                                    </ul>
                                    <p class="mt-3 text-xs text-slate-400">{{ __('squad.lineup_help_mentality_desc') }}</p>
                                </div>

                                {{-- Fitness & Morale --}}
                                <div>
                                    <p class="font-semibold text-slate-700 mb-2">{{ __('squad.lineup_help_condition_title') }}</p>
                                    <p class="text-slate-500 mb-2">{{ __('squad.lineup_help_condition_desc') }}</p>
                                    <ul class="space-y-1 text-slate-600">
                                        <li class="flex gap-2"><span class="text-amber-500 shrink-0">&#9679;</span> {{ __('squad.lineup_help_fitness') }}</li>
                                        <li class="flex gap-2"><span class="text-sky-500 shrink-0">&#9679;</span> {{ __('squad.lineup_help_morale') }}</li>
                                    </ul>
                                    <p class="mt-3 text-xs text-slate-400">{{ __('squad.lineup_help_auto') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('game.lineup.save', $game->id) }}" @submit="_isSaving = true">
                        @csrf

                        {{-- Hidden inputs --}}
                        <template x-for="playerId in selectedPlayers" :key="playerId">
                            <input type="hidden" name="players[]" :value="playerId">
                        </template>
                        <input type="hidden" name="formation" :value="selectedFormation">
                        <input type="hidden" name="mentality" :value="selectedMentality">
                        {{-- Slot assignment hidden inputs --}}
                        <template x-for="slot in slotAssignments" :key="'sa-' + slot.id">
                            <input x-show="slot.player" type="hidden" :name="'slot_assignments[' + slot.id + ']'" :value="slot.player?.id">
                        </template>

                        {{-- Top Bar: Formation, Stats, Actions --}}
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6 p-4 bg-slate-50 rounded-lg sticky top-0 z-10">
                            <div class="flex flex-wrap items-center gap-3 md:gap-6">
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
                                <div class="hidden md:flex items-center gap-3 px-3 py-1.5 bg-slate-200 rounded-md">
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

                            <div class="flex items-center gap-3 shrink-0">
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
                        </div>

                        {{-- Main Content: Pitch + Player List --}}
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            {{-- Pitch Visualization --}}

                            <div class="col-span-1 lg:sticky lg:top-[100px] lg:self-start" :class="{ 'hidden lg:block': activeLineupTab !== 'pitch' }">
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
                                            class="absolute transform -translate-x-1/2 -translate-y-1/2 transition-all duration-300 hover:z-30"
                                            :style="`left: ${slot.x}%; top: ${100 - slot.y}%`"
                                        >
                                            {{-- Empty Slot (clickable for assignment) --}}
                                            <div
                                                x-show="!slot.player"
                                                @click="selectSlot(slot.id)"
                                                class="w-11 h-11 rounded-full border-2 flex items-center justify-center backdrop-blur-sm cursor-pointer transition-all duration-200"
                                                :class="selectedSlot === slot.id
                                                    ? 'border-white bg-white/30 ring-2 ring-white/60 scale-110'
                                                    : 'border-dashed border-white/40 bg-white/5 hover:border-white/70 hover:bg-white/15'"
                                            >
                                                <span class="text-[10px] font-semibold tracking-wide" :class="selectedSlot === slot.id ? 'text-white' : 'text-white/60'" x-text="slot.displayLabel"></span>
                                            </div>

                                            {{-- Filled Slot (clickable for reassignment) --}}
                                            <div
                                                x-show="slot.player"
                                                class="group relative cursor-pointer"
                                                @click="handleSlotClick(slot.id, slot.player?.id)"
                                            >
                                                {{-- Main player badge --}}
                                                <div
                                                    class="relative w-11 h-11 rounded-xl shadow-lg border border-white/20 transform transition-all duration-200 hover:scale-110 hover:shadow-xl"
                                                    :class="{
                                                        'ring-2 ring-white ring-offset-1 ring-offset-emerald-600 scale-110': selectedSlot === slot.id,
                                                        'ring-2 ring-white/70 scale-110 shadow-xl': hoveredPlayerId && hoveredPlayerId === slot.player?.id && selectedSlot !== slot.id,
                                                    }"
                                                    :style="getShirtStyle(slot.role)"
                                                >
                                                    {{-- Number or Initials --}}
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" :style="getNumberStyle(slot.role)" x-text="slot.player?.number || getInitials(slot.player?.name)"></span>
                                                    </div>
                                                </div>

                                                {{-- Hover tooltip --}}
                                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-slate-900/95 backdrop-blur-sm text-white text-xs rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none z-20 shadow-xl">
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

                                    {{-- Selected slot indicator banner --}}
                                    <div
                                        x-show="selectedSlot !== null"
                                        x-cloak
                                        class="absolute bottom-2 left-1/2 -translate-x-1/2 px-4 py-2 bg-white/95 backdrop-blur-sm rounded-lg shadow-lg text-xs font-medium text-slate-700 flex items-center gap-2 z-20"
                                    >
                                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                                        {{ __('squad.select_player_for_slot') }}
                                        <button type="button" @click="selectedSlot = null" class="ml-1 text-slate-400 hover:text-slate-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
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
                            <div class="lg:col-span-2 overflow-x-auto" :class="{ 'hidden lg:block': activeLineupTab !== 'squad' }">
                                <table class="w-full text-sm">
                                    <thead class="text-left text-sm border-b sticky top-0 bg-white">
                                        <tr>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2">{{ __('app.name') }}</th>
                                            <th class="font-semibold py-2 w-10"></th>
                                            <th class="font-semibold py-2 text-center w-8">{{ __('squad.overall') }}</th>
                                            <th class="font-semibold py-2 text-center w-8 hidden md:table-cell">{{ __('squad.technical') }}</th>
                                            <th class="font-semibold py-2 text-center w-8 hidden md:table-cell">{{ __('squad.physical') }}</th>
                                            <th class="font-semibold py-2 text-center w-8 hidden md:table-cell">{{ __('squad.fitness') }}</th>
                                            <th class="font-semibold py-2 text-center w-8 hidden md:table-cell">{{ __('squad.morale') }}</th>
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
                                                        @mouseenter="hoveredPlayerId = '{{ $player->id }}'"
                                                        @mouseleave="hoveredPlayerId = null"
                                                        class="border-b border-slate-200 transition-colors
                                                            @if($isUnavailable)
                                                                text-slate-400 cursor-not-allowed
                                                            @else
                                                                cursor-pointer hover:bg-slate-50
                                                            @endif"
                                                        :class="{
                                                            'bg-sky-50': isSelected('{{ $player->id }}') && selectedSlot === null,
                                                            'bg-sky-100 ring-1 ring-sky-300': selectedSlot !== null && !{{ $isUnavailable ? 'true' : 'false' }} && getSelectedSlotCompatibility('{{ $player->position }}')?.score >= 40,
                                                            'opacity-50': !isSelected('{{ $player->id }}') && selectedCount >= 11 && selectedSlot === null && !{{ $isUnavailable ? 'true' : 'false' }}
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
                                                        {{-- Compatibility indicator --}}
                                                        <td class="py-2 text-right">
                                                            {{-- When a slot is selected: show compatibility with that slot --}}
                                                            @if(!$isUnavailable)
                                                                <template x-if="selectedSlot !== null && getSelectedSlotCompatibility('{{ $player->position }}')">
                                                                    <span
                                                                        class="text-xs font-medium px-1.5 py-0.5 rounded whitespace-nowrap"
                                                                        :class="getSelectedSlotCompatibility('{{ $player->position }}').class"
                                                                        x-text="getSelectedSlotCompatibility('{{ $player->position }}').label"
                                                                    ></span>
                                                                </template>
                                                            @endif
                                                            {{-- When no slot selected: show assigned slot info --}}
                                                            <template x-if="selectedSlot === null && isSelected('{{ $player->id }}') && getPlayerSlot('{{ $player->id }}')">
                                                                <span
                                                                    class="text-xs font-medium px-1.5 py-0.5 rounded whitespace-nowrap"
                                                                    :class="getCompatibilityDisplay('{{ $player->position }}', getPlayerSlot('{{ $player->id }}')).class"
                                                                    x-text="getPlayerSlotDisplay('{{ $player->id }}') + (getCompatibilityDisplay('{{ $player->position }}', getPlayerSlot('{{ $player->id }}')).label !== '{{ __('squad.natural') }}' ? ' (' + getCompatibilityDisplay('{{ $player->position }}', getPlayerSlot('{{ $player->id }}')).label + ')' : '')"
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
                                                        <td class="py-2 text-center text-slate-400 hidden md:table-cell">
                                                            {{ $player->technical_ability }}
                                                        </td>
                                                        {{-- Physical --}}
                                                        <td class="py-2 text-center text-slate-400 hidden md:table-cell">
                                                            {{ $player->physical_ability }}
                                                        </td>
                                                        {{-- Fitness --}}
                                                        <td class="py-2 text-center hidden md:table-cell @if($player->fitness < 70) text-yellow-500 @elseif($player->fitness < 50) text-red-500 @else text-slate-400 @endif">
                                                            {{ $player->fitness }}
                                                        </td>
                                                        {{-- Morale --}}
                                                        <td class="py-2 text-center hidden md:table-cell @if($player->morale < 60) text-red-500 @elseif($player->morale < 70) text-yellow-500 @else text-slate-400 @endif">
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
