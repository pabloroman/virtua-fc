@php
    /** @var App\Models\Game $game */
    /** @var \Illuminate\Support\Collection $goalkeepers */
    /** @var \Illuminate\Support\Collection $defenders */
    /** @var \Illuminate\Support\Collection $midfielders */
    /** @var \Illuminate\Support\Collection $forwards */
    /** @var \Illuminate\Support\Collection $allPlayers */
    /** @var \Illuminate\Support\Collection $playerData */
    /** @var array $suggestions */
    /** @var int $maxStandard */
    /** @var int $academyNumberStart */
    /** @var int $maxAcademyAge */
    /** @var int $minGk */
    /** @var int $minDef */
    /** @var int $minMid */
    /** @var int $minFwd */
    /** @var int $minTotal */
    /** @var bool $isReRegistration */

    $positionGroups = [
        ['key' => 'goalkeepers', 'label' => __('squad.goalkeepers'), 'players' => $goalkeepers, 'group' => 'Goalkeeper'],
        ['key' => 'defenders', 'label' => __('squad.defenders'), 'players' => $defenders, 'group' => 'Defender'],
        ['key' => 'midfielders', 'label' => __('squad.midfielders'), 'players' => $midfielders, 'group' => 'Midfielder'],
        ['key' => 'forwards', 'label' => __('squad.forwards'), 'players' => $forwards, 'group' => 'Forward'],
    ];
@endphp

<x-app-layout :hide-footer="true">
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div x-data="{
        players: @js($playerData),
        suggestions: @js($suggestions),
        assignments: {},
        activeTab: 'goalkeepers',
        maxStandard: {{ $maxStandard }},
        academyStart: {{ $academyNumberStart }},
        maxAcademyAge: {{ $maxAcademyAge }},
        minCounts: { Goalkeeper: {{ $minGk }}, Defender: {{ $minDef }}, Midfielder: {{ $minMid }}, Forward: {{ $minFwd }} },
        minTotal: {{ $minTotal }},
        submitting: false,

        init() {
            // Pre-populate with current assignments (re-registration) or empty
            for (const [id, p] of Object.entries(this.players)) {
                if (p.number) {
                    this.assignments[id] = p.number;
                }
            }
        },

        get registeredIds() {
            return Object.keys(this.assignments);
        },

        get standardCount() {
            return Object.values(this.assignments).filter(n => n >= 1 && n <= this.maxStandard).length;
        },

        get academyCount() {
            return Object.values(this.assignments).filter(n => n >= this.academyStart).length;
        },

        get totalRegistered() {
            return Object.keys(this.assignments).length;
        },

        get slotsRemaining() {
            return this.maxStandard - this.standardCount;
        },

        positionCount(group) {
            return Object.keys(this.assignments).filter(id => this.players[id]?.position_group === group).length;
        },

        positionMet(group) {
            return this.positionCount(group) >= this.minCounts[group];
        },

        get canSubmit() {
            if (this.totalRegistered < this.minTotal) return false;
            if (this.standardCount > this.maxStandard) return false;
            for (const [group, min] of Object.entries(this.minCounts)) {
                if (this.positionCount(group) < min) return false;
            }
            // Check for duplicate numbers
            const nums = Object.values(this.assignments);
            if (new Set(nums).size !== nums.length) return false;
            return true;
        },

        isRegistered(playerId) {
            return playerId in this.assignments;
        },

        isAcademyEligible(playerId) {
            const p = this.players[playerId];
            return p && p.age <= this.maxAcademyAge;
        },

        togglePlayer(playerId) {
            if (this.isRegistered(playerId)) {
                delete this.assignments[playerId];
                this.assignments = { ...this.assignments };
            } else {
                this.registerPlayer(playerId);
            }
        },

        registerPlayer(playerId) {
            const p = this.players[playerId];
            if (!p) return;

            // Academy-eligible youth get numbers 26+
            if (p.age <= this.maxAcademyAge && this.standardCount >= this.maxStandard) {
                const taken = new Set(Object.values(this.assignments));
                for (let n = this.academyStart; n <= 99; n++) {
                    if (!taken.has(n)) {
                        this.assignments[playerId] = n;
                        this.assignments = { ...this.assignments };
                        return;
                    }
                }
                return;
            }

            // Standard slot
            if (this.standardCount >= this.maxStandard) return;

            const taken = new Set(Object.values(this.assignments));
            for (let n = 1; n <= this.maxStandard; n++) {
                if (!taken.has(n)) {
                    this.assignments[playerId] = n;
                    this.assignments = { ...this.assignments };
                    return;
                }
            }
        },

        autoAssign() {
            this.assignments = {};
            for (const [id, num] of Object.entries(this.suggestions)) {
                if (this.players[id]) {
                    this.assignments[id] = num;
                }
            }
            this.assignments = { ...this.assignments };
        },

        clearAll() {
            this.assignments = {};
        },

        countByGroup(groupKey) {
            const groupMap = {
                goalkeepers: 'Goalkeeper',
                defenders: 'Defender',
                midfielders: 'Midfielder',
                forwards: 'Forward',
            };
            const group = groupMap[groupKey];
            return Object.keys(this.assignments).filter(id => this.players[id]?.position_group === group).length;
        },
    }" class="min-h-screen pb-32 md:pb-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">

            {{-- Header --}}
            <div class="text-center mb-6 md:mb-8">
                <x-team-crest :team="$game->team" class="w-16 h-16 md:w-20 md:h-20 mx-auto mb-3 md:mb-4" />
                <h1 class="text-2xl md:text-3xl font-bold text-text-primary mb-1">
                    {{ __('squad.registration_title') }}
                </h1>
                <p class="text-sm text-text-muted max-w-md mx-auto">
                    {{ __('squad.registration_subtitle', ['max' => $maxStandard]) }}
                </p>
            </div>

            {{-- Flash Messages --}}
            <x-flash-message type="error" :message="session('error')" class="mb-4" />
            <x-flash-message type="success" :message="session('success')" class="mb-4" />

            {{-- Info Banner --}}
            <x-status-banner color="blue" :title="__('squad.registration_info_title')" :description="__('squad.registration_info_description', ['max' => $maxStandard, 'academy_age' => $maxAcademyAge + 1])" class="mb-4">
                <x-slot name="icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>
                </x-slot>
            </x-status-banner>

            {{-- Main Card --}}
            <div class="bg-surface-800 rounded-xl shadow-xs border border-border-strong overflow-hidden">

                {{-- Title Bar with Counter & Actions --}}
                <div class="p-4 md:p-6 border-b border-border-strong">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xl md:text-2xl font-bold transition-colors"
                                      :class="standardCount === maxStandard ? 'text-accent-green' : 'text-text-secondary'"
                                      x-text="standardCount"></span>
                                <span class="text-sm md:text-base text-text-secondary">/</span>
                                <span class="text-sm md:text-base text-text-secondary">{{ $maxStandard }}</span>
                            </div>
                            <span class="text-xs text-text-muted hidden md:inline" x-show="academyCount > 0">
                                + <span x-text="academyCount"></span> {{ __('squad.academy_short') }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ghost-button type="button" @click="clearAll()" class="text-xs">
                                {{ __('squad.clear_all') }}
                            </x-ghost-button>
                            <x-secondary-button type="button" @click="autoAssign()" class="text-xs">
                                {{ __('squad.auto_assign') }}
                            </x-secondary-button>
                        </div>
                    </div>

                    {{-- Position Minimums --}}
                    <div class="flex items-center gap-3 mt-3 text-xs">
                        @foreach ([
                            ['group' => 'Goalkeeper', 'label' => __('squad.goalkeepers_short'), 'min' => $minGk],
                            ['group' => 'Defender', 'label' => __('squad.defenders_short'), 'min' => $minDef],
                            ['group' => 'Midfielder', 'label' => __('squad.midfielders_short'), 'min' => $minMid],
                            ['group' => 'Forward', 'label' => __('squad.forwards_short'), 'min' => $minFwd],
                        ] as $req)
                        <div class="flex items-center gap-1 transition-colors"
                             :class="positionMet('{{ $req['group'] }}') ? 'text-accent-green' : 'text-accent-gold'">
                            <svg x-show="positionMet('{{ $req['group'] }}')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <svg x-show="!positionMet('{{ $req['group'] }}')" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                            <span>{{ $req['label'] }}: <span x-text="positionCount('{{ $req['group'] }}')"></span>/{{ $req['min'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="border-b border-border-strong overflow-x-auto scrollbar-hide">
                    <nav class="flex">
                        @foreach ($positionGroups as $pg)
                        <x-tab-button @click="activeTab = '{{ $pg['key'] }}'"
                                x-bind:class="activeTab === '{{ $pg['key'] }}'
                                    ? 'border-accent-blue text-accent-blue font-semibold'
                                    : 'border-transparent text-text-muted hover:text-text-body'"
                                class="flex-1 shrink-0 px-3 md:px-4 py-3 text-xs md:text-sm text-center min-h-[44px] flex items-center justify-center gap-1.5">
                            <span>{{ $pg['label'] }}</span>
                            <span class="inline-flex items-center justify-center rounded-full text-[10px] md:text-xs font-semibold min-w-[20px] h-5 px-1 transition-colors"
                                  :class="countByGroup('{{ $pg['key'] }}') > 0 ? 'bg-accent-green/10 text-accent-green' : 'bg-surface-700 text-text-secondary'"
                                  x-text="countByGroup('{{ $pg['key'] }}')"></span>
                        </x-tab-button>
                        @endforeach
                    </nav>
                </div>

                {{-- Player Lists --}}
                @foreach ($positionGroups as $pg)
                <div x-show="activeTab === '{{ $pg['key'] }}'" x-cloak class="divide-y divide-border-default">
                    @foreach ($pg['players'] as $player)
                    <button type="button"
                            @click="togglePlayer('{{ $player->id }}')"
                            :class="{
                                'bg-accent-green/10 border-l-4 border-l-emerald-500': isRegistered('{{ $player->id }}'),
                                'border-l-4 border-l-transparent hover:bg-surface-700/50': !isRegistered('{{ $player->id }}'),
                                'opacity-40 cursor-not-allowed': slotsRemaining <= 0 && !isRegistered('{{ $player->id }}') && !isAcademyEligible('{{ $player->id }}'),
                            }"
                            :disabled="slotsRemaining <= 0 && !isRegistered('{{ $player->id }}') && !isAcademyEligible('{{ $player->id }}')"
                            class="w-full flex items-center gap-3 px-3 md:px-5 py-3 md:py-3.5 text-left transition-all min-h-[56px]">

                        {{-- Number Badge --}}
                        <div class="shrink-0 w-8 h-8 rounded-md flex items-center justify-center text-xs font-bold transition-colors"
                             :class="isRegistered('{{ $player->id }}')
                                 ? (assignments['{{ $player->id }}'] >= academyStart ? 'bg-accent-blue/15 text-accent-blue' : 'bg-accent-green/15 text-accent-green')
                                 : 'bg-surface-700 text-text-secondary'">
                            <span x-show="isRegistered('{{ $player->id }}')" x-text="assignments['{{ $player->id }}']"></span>
                            <span x-show="!isRegistered('{{ $player->id }}')">-</span>
                        </div>

                        {{-- Position Badge --}}
                        <x-position-badge :position="$player->position" size="md" />

                        {{-- Name + Meta --}}
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-sm md:text-base text-text-primary truncate">{{ $player->player->name }}</div>
                            <div class="flex items-center gap-2 text-xs text-text-secondary mt-0.5">
                                <span>{{ $player->age($game->current_date) }} {{ __('squad.years_abbr') }}</span>
                                @if($player->age($game->current_date) <= $maxAcademyAge)
                                <span class="text-accent-blue">{{ __('squad.academy_eligible') }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Abilities --}}
                        <div class="shrink-0 flex items-center gap-2 md:gap-3">
                            <div class="hidden md:flex items-center gap-1.5">
                                <span class="text-xs text-text-secondary">{{ __('squad.technical_abbr') }}</span>
                                <span class="text-xs font-semibold text-text-secondary">{{ $player->current_technical_ability }}</span>
                            </div>
                            <div class="hidden md:flex items-center gap-1.5">
                                <span class="text-xs text-text-secondary">{{ __('squad.physical_abbr') }}</span>
                                <span class="text-xs font-semibold text-text-secondary">{{ $player->current_physical_ability }}</span>
                            </div>
                            <div class="flex items-center justify-center w-10 h-10 md:w-11 md:h-11 rounded-lg transition-colors"
                                 :class="isRegistered('{{ $player->id }}') ? 'bg-accent-green/10' : 'bg-surface-700'">
                                <span class="text-sm md:text-base font-bold"
                                      :class="isRegistered('{{ $player->id }}') ? 'text-accent-green' : 'text-text-body'">{{ $player->overall_score }}</span>
                            </div>
                        </div>
                    </button>
                    @endforeach
                </div>
                @endforeach

            </div>
        </div>

        {{-- Sticky Bottom Bar --}}
        <div class="fixed bottom-0 left-0 right-0 bg-surface-800/95 backdrop-blur-xs border-t border-border-strong shadow-lg z-30">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-3 md:py-4">
                <div class="flex items-center gap-3 md:gap-4">
                    {{-- Position Breakdown (desktop) --}}
                    <div class="hidden md:flex items-center gap-3 text-xs text-text-muted flex-1">
                        <span><span class="font-semibold text-text-body" x-text="countByGroup('goalkeepers')"></span> {{ __('squad.goalkeepers_short') }}</span>
                        <span class="text-text-body">&middot;</span>
                        <span><span class="font-semibold text-text-body" x-text="countByGroup('defenders')"></span> {{ __('squad.defenders_short') }}</span>
                        <span class="text-text-body">&middot;</span>
                        <span><span class="font-semibold text-text-body" x-text="countByGroup('midfielders')"></span> {{ __('squad.midfielders_short') }}</span>
                        <span class="text-text-body">&middot;</span>
                        <span><span class="font-semibold text-text-body" x-text="countByGroup('forwards')"></span> {{ __('squad.forwards_short') }}</span>
                    </div>

                    {{-- Mobile: compact counter --}}
                    <div class="flex md:hidden items-center gap-1.5 text-sm flex-1">
                        <span class="font-bold transition-colors"
                              :class="canSubmit ? 'text-accent-green' : 'text-text-body'"
                              x-text="totalRegistered"></span>
                        <span class="text-text-secondary">{{ __('squad.registered_label') }}</span>
                    </div>

                    {{-- Submit --}}
                    <form method="POST" action="{{ route('game.squad.registration.save', $game->id) }}" class="flex-1 md:flex-none"
                          @submit="submitting = true">
                        @csrf
                        <template x-for="[playerId, number] in Object.entries(assignments)" :key="playerId">
                            <input type="hidden" :name="'assignments[' + playerId + ']'" :value="number">
                        </template>
                        <x-primary-button color="emerald" x-bind:disabled="!canSubmit || submitting" class="w-full md:w-auto">
                            <span x-show="!submitting">{{ __('squad.confirm_registration') }}</span>
                            <span x-show="submitting" x-cloak>{{ __('app.saving') }}...</span>
                        </x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
