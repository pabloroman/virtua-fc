@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">{{ __('squad.squad_development') }}</h3>
                        <a href="{{ route('game.squad', $game->id) }}" class="text-sm text-sky-600 hover:text-sky-800">
                            &larr; {{ __('squad.back_to_squad') }}
                        </a>
                    </div>

                    {{-- Filter tabs --}}
                    <div class="flex gap-2 mb-6">
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
                        <table class="w-full text-sm">
                            <thead class="text-left border-b">
                                <tr>
                                    <th class="font-semibold py-2 w-8"></th>
                                    <th class="font-semibold py-2">{{ __('app.player') }}</th>
                                    <th class="font-semibold py-2 text-center">{{ __('app.age') }}</th>
                                    <th class="font-semibold py-2 text-center">{{ __('squad.pot') }}</th>
                                    <th class="font-semibold py-2 text-center">{{ __('squad.cur') }}</th>
                                    <th class="font-semibold py-2 text-center">{{ __('app.status') }}</th>
                                    <th class="font-semibold py-2 text-center">{{ __('squad.apps') }}</th>
                                    <th class="font-semibold py-2 text-center">{{ __('squad.projection') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($players as $player)
                                    @php
                                        $currentAbility = (int) round(($player->current_technical_ability + $player->current_physical_ability) / 2);
                                        $potentialGap = $player->potential_high ? $player->potential_high - $currentAbility : 0;
                                        $hasStarterBonus = $player->season_appearances >= 15;
                                    @endphp
                                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                                        {{-- Status indicator --}}
                                        <td class="py-3 text-center">
                                            @if($player->development_status === 'growing')
                                                <span class="text-green-500 font-bold" title="{{ __('squad.growing') }}">^</span>
                                            @elseif($player->development_status === 'declining')
                                                <span class="text-red-500 font-bold" title="{{ __('squad.declining') }}">v</span>
                                            @else
                                                <span class="text-slate-300">-</span>
                                            @endif
                                        </td>
                                        {{-- Player name --}}
                                        <td class="py-3">
                                            <div class="flex items-center gap-3">
                                                @php $positionDisplay = $player->position_display; @endphp
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded text-xs font-bold {{ $positionDisplay['bg'] }} {{ $positionDisplay['text'] }}">
                                                    {{ $positionDisplay['abbreviation'] }}
                                                </span>
                                                <div>
                                                    <div class="font-medium text-slate-900">{{ $player->name }}</div>
                                                    <div class="text-xs text-slate-500">{{ $player->position }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        {{-- Age --}}
                                        <td class="py-3 text-center">
                                            <span class="@if($player->age <= 23) text-green-600 @elseif($player->age >= 30) text-orange-500 @endif">
                                                {{ $player->age }}
                                            </span>
                                        </td>
                                        {{-- Potential range --}}
                                        <td class="py-3 text-center">
                                            @if($player->potential_low && $player->potential_high)
                                                <span class="font-medium @if($potentialGap >= 8) text-sky-600 @elseif($potentialGap >= 4) text-sky-500 @else text-slate-600 @endif">
                                                    {{ $player->potential_low }}-{{ $player->potential_high }}
                                                </span>
                                            @else
                                                <span class="text-slate-400">?</span>
                                            @endif
                                        </td>
                                        {{-- Current ability --}}
                                        <td class="py-3 text-center">
                                            <span class="font-bold @if($currentAbility >= 80) text-green-600 @elseif($currentAbility >= 70) text-lime-600 @elseif($currentAbility >= 60) text-yellow-600 @else text-slate-500 @endif">
                                                {{ $currentAbility }}
                                            </span>
                                        </td>
                                        {{-- Development status --}}
                                        <td class="py-3 text-center">
                                            @if($player->development_status === 'growing')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                                    {{ __('squad.growing') }}
                                                </span>
                                            @elseif($player->development_status === 'peak')
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                                    {{ __('squad.peak') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                                    {{ __('squad.declining') }}
                                                </span>
                                            @endif
                                        </td>
                                        {{-- Season appearances --}}
                                        <td class="py-3 text-center">
                                            <span class="@if($hasStarterBonus) text-green-600 font-medium @else text-slate-500 @endif"
                                                  title="{{ $hasStarterBonus ? __('squad.qualifies_starter_bonus') : __('squad.needs_appearances', ['count' => 15]) }}">
                                                {{ $player->season_appearances }}
                                            </span>
                                        </td>
                                        {{-- Projection --}}
                                        <td class="py-3 text-center">
                                            @if($player->projection > 0)
                                                <span class="font-medium text-green-600">
                                                    {{ $currentAbility + $player->projection }} (+{{ $player->projection }})
                                                </span>
                                            @elseif($player->projection < 0)
                                                <span class="font-medium text-red-500">
                                                    {{ $currentAbility + $player->projection }} ({{ $player->projection }})
                                                </span>
                                            @else
                                                <span class="text-slate-500">
                                                    {{ $currentAbility }} (=)
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    {{-- Legend --}}
                    <div class="mt-8 pt-6 border-t">
                        <div class="flex flex-wrap gap-6 text-xs text-slate-500">
                            <div class="flex items-center gap-2">
                                <span class="text-green-500 font-bold">^</span>
                                <span>{{ __('squad.growing') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-red-500 font-bold">v</span>
                                <span>{{ __('squad.declining') }}</span>
                            </div>
                            <div>
                                <span class="font-medium">{{ __('squad.cur') }}</span> = {{ __('squad.current_ability') }}
                            </div>
                            <div>
                                <span class="font-medium">{{ __('squad.pot') }}</span> = {{ __('squad.potential_range') }}
                            </div>
                            <div>
                                <span class="font-medium">{{ __('squad.apps') }}</span> = {{ __('squad.season_appearances') }}
                                <span class="text-slate-400">(15+ = {{ __('squad.starter_bonus') }})</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
