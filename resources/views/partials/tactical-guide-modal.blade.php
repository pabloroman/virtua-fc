{{-- Tactical Guide Modal --}}
<x-modal name="tactical-guide" max-width="3xl">
    <x-modal-header modal-name="tactical-guide">{{ __('game.tactical_guide_title') }}</x-modal-header>
    <div class="p-5 space-y-6 max-h-[80vh] overflow-y-auto">

        {{-- Intro --}}
        <div class="bg-surface-700/50 border border-border-strong rounded-lg p-4">
            <p class="text-sm text-text-secondary">{{ __('game.tactical_guide_intro') }}</p>
        </div>

        {{-- Formations --}}
        <section>
            <h3 class="text-base font-semibold text-text-primary mb-3 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-violet-500 rounded-full"></span>
                {{ __('game.tg_formations') }}
            </h3>
            <div class="bg-surface-800 rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-default text-sm">
                        <thead class="bg-surface-700/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-text-body">{{ __('game.tg_formation') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_your_goals') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_goals_conceded') }}</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-text-body hidden md:table-cell">{{ __('game.tg_profile') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-default">
                            @foreach($guideFormations as $f)
                            <tr>
                                <td class="px-4 py-2.5 font-mono font-semibold text-text-primary">{{ $f['name'] }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $f['attack'] > 1.0 ? 'text-accent-green' : ($f['attack'] < 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $f['attack'] == 1.0 ? '-' : ($f['attack'] > 1.0 ? '+' : '') . round(($f['attack'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $f['defense'] < 1.0 ? 'text-accent-green' : ($f['defense'] > 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $f['defense'] == 1.0 ? '-' : ($f['defense'] > 1.0 ? '+' : '') . round(($f['defense'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 hidden md:table-cell text-text-muted">
                                    {{ __('game.formation_profile_' . str_replace('-', '', $f['name'])) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Mentality --}}
        <section>
            <h3 class="text-base font-semibold text-text-primary mb-3 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-rose-500 rounded-full"></span>
                {{ __('game.tg_mentality') }}
            </h3>
            <div class="bg-surface-800 rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-default text-sm">
                        <thead class="bg-surface-700/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-text-body">{{ __('game.tg_mentality') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_your_goals') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_goals_conceded') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-default">
                            @foreach($guideMentalities as $m)
                            <tr>
                                <td class="px-4 py-2.5 font-semibold text-text-primary">{{ __('game.mentality_' . $m['name']) }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $m['own_goals'] > 1.0 ? 'text-accent-green' : ($m['own_goals'] < 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $m['own_goals'] == 1.0 ? '-' : ($m['own_goals'] > 1.0 ? '+' : '') . round(($m['own_goals'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $m['opponent_goals'] < 1.0 ? 'text-accent-green' : ($m['opponent_goals'] > 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $m['opponent_goals'] == 1.0 ? '-' : ($m['opponent_goals'] > 1.0 ? '+' : '') . round(($m['opponent_goals'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Playing Style --}}
        <section>
            <h3 class="text-base font-semibold text-text-primary mb-3 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-accent-blue rounded-full"></span>
                {{ __('game.tg_playing_style') }}
            </h3>
            <div class="bg-surface-800 rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-default text-sm">
                        <thead class="bg-surface-700/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-text-body">{{ __('game.tg_style') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_your_goals') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_goals_conceded') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_energy') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-default">
                            @foreach($guidePlayingStyles as $s)
                            <tr>
                                <td class="px-4 py-2.5 font-semibold text-text-primary">{{ $s['label'] }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $s['own_xg'] > 1.0 ? 'text-accent-green' : ($s['own_xg'] < 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $s['own_xg'] == 1.0 ? '-' : ($s['own_xg'] > 1.0 ? '+' : '') . round(($s['own_xg'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $s['opp_xg'] < 1.0 ? 'text-accent-green' : ($s['opp_xg'] > 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $s['opp_xg'] == 1.0 ? '-' : ($s['opp_xg'] > 1.0 ? '+' : '') . round(($s['opp_xg'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $s['energy'] > 1.0 ? 'text-amber-600' : ($s['energy'] < 1.0 ? 'text-accent-green' : 'text-text-muted') }}">
                                        {{ $s['energy'] == 1.0 ? '-' : ($s['energy'] > 1.0 ? '+' : '') . round(($s['energy'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Pressing --}}
        <section>
            <h3 class="text-base font-semibold text-text-primary mb-3 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-accent-gold rounded-full"></span>
                {{ __('game.tg_pressing') }}
            </h3>
            <div class="bg-surface-800 rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-default text-sm">
                        <thead class="bg-surface-700/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-text-body">{{ __('game.tg_pressing') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_your_goals') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_goals_conceded') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_energy') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-default">
                            @foreach($guidePressingOptions as $p)
                            <tr>
                                <td class="px-4 py-2.5 font-semibold text-text-primary">{{ $p['label'] }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $p['own_xg'] > 1.0 ? 'text-accent-green' : ($p['own_xg'] < 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $p['own_xg'] == 1.0 ? '-' : ($p['own_xg'] > 1.0 ? '+' : '') . round(($p['own_xg'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    @if($p['fades'])
                                        <span class="text-accent-green">{{ round(($p['opp_xg'] - 1) * 100) }}%</span>
                                        <span class="text-text-secondary text-xs">&rarr; {{ round(($p['fade_to'] - 1) * 100) }}%</span>
                                    @else
                                        <span class="{{ $p['opp_xg'] < 1.0 ? 'text-accent-green' : ($p['opp_xg'] > 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                            {{ $p['opp_xg'] == 1.0 ? '-' : ($p['opp_xg'] > 1.0 ? '+' : '') . round(($p['opp_xg'] - 1) * 100) . '%' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $p['energy'] > 1.0 ? 'text-amber-600' : ($p['energy'] < 1.0 ? 'text-accent-green' : 'text-text-muted') }}">
                                        {{ $p['energy'] == 1.0 ? '-' : ($p['energy'] > 1.0 ? '+' : '') . round(($p['energy'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-2 bg-accent-gold/10 border-t border-accent-gold/20 text-xs text-accent-gold">
                    {{ __('game.tg_pressing_fade_note') }}
                </div>
            </div>
        </section>

        {{-- Defensive Line --}}
        <section>
            <h3 class="text-base font-semibold text-text-primary mb-3 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-emerald-500 rounded-full"></span>
                {{ __('game.tg_defensive_line') }}
            </h3>
            <div class="bg-surface-800 rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-default text-sm">
                        <thead class="bg-surface-700/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-text-body">{{ __('game.tg_line') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_your_goals') }}</th>
                                <th class="px-4 py-2.5 text-center font-semibold text-text-body">{{ __('game.tg_goals_conceded') }}</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-text-body hidden md:table-cell">{{ __('game.tg_note') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-default">
                            @foreach($guideDefensiveLines as $d)
                            <tr>
                                <td class="px-4 py-2.5 font-semibold text-text-primary">{{ $d['label'] }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $d['own_xg'] > 1.0 ? 'text-accent-green' : ($d['own_xg'] < 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $d['own_xg'] == 1.0 ? '-' : ($d['own_xg'] > 1.0 ? '+' : '') . round(($d['own_xg'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="{{ $d['opp_xg'] < 1.0 ? 'text-accent-green' : ($d['opp_xg'] > 1.0 ? 'text-accent-red' : 'text-text-muted') }}">
                                        {{ $d['opp_xg'] == 1.0 ? '-' : ($d['opp_xg'] > 1.0 ? '+' : '') . round(($d['opp_xg'] - 1) * 100) . '%' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 hidden md:table-cell text-xs text-text-muted">
                                    @if($d['threshold'] > 0)
                                        {{ __('game.tg_high_line_note', ['threshold' => $d['threshold']]) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-2 bg-accent-green/10 border-t border-accent-green/20 text-xs text-accent-green md:hidden">
                    {{ __('game.tg_high_line_note', ['threshold' => 80]) }}
                </div>
            </div>
        </section>

        {{-- Tactical Interactions --}}
        <section>
            <h3 class="text-base font-semibold text-text-primary mb-3 flex items-center gap-2">
                <span class="w-1.5 h-5 bg-indigo-500 rounded-full"></span>
                {{ __('game.tg_interactions') }}
            </h3>
            <p class="text-sm text-text-muted mb-3">{{ __('game.tg_interactions_intro') }}</p>
            <div class="space-y-3">
                {{-- Counter vs Attacking + High Line --}}
                <div class="bg-surface-700/50 rounded-lg border border-border-strong p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-accent-blue/10 text-accent-blue">{{ __('game.style_counter_attack') }}</span>
                            <span class="text-text-secondary text-xs">vs</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-accent-red/10 text-accent-red">{{ __('game.mentality_attacking') }}</span>
                            <span class="text-text-secondary text-xs">+</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-accent-green/10 text-accent-green">{{ __('game.defline_high_line') }}</span>
                        </div>
                        <p class="text-xs text-text-muted mt-1">{{ __('game.tg_counter_bonus_desc') }}</p>
                    </div>
                    <span class="text-accent-green font-semibold text-sm shrink-0">+{{ round(($tacticalInteractions['counter_vs_attacking_high_line'] - 1) * 100) }}% {{ __('game.tg_your_goals') }}</span>
                </div>

                {{-- Possession vs High Press --}}
                <div class="bg-surface-700/50 rounded-lg border border-border-strong p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-accent-blue/10 text-accent-blue">{{ __('game.style_possession') }}</span>
                            <span class="text-text-secondary text-xs">vs</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-accent-gold/10 text-accent-gold">{{ __('game.pressing_high_press') }}</span>
                        </div>
                        <p class="text-xs text-text-muted mt-1">{{ __('game.tg_possession_penalty_desc') }}</p>
                    </div>
                    <span class="text-accent-red font-semibold text-sm shrink-0">{{ round(($tacticalInteractions['possession_disrupted_by_high_press'] - 1) * 100) }}% {{ __('game.tg_your_goals') }}</span>
                </div>

                {{-- Direct vs High Press --}}
                <div class="bg-surface-700/50 rounded-lg border border-border-strong p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-accent-blue/10 text-accent-blue">{{ __('game.style_direct') }}</span>
                            <span class="text-text-secondary text-xs">vs</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-xs font-medium bg-accent-gold/10 text-accent-gold">{{ __('game.pressing_high_press') }}</span>
                        </div>
                        <p class="text-xs text-text-muted mt-1">{{ __('game.tg_direct_bonus_desc') }}</p>
                    </div>
                    <span class="text-accent-green font-semibold text-sm shrink-0">+{{ round(($tacticalInteractions['direct_bypasses_high_press'] - 1) * 100) }}% {{ __('game.tg_your_goals') }}</span>
                </div>
            </div>
        </section>

        {{-- Legend --}}
        <section>
            <div class="bg-surface-700/50 border border-border-strong rounded-lg p-4">
                <h3 class="text-sm font-semibold text-text-body mb-2">{{ __('game.tg_legend') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="text-accent-green font-semibold">+5%</span>
                        <span class="text-text-muted">{{ __('game.tg_legend_positive') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-accent-red font-semibold">-5%</span>
                        <span class="text-text-muted">{{ __('game.tg_legend_negative') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-amber-600 font-semibold">+10%</span>
                        <span class="text-text-muted">{{ __('game.tg_legend_energy') }}</span>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-modal>
