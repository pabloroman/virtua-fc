<x-admin-layout>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('editor.player-templates.index', ['season' => $selectedSeason]) }}"
               class="text-text-muted hover:text-text-secondary transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </a>
            @if($team->image)
                <img src="{{ $team->image }}" alt="" class="w-8 h-8 shrink-0">
            @endif
            <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                {{ $team->name }}
            </h1>
        </div>
        <div class="flex items-center gap-3">
            {{-- Season selector --}}
            <form method="GET" action="{{ route('editor.player-templates.squad', $team->id) }}" class="flex items-center gap-2">
                <x-select-input name="season" onchange="this.form.submit()">
                    @foreach($seasons as $season)
                        <option value="{{ $season }}" {{ $selectedSeason === $season ? 'selected' : '' }}>
                            {{ $season }}
                        </option>
                    @endforeach
                </x-select-input>
            </form>
            {{-- Add Player --}}
            <button @click="$dispatch('open-modal', 'add-player')"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-accent-blue/10 text-accent-blue hover:bg-accent-blue/20 transition-colors min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                {{ __('admin.add_player') }}
            </button>
        </div>
    </div>

    <x-flash-message />

    {{-- Squad grouped by position --}}
    @forelse($grouped as $groupName => $templates)
        <div class="mb-6">
            {{-- Group header --}}
            <div class="bg-surface-700/30 px-4 py-2 rounded-t-xl border border-border-default">
                <span class="text-[10px] text-text-muted uppercase tracking-wider font-semibold">
                    {{ __('admin.position_group_' . strtolower(str_replace(' ', '_', $groupName))) }}
                </span>
                <span class="text-[10px] text-text-muted ml-2">({{ $templates->count() }})</span>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto border-x border-b border-border-default rounded-b-xl">
                <table class="min-w-full divide-y divide-border-default">
                    <thead>
                        <tr class="bg-surface-800">
                            <th class="px-3 py-2 text-left text-[10px] text-text-muted uppercase tracking-wider w-10">#</th>
                            <th class="px-3 py-2 text-left text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.tpl_player') }}</th>
                            <th class="px-3 py-2 text-left text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.tpl_position') }}</th>
                            <th class="px-3 py-2 text-right text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.tpl_market_value') }}</th>
                            <th class="px-3 py-2 text-center text-[10px] text-text-muted uppercase tracking-wider w-[60px]">{{ __('admin.tpl_tech') }}</th>
                            <th class="px-3 py-2 text-center text-[10px] text-text-muted uppercase tracking-wider w-[60px]">{{ __('admin.tpl_phys') }}</th>
                            <th class="px-3 py-2 text-center text-[10px] text-text-muted uppercase tracking-wider hidden md:table-cell">{{ __('admin.tpl_potential') }}</th>
                            <th class="px-3 py-2 text-center text-[10px] text-text-muted uppercase tracking-wider hidden lg:table-cell w-10">{{ __('admin.tpl_tier') }}</th>
                            <th class="px-3 py-2 text-right text-[10px] text-text-muted uppercase tracking-wider">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-default">
                        @foreach($templates as $template)
                            <tr x-data="{
                                    editing: false,
                                    saving: false,
                                    error: '',
                                    original: @js($template->toArray()),
                                    form: {
                                        ...@js($template->toArray()),
                                        nationality: @js(($template->player?->nationality ?? [])[0] ?? ''),
                                    },
                                    marketValueEuros: Math.round(@js($template->market_value_cents) / 100),
                                    annualWageEuros: Math.round(@js($template->annual_wage) / 100),
                                    async save() {
                                        this.saving = true;
                                        this.error = '';
                                        try {
                                            const payload = {
                                                ...this.form,
                                                market_value_cents: this.marketValueEuros * 100,
                                                annual_wage: this.annualWageEuros * 100,
                                            };
                                            const res = await fetch('{{ route('editor.player-templates.update', $template->id) }}', {
                                                method: 'PUT',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                    'Accept': 'application/json',
                                                },
                                                body: JSON.stringify(payload),
                                            });
                                            const data = await res.json();
                                            if (res.ok && data.success) {
                                                this.original = { ...payload };
                                                this.editing = false;
                                            } else {
                                                const errors = data.errors ? Object.values(data.errors).flat().join(', ') : data.message;
                                                this.error = errors || 'Error';
                                            }
                                        } catch (e) {
                                            this.error = e.message;
                                        } finally {
                                            this.saving = false;
                                        }
                                    },
                                    cancel() {
                                        this.form = { ...this.original };
                                        this.form.nationality = @js(($template->player?->nationality ?? [])[0] ?? '');
                                        this.marketValueEuros = Math.round(this.original.market_value_cents / 100);
                                        this.annualWageEuros = Math.round(this.original.annual_wage / 100);
                                        this.editing = false;
                                        this.error = '';
                                    },
                                }"
                                class="bg-surface-800 hover:bg-[rgba(59,130,246,0.05)] transition-colors">

                                {{-- Display mode: proper <td> elements --}}
                                <td x-show="!editing" class="px-3 py-2.5 text-sm text-text-muted" x-text="original.number || '—'"></td>
                                <td x-show="!editing" class="px-3 py-2.5 text-sm text-text-primary font-medium truncate">{{ $template->player?->name ?? '—' }}</td>
                                <td x-show="!editing" class="px-3 py-2.5 text-xs text-text-muted hidden md:table-cell" x-text="original.position"></td>
                                <td x-show="!editing" class="px-3 py-2.5 text-xs text-text-muted text-right hidden md:table-cell">{{ \App\Support\Money::format($template->market_value_cents) }}</td>
                                <td x-show="!editing" class="px-3 py-2.5 text-sm text-text-primary text-center" x-text="original.game_technical_ability ?? '—'"></td>
                                <td x-show="!editing" class="px-3 py-2.5 text-sm text-text-primary text-center" x-text="original.game_physical_ability ?? '—'"></td>
                                <td x-show="!editing" class="px-3 py-2.5 text-xs text-text-muted text-center hidden md:table-cell">
                                    <span x-text="(original.potential_low ?? '?') + '-' + (original.potential_high ?? '?')"></span>
                                </td>
                                <td x-show="!editing" class="px-3 py-2.5 text-xs text-text-muted text-center hidden lg:table-cell" x-text="original.tier"></td>
                                <td x-show="!editing" class="px-3 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="editing = true" class="p-1.5 rounded text-text-muted hover:text-accent-blue hover:bg-accent-blue/10 transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center" title="{{ __('admin.edit') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                                        </button>
                                        <button @click="$dispatch('open-modal', 'audit-{{ $template->id }}')" class="p-1.5 rounded text-text-muted hover:text-accent-gold hover:bg-accent-gold/10 transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center" title="{{ __('admin.history') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        </button>
                                        <form method="POST" action="{{ route('editor.player-templates.delete', $template->id) }}" onsubmit="return confirm('{{ __('admin.remove_confirm') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 rounded text-text-muted hover:text-accent-red hover:bg-accent-red/10 transition-colors min-w-[44px] min-h-[44px] flex items-center justify-center" title="{{ __('admin.remove_player') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                {{-- Edit mode --}}
                                <td x-show="editing" x-cloak colspan="9" class="p-0">
                                    <div class="p-4 bg-accent-blue/5 border-l-2 border-accent-blue space-y-4">
                                        <div class="text-sm font-medium text-text-primary">{{ $template->player?->name }}</div>

                                        {{-- Error --}}
                                        <div x-show="error" x-text="error" class="text-xs text-accent-red bg-accent-red/10 rounded px-3 py-1.5"></div>

                                        {{-- Section: Personal Details --}}
                                        <div>
                                            <div class="text-[10px] text-text-muted uppercase tracking-wider font-semibold mb-2">{{ __('admin.section_personal') }}</div>
                                            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_team') }}" />
                                                    <x-select-input x-model="form.team_id" class="w-full">
                                                        <option value="">{{ __('admin.free_agent') }}</option>
                                                        @foreach($teams as $t)
                                                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                                                        @endforeach
                                                    </x-select-input>
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_number') }}" />
                                                    <x-text-input type="number" x-model="form.number" min="1" max="99" class="w-full" />
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_position') }}" />
                                                    <x-select-input x-model="form.position" class="w-full">
                                                        @foreach($positions as $pos)
                                                            <option value="{{ $pos }}">{{ $pos }}</option>
                                                        @endforeach
                                                    </x-select-input>
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_nationality') }}" />
                                                    <x-select-input x-model="form.nationality" class="w-full">
                                                        <option value="">—</option>
                                                        @foreach($countries as $country)
                                                            <option value="{{ $country }}">{{ $country }}</option>
                                                        @endforeach
                                                    </x-select-input>
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_contract_until') }}" />
                                                    <x-text-input type="date" x-model="form.contract_until" class="w-full" />
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_tier') }}" />
                                                    <x-select-input x-model="form.tier" class="w-full">
                                                        @for($i = 1; $i <= 5; $i++)
                                                            <option value="{{ $i }}">{{ $i }}</option>
                                                        @endfor
                                                    </x-select-input>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Section: Game Parameters --}}
                                        <div>
                                            <div class="text-[10px] text-text-muted uppercase tracking-wider font-semibold mb-2">{{ __('admin.section_game_params') }}</div>
                                            <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_tech') }}" />
                                                    <x-text-input type="number" x-model="form.game_technical_ability" min="1" max="99" class="w-full" />
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_phys') }}" />
                                                    <x-text-input type="number" x-model="form.game_physical_ability" min="1" max="99" class="w-full" />
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_potential') }}" />
                                                    <x-text-input type="number" x-model="form.potential" min="1" max="99" class="w-full" />
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_potential_low') }}" />
                                                    <x-text-input type="number" x-model="form.potential_low" min="1" max="99" class="w-full" />
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_potential_high') }}" />
                                                    <x-text-input type="number" x-model="form.potential_high" min="1" max="99" class="w-full" />
                                                </div>
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_durability') }}" />
                                                    <x-text-input type="number" x-model="form.durability" min="0" max="100" class="w-full" />
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Section: Financial --}}
                                        <div>
                                            <div class="text-[10px] text-text-muted uppercase tracking-wider font-semibold mb-2">{{ __('admin.section_financial') }}</div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div>
                                                    <x-input-label value="{{ __('admin.tpl_market_value') }}" />
                                                    <x-money-input size="md" x-model="marketValueEuros" :presets="[500000, 2000000, 5000000, 10000000, 20000000, 50000000, 100000000]" />
                                                </div>
                                                <div>
                                                    <div class="flex items-center gap-1.5 mb-1">
                                                        <x-input-label value="{{ __('admin.tpl_annual_wage') }}" class="mb-0" />
                                                        @include('editor.player-templates._wage-tooltip')
                                                    </div>
                                                    <x-money-input size="md" x-model="annualWageEuros" :presets="[100000, 500000, 1000000, 3000000, 5000000, 10000000, 20000000]" />
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Actions --}}
                                        <div class="flex items-center gap-2 pt-1">
                                            <x-primary-button type="button" color="blue" size="xs" @click="save()" x-bind:disabled="saving">
                                                <span x-show="!saving">{{ __('admin.save') }}</span>
                                                <span x-show="saving">...</span>
                                            </x-primary-button>
                                            <x-ghost-button type="button" color="slate" size="xs" @click="cancel()">
                                                {{ __('admin.cancel') }}
                                            </x-ghost-button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-surface-800 border border-border-default rounded-xl p-8 text-center">
            <p class="text-sm text-text-muted">{{ __('admin.no_data') }}</p>
        </div>
    @endforelse

    {{-- Audit modals for each template --}}
    @foreach($grouped->flatten() as $template)
        @include('editor.player-templates._audit-modal', ['template' => $template])
    @endforeach

    {{-- Add player modal --}}
    @include('editor.player-templates._add-player-modal')
</x-admin-layout>
