@php /** @var App\Models\Game $game */ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$nextMatch" :continue-to-home="true"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 pb-12">
            {{-- Page Title --}}
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-xl md:text-2xl font-bold text-white">{{ __('game.tactical_guide_title') }}</h1>
                <a href="{{ route('game.lineup', $game->id) }}" class="text-sm text-sky-400 hover:text-sky-300 transition-colors">
                    &larr; {{ __('app.starting_xi') }}
                </a>
            </div>

            {{-- Intro --}}
            <div class="bg-white/5 border border-white/10 rounded-lg p-4 mb-6">
                <p class="text-sm text-slate-300">{{ __('game.tactical_guide_intro') }}</p>
            </div>

            {{-- Formations --}}
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-violet-500 rounded-full"></span>
                    {{ __('game.tg_formations') }}
                </h2>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('game.tg_formation') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_your_goals') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_goals_conceded') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700 hidden md:table-cell">{{ __('game.tg_profile') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($formations as $f)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-mono font-semibold text-slate-900">{{ $f['name'] }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $f['attack'] > 1.0 ? 'text-green-600' : ($f['attack'] < 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                            {{ $f['attack'] == 1.0 ? '-' : ($f['attack'] > 1.0 ? '+' : '') . round(($f['attack'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $f['defense'] < 1.0 ? 'text-green-600' : ($f['defense'] > 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                            {{ $f['defense'] == 1.0 ? '-' : ($f['defense'] > 1.0 ? '+' : '') . round(($f['defense'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell text-slate-500">
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
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-rose-500 rounded-full"></span>
                    {{ __('game.tg_mentality') }}
                </h2>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('game.tg_mentality') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_your_goals') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_goals_conceded') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($mentalities as $m)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ __('game.mentality_' . $m['name']) }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $m['own_goals'] > 1.0 ? 'text-green-600' : ($m['own_goals'] < 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                            {{ $m['own_goals'] == 1.0 ? '-' : ($m['own_goals'] > 1.0 ? '+' : '') . round(($m['own_goals'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $m['opponent_goals'] < 1.0 ? 'text-green-600' : ($m['opponent_goals'] > 1.0 ? 'text-red-600' : 'text-slate-500') }}">
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
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-sky-500 rounded-full"></span>
                    {{ __('game.tg_playing_style') }}
                </h2>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('game.tg_style') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_your_goals') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_goals_conceded') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_energy') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($playingStyles as $s)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $s['label'] }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $s['own_xg'] > 1.0 ? 'text-green-600' : ($s['own_xg'] < 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                            {{ $s['own_xg'] == 1.0 ? '-' : ($s['own_xg'] > 1.0 ? '+' : '') . round(($s['own_xg'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $s['opp_xg'] < 1.0 ? 'text-green-600' : ($s['opp_xg'] > 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                            {{ $s['opp_xg'] == 1.0 ? '-' : ($s['opp_xg'] > 1.0 ? '+' : '') . round(($s['opp_xg'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $s['energy'] > 1.0 ? 'text-amber-600' : ($s['energy'] < 1.0 ? 'text-green-600' : 'text-slate-500') }}">
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
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-amber-500 rounded-full"></span>
                    {{ __('game.tg_pressing') }}
                </h2>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('game.tg_pressing') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_your_goals') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_goals_conceded') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_energy') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($pressingOptions as $p)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $p['label'] }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $p['own_xg'] > 1.0 ? 'text-green-600' : ($p['own_xg'] < 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                            {{ $p['own_xg'] == 1.0 ? '-' : ($p['own_xg'] > 1.0 ? '+' : '') . round(($p['own_xg'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($p['fades'])
                                            <span class="text-green-600">{{ round(($p['opp_xg'] - 1) * 100) }}%</span>
                                            <span class="text-slate-400 text-xs">&rarr; {{ round(($p['fade_to'] - 1) * 100) }}%</span>
                                        @else
                                            <span class="{{ $p['opp_xg'] < 1.0 ? 'text-green-600' : ($p['opp_xg'] > 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                                {{ $p['opp_xg'] == 1.0 ? '-' : ($p['opp_xg'] > 1.0 ? '+' : '') . round(($p['opp_xg'] - 1) * 100) . '%' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $p['energy'] > 1.0 ? 'text-amber-600' : ($p['energy'] < 1.0 ? 'text-green-600' : 'text-slate-500') }}">
                                            {{ $p['energy'] == 1.0 ? '-' : ($p['energy'] > 1.0 ? '+' : '') . round(($p['energy'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-2 bg-amber-50 border-t border-amber-100 text-xs text-amber-700">
                        {{ __('game.tg_pressing_fade_note') }}
                    </div>
                </div>
            </section>

            {{-- Defensive Line --}}
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-emerald-500 rounded-full"></span>
                    {{ __('game.tg_defensive_line') }}
                </h2>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">{{ __('game.tg_line') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_your_goals') }}</th>
                                    <th class="px-4 py-3 text-center font-semibold text-slate-700">{{ __('game.tg_goals_conceded') }}</th>
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700 hidden md:table-cell">{{ __('game.tg_note') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($defensiveLines as $d)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-semibold text-slate-900">{{ $d['label'] }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $d['own_xg'] > 1.0 ? 'text-green-600' : ($d['own_xg'] < 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                            {{ $d['own_xg'] == 1.0 ? '-' : ($d['own_xg'] > 1.0 ? '+' : '') . round(($d['own_xg'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="{{ $d['opp_xg'] < 1.0 ? 'text-green-600' : ($d['opp_xg'] > 1.0 ? 'text-red-600' : 'text-slate-500') }}">
                                            {{ $d['opp_xg'] == 1.0 ? '-' : ($d['opp_xg'] > 1.0 ? '+' : '') . round(($d['opp_xg'] - 1) * 100) . '%' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 hidden md:table-cell text-xs text-slate-500">
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
                    <div class="px-4 py-2 bg-emerald-50 border-t border-emerald-100 text-xs text-emerald-700 md:hidden">
                        {{ __('game.tg_high_line_note', ['threshold' => 80]) }}
                    </div>
                </div>
            </section>

            {{-- Tactical Interactions --}}
            <section class="mb-8">
                <h2 class="text-lg font-semibold text-white mb-3 flex items-center gap-2">
                    <span class="w-1.5 h-5 bg-indigo-500 rounded-full"></span>
                    {{ __('game.tg_interactions') }}
                </h2>
                <p class="text-sm text-slate-400 mb-3">{{ __('game.tg_interactions_intro') }}</p>
                <div class="space-y-3">
                    {{-- Counter vs Attacking + High Line --}}
                    <div class="bg-white rounded-lg shadow p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-100 text-sky-800">{{ __('game.style_counter_attack') }}</span>
                                <span class="text-slate-400 text-xs">vs</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800">{{ __('game.mentality_attacking') }}</span>
                                <span class="text-slate-400 text-xs">+</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-100 text-emerald-800">{{ __('game.defline_high_line') }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">{{ __('game.tg_counter_bonus_desc') }}</p>
                        </div>
                        <span class="text-green-600 font-semibold text-sm shrink-0">+{{ round(($interactions['counter_vs_attacking_high_line'] - 1) * 100) }}% {{ __('game.tg_your_goals') }}</span>
                    </div>

                    {{-- Possession vs High Press --}}
                    <div class="bg-white rounded-lg shadow p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-100 text-sky-800">{{ __('game.style_possession') }}</span>
                                <span class="text-slate-400 text-xs">vs</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">{{ __('game.pressing_high_press') }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">{{ __('game.tg_possession_penalty_desc') }}</p>
                        </div>
                        <span class="text-red-600 font-semibold text-sm shrink-0">{{ round(($interactions['possession_disrupted_by_high_press'] - 1) * 100) }}% {{ __('game.tg_your_goals') }}</span>
                    </div>

                    {{-- Direct vs High Press --}}
                    <div class="bg-white rounded-lg shadow p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-100 text-sky-800">{{ __('game.style_direct') }}</span>
                                <span class="text-slate-400 text-xs">vs</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">{{ __('game.pressing_high_press') }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">{{ __('game.tg_direct_bonus_desc') }}</p>
                        </div>
                        <span class="text-green-600 font-semibold text-sm shrink-0">+{{ round(($interactions['direct_bypasses_high_press'] - 1) * 100) }}% {{ __('game.tg_your_goals') }}</span>
                    </div>
                </div>
            </section>

            {{-- Legend --}}
            <section>
                <div class="bg-white/5 border border-white/10 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-white mb-2">{{ __('game.tg_legend') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-green-600 font-semibold">+5%</span>
                            <span class="text-slate-400">{{ __('game.tg_legend_positive') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-red-600 font-semibold">-5%</span>
                            <span class="text-slate-400">{{ __('game.tg_legend_negative') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-amber-600 font-semibold">+10%</span>
                            <span class="text-slate-400">{{ __('game.tg_legend_energy') }}</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
