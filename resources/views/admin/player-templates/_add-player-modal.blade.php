<x-modal name="add-player" maxWidth="3xl">
    <div x-data="{
        playerQuery: '',
        playerResults: [],
        selectedPlayer: null,
        searching: false,
        debounceTimer: null,
        form: {
            season: @js($selectedSeason),
            player_id: '',
            team_id: @js($team->id),
            number: '',
            position: 'Centre-Forward',
            market_value: '',
            market_value_cents: 0,
            contract_until: '',
            annual_wage: 0,
            fitness: 80,
            morale: 80,
            durability: 50,
            game_technical_ability: 50,
            game_physical_ability: 50,
            potential: 50,
            potential_low: 40,
            potential_high: 60,
            tier: 1,
        },
        searchPlayers() {
            clearTimeout(this.debounceTimer);
            if (this.playerQuery.length < 2) {
                this.playerResults = [];
                return;
            }
            this.debounceTimer = setTimeout(async () => {
                this.searching = true;
                try {
                    const res = await fetch('{{ route('admin.player-templates.search-players') }}?q=' + encodeURIComponent(this.playerQuery));
                    this.playerResults = await res.json();
                } catch (e) {
                    this.playerResults = [];
                } finally {
                    this.searching = false;
                }
            }, 300);
        },
        selectPlayer(player) {
            this.selectedPlayer = player;
            this.form.player_id = player.id;
            this.playerQuery = player.name;
            this.playerResults = [];
        },
        clearPlayer() {
            this.selectedPlayer = null;
            this.form.player_id = '';
            this.playerQuery = '';
            this.playerResults = [];
        }
    }" class="p-6">
        <h2 class="font-heading text-lg font-bold uppercase tracking-wide text-text-primary mb-4">
            {{ __('admin.add_player') }}
        </h2>

        <form method="POST" action="{{ route('admin.player-templates.store') }}">
            @csrf
            <input type="hidden" name="season" x-bind:value="form.season">
            <input type="hidden" name="team_id" x-bind:value="form.team_id">
            <input type="hidden" name="player_id" x-bind:value="form.player_id">

            {{-- Player search --}}
            <div class="mb-4">
                <x-input-label value="{{ __('admin.tpl_player') }}" />
                <div class="relative">
                    <x-text-input
                        type="text"
                        x-model="playerQuery"
                        @input="searchPlayers()"
                        placeholder="{{ __('admin.search_players_placeholder') }}"
                        class="w-full"
                        x-show="!selectedPlayer"
                    />
                    <div x-show="selectedPlayer" class="flex items-center gap-2 px-3 py-2 bg-surface-700 rounded-lg">
                        <span class="text-sm text-text-primary" x-text="selectedPlayer?.name"></span>
                        <button type="button" @click="clearPlayer()" class="text-text-muted hover:text-accent-red">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    {{-- Dropdown results --}}
                    <div x-show="playerResults.length > 0 && !selectedPlayer"
                         class="absolute z-20 mt-1 w-full bg-surface-700 border border-border-default rounded-lg shadow-lg max-h-48 overflow-y-auto">
                        <template x-for="player in playerResults" :key="player.id">
                            <button type="button"
                                    @click="selectPlayer(player)"
                                    class="w-full text-left px-3 py-2 text-sm text-text-primary hover:bg-surface-600 transition-colors min-h-[44px]">
                                <span x-text="player.name"></span>
                                <span class="text-text-muted ml-2 text-xs" x-text="player.date_of_birth"></span>
                            </button>
                        </template>
                    </div>
                    <div x-show="searching" class="absolute right-3 top-2.5 text-xs text-text-muted">...</div>
                </div>
                <x-input-error :messages="$errors->get('player_id')" />
            </div>

            {{-- Basic fields --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div>
                    <x-input-label value="{{ __('admin.tpl_number') }}" />
                    <x-text-input type="number" name="number" x-model="form.number" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_position') }}" />
                    <select name="position" x-model="form.position"
                            class="w-full rounded-lg border border-border-default bg-surface-700 text-text-primary text-sm px-3 py-2 focus:border-accent-blue focus:ring-accent-blue">
                        @foreach($positions as $pos)
                            <option value="{{ $pos }}">{{ $pos }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_market_value') }}" />
                    <x-text-input type="text" name="market_value" x-model="form.market_value" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_market_value_cents') }}" />
                    <x-text-input type="number" name="market_value_cents" x-model="form.market_value_cents" min="0" class="w-full" />
                </div>
            </div>

            {{-- Abilities --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4">
                <div>
                    <x-input-label value="{{ __('admin.tpl_tech') }}" />
                    <x-text-input type="number" name="game_technical_ability" x-model="form.game_technical_ability" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_phys') }}" />
                    <x-text-input type="number" name="game_physical_ability" x-model="form.game_physical_ability" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_potential') }}" />
                    <x-text-input type="number" name="potential" x-model="form.potential" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_potential_low') }}" />
                    <x-text-input type="number" name="potential_low" x-model="form.potential_low" min="1" max="99" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_potential_high') }}" />
                    <x-text-input type="number" name="potential_high" x-model="form.potential_high" min="1" max="99" class="w-full" />
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
                <div>
                    <x-input-label value="{{ __('admin.tpl_fitness') }}" />
                    <x-text-input type="number" name="fitness" x-model="form.fitness" min="0" max="100" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_morale') }}" />
                    <x-text-input type="number" name="morale" x-model="form.morale" min="0" max="100" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_durability') }}" />
                    <x-text-input type="number" name="durability" x-model="form.durability" min="0" max="100" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_annual_wage') }}" />
                    <x-text-input type="number" name="annual_wage" x-model="form.annual_wage" min="0" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_contract_until') }}" />
                    <x-text-input type="date" name="contract_until" x-model="form.contract_until" class="w-full" />
                </div>
                <div>
                    <x-input-label value="{{ __('admin.tpl_tier') }}" />
                    <select name="tier" x-model="form.tier"
                            class="w-full rounded-lg border border-border-default bg-surface-700 text-text-primary text-sm px-3 py-2 focus:border-accent-blue focus:ring-accent-blue">
                        @for($i = 1; $i <= 5; $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-border-default">
                <x-ghost-button type="button" color="slate" size="xs" @click="$dispatch('close-modal', 'add-player')">
                    {{ __('admin.cancel') }}
                </x-ghost-button>
                <x-primary-button type="submit" color="blue" size="xs" x-bind:disabled="!form.player_id">
                    {{ __('admin.add_player') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>
