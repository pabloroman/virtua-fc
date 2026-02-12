@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    <x-section-nav :items="[
                        ['href' => route('game.squad', $game->id), 'label' => __('squad.squad'), 'active' => false],
                        ['href' => route('game.squad.development', $game->id), 'label' => __('squad.development'), 'active' => true],
                        ['href' => route('game.squad.stats', $game->id), 'label' => __('squad.stats'), 'active' => false],
                        ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false, 'badge' => $academyCount > 0 ? $academyCount : null],
                    ]" />

                    <div class="mt-6"></div>

                    {{-- Filter tabs --}}
                    <div class="flex gap-2 mb-6 overflow-x-auto scrollbar-hide">
                        <a href="{{ route('game.squad.development', ['gameId' => $game->id, 'filter' => 'high_potential']) }}"
                           class="px-4 py-2 rounded-full text-sm font-medium transition-colors
                                  {{ $filter === 'high_potential' ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            {{ __('squad.high_potential') }}
                            <span class="ml-1 text-xs opacity-75">({{ $stats['high_potential'] }})</span>
                        </a>
                        <a href="{{ route('game.squad.development', ['gameId' => $game->id, 'filter' => 'growing']) }}"
                           class="px-4 py-2 rounded-full text-sm font-medium transition-colors
                                  {{ $filter === 'growing' ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            {{ __('squad.growing') }}
                            <span class="ml-1 text-xs opacity-75">({{ $stats['growing'] }})</span>
                        </a>
                        <a href="{{ route('game.squad.development', ['gameId' => $game->id, 'filter' => 'declining']) }}"
                           class="px-4 py-2 rounded-full text-sm font-medium transition-colors
                                  {{ $filter === 'declining' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            {{ __('squad.declining') }}
                            <span class="ml-1 text-xs opacity-75">({{ $stats['declining'] }})</span>
                        </a>
                        <a href="{{ route('game.squad.development', $game->id) }}"
                           class="px-4 py-2 rounded-full text-sm font-medium transition-colors
                                  {{ $filter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                            {{ __('squad.all') }}
                            <span class="ml-1 text-xs opacity-75">({{ $stats['all'] }})</span>
                        </a>
                    </div>

                    @if($players->isEmpty())
                        <div class="text-center py-12 text-slate-500">
                            {{ __('squad.no_players_match_filter') }}
                        </div>
                    @else
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left border-b border-slate-300">
                                <tr>
                                    <th class="font-semibold py-2 pl-2">{{ __('app.player') }}</th>
                                    <th class="font-semibold py-2 text-center w-14">{{ __('app.age') }}</th>
                                    <th class="font-semibold py-2 pl-2" style="min-width: 180px">{{ __('squad.ability') }}</th>
                                    <th class="font-semibold py-2 text-center w-24">{{ __('app.status') }}</th>
                                    <th class="font-semibold py-2 text-center w-24 hidden md:table-cell">{{ __('squad.playing_time') }}</th>
                                    <th class="font-semibold py-2 text-center" style="min-width: 120px">{{ __('squad.projection') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($players as $player)
                                    @php
                                        $currentAbility = (int) round(($player->current_technical_ability + $player->current_physical_ability) / 2);
                                        $potentialGap = $player->potential_high ? $player->potential_high - $currentAbility : 0;
                                        $hasStarterBonus = $player->season_appearances >= 15;
                                        $projectedAbility = $currentAbility + ($player->projection ?? 0);
                                    @endphp
                                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                                        {{-- Player: badge + name + position --}}
                                        <td class="py-3 pl-2">
                                            <div class="flex items-center gap-3">
                                                <x-position-badge :position="$player->position" />
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-medium text-slate-900">{{ $player->name }}</span>
                                                    {{-- Development arrow --}}
                                                    @if($player->development_status === 'growing')
                                                        <svg class="w-3.5 h-3.5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                                        </svg>
                                                    @elseif($player->development_status === 'declining')
                                                        <svg class="w-3.5 h-3.5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                        </svg>
                                                    @else
                                                        <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                                        </svg>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Age --}}
                                        <td class="py-3 text-center">
                                            <span class="font-medium @if($player->age <= 23) text-green-600 @elseif($player->age >= 30) text-orange-500 @else text-slate-700 @endif">
                                                {{ $player->age }}
                                            </span>
                                        </td>

                                        {{-- Ability: potential bar (replaces POT + CUR) --}}
                                        <td class="py-3 pl-2">
                                            <x-potential-bar
                                                :current-ability="$currentAbility"
                                                :potential-low="$player->potential_low"
                                                :potential-high="$player->potential_high"
                                                :projection="$player->projection"
                                            />
                                        </td>

                                        {{-- Status badge with arrow --}}
                                        <td class="py-3 text-center">
                                            @if($player->development_status === 'growing')
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                                    {{ __('squad.growing') }}
                                                </span>
                                            @elseif($player->development_status === 'peak')
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                                    {{ __('squad.peak') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                                    {{ __('squad.declining') }}
                                                </span>
                                            @endif
                                        </td>

                                        {{-- Playing time: progress bar toward 15 apps --}}
                                        <td class="py-3 text-center hidden md:table-cell">
                                            <div class="flex flex-col items-center gap-1">
                                                <div class="w-16 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full {{ $hasStarterBonus ? 'bg-green-500' : 'bg-amber-500' }}"
                                                         style="width: {{ min(100, ($player->season_appearances / 15) * 100) }}%"></div>
                                                </div>
                                                <span class="text-xs {{ $hasStarterBonus ? 'text-green-600 font-medium' : 'text-slate-500' }}"
                                                      title="{{ $hasStarterBonus ? __('squad.qualifies_starter_bonus') : __('squad.needs_appearances', ['count' => 15]) }}">
                                                    {{ $player->season_appearances }}/15
                                                    @if($hasStarterBonus)
                                                        <svg class="w-3 h-3 inline text-green-500 -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                                    @endif
                                                </span>
                                            </div>
                                        </td>

                                        {{-- Projection: visual before → after --}}
                                        <td class="py-3 text-center">
                                            @if($player->projection > 0)
                                                <div class="flex items-center justify-center gap-1">
                                                    <span class="text-slate-500">{{ $currentAbility }}</span>
                                                    <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                    <span class="font-bold text-green-600">{{ $projectedAbility }}</span>
                                                    <span class="text-xs text-green-500">(+{{ $player->projection }})</span>
                                                </div>
                                            @elseif($player->projection < 0)
                                                <div class="flex items-center justify-center gap-1">
                                                    <span class="text-slate-500">{{ $currentAbility }}</span>
                                                    <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                    <span class="font-bold text-red-500">{{ $projectedAbility }}</span>
                                                    <span class="text-xs text-red-400">({{ $player->projection }})</span>
                                                </div>
                                            @else
                                                <div class="flex items-center justify-center gap-1">
                                                    <span class="text-slate-500">{{ $currentAbility }}</span>
                                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                                    <span class="font-medium text-slate-500">{{ $projectedAbility }}</span>
                                                    <span class="text-xs text-slate-400">(=)</span>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    @endif

                    {{-- Legend --}}
                    <div class="mt-8 pt-6 border-t">
                        <div class="flex flex-wrap gap-6 text-xs text-slate-500">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                <span>{{ __('squad.growing') }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                <span>{{ __('squad.declining') }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                <span>{{ __('squad.peak') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-2 bg-sky-100 rounded-full border border-sky-200"></div>
                                <span>{{ __('squad.potential_range') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-1.5 bg-amber-500 rounded-full"></div>
                                <span>&lt; 15 {{ __('squad.apps') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-1.5 bg-green-500 rounded-full"></div>
                                <span>15+ {{ __('squad.apps') }} = {{ __('squad.starter_bonus') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
