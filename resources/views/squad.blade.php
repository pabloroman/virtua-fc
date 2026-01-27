@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">
                    <h3 class="font-semibold text-xl text-slate-900 mb-6">{{ $game->team->name }} Squad</h3>

                    <table class="w-full text-sm">
                        <thead class="text-left text-slate-500 border-b">
                            <tr>
                                <th class="py-2 w-10"></th>
                                <th class="py-2">Name</th>
                                <th class="py-2 w-10"></th>
                                <th class="py-2 text-right">Value</th>
                                <th class="py-2 text-center">Contract</th>
                                <th class="py-2 text-center">Age</th>
                                <th class="py-2 w-4"></th>
                                <th class="py-2 text-center w-12">TEC</th>
                                <th class="py-2 text-center w-12">PHY</th>
                                <th class="py-2 text-center w-12">FIT</th>
                                <th class="py-2 text-center w-12">MOR</th>
                                <th class="py-2 text-center w-12">OVR</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([
                                ['name' => 'Goalkeepers', 'players' => $goalkeepers],
                                ['name' => 'Defenders', 'players' => $defenders],
                                ['name' => 'Midfielders', 'players' => $midfielders],
                                ['name' => 'Forwards', 'players' => $forwards],
                            ] as $group)
                                @if($group['players']->isNotEmpty())
                                    <tr class="bg-slate-100">
                                        <td colspan="12" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                            {{ $group['name'] }}
                                        </td>
                                    </tr>
                                    @foreach($group['players'] as $gamePlayer)
                                        @php
                                            $nextMatchday = $game->current_matchday + 1;
                                            $isUnavailable = !$gamePlayer->isAvailable($game->current_date, $nextMatchday);
                                            $unavailabilityReason = $gamePlayer->getUnavailabilityReason($game->current_date, $nextMatchday);
                                            $positionDisplay = $gamePlayer->position_display;
                                        @endphp
                                        <tr class="border-b border-slate-100 @if($isUnavailable) text-slate-400 @endif hover:bg-slate-50">
                                            {{-- Position --}}
                                            <td class="py-2 text-center">
                                                <span x-data="" x-tooltip.raw="{{ $gamePlayer->position }}" class="inline-flex items-center justify-center w-8 h-8 rounded text-xs font-bold cursor-help {{ $positionDisplay['bg'] }} {{ $positionDisplay['text'] }}">
                                                    {{ $positionDisplay['abbreviation'] }}
                                                </span>
                                            </td>
                                            {{-- Name --}}
                                            <td class="py-2">
                                                <div class="font-medium text-slate-900 @if($isUnavailable) text-slate-400 @endif">
                                                    {{ $gamePlayer->player->name }}
                                                </div>
                                                @if($unavailabilityReason)
                                                    <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                                @endif
                                            </td>
                                            {{-- Nationality --}}
                                            <td class="py-2">
                                                <div class="flex gap-1">
                                                    @foreach($gamePlayer->nationality_flags as $flag)
                                                        <img src="/flags/{{ $flag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm" title="{{ $flag['name'] }}">
                                                    @endforeach
                                                </div>
                                            </td>
                                            {{-- Market Value --}}
                                            <td class="py-2 text-right text-slate-600">{{ $gamePlayer->market_value }}</td>
                                            {{-- Contract --}}
                                            <td class="py-2 text-center text-slate-600">
                                                @if($gamePlayer->contract_until)
                                                    {{ $gamePlayer->contract_until->format('M Y') }}
                                                @endif
                                            </td>
                                            {{-- Age --}}
                                            <td class="py-2 text-center">{{ $gamePlayer->player->age }}</td>
                                            {{-- Separator --}}
                                            <td class="py-2">
                                                <div class="w-px h-6 bg-slate-200 mx-auto"></div>
                                            </td>
                                            {{-- Technical --}}
                                            <td class="py-2 text-center @if($gamePlayer->technical_ability >= 80) text-green-600 @elseif($gamePlayer->technical_ability >= 70) text-lime-600 @elseif($gamePlayer->technical_ability < 60) text-slate-400 @endif">
                                                {{ $gamePlayer->technical_ability }}
                                            </td>
                                            {{-- Physical --}}
                                            <td class="py-2 text-center @if($gamePlayer->physical_ability >= 80) text-green-600 @elseif($gamePlayer->physical_ability >= 70) text-lime-600 @elseif($gamePlayer->physical_ability < 60) text-slate-400 @endif">
                                                {{ $gamePlayer->physical_ability }}
                                            </td>
                                            {{-- Fitness --}}
                                            <td class="py-2 text-center @if($gamePlayer->fitness < 70) text-yellow-600 @elseif($gamePlayer->fitness < 50) text-red-500 @endif">
                                                {{ $gamePlayer->fitness }}
                                            </td>
                                            {{-- Morale --}}
                                            <td class="py-2 text-center @if($gamePlayer->morale < 60) text-red-500 @elseif($gamePlayer->morale < 70) text-yellow-600 @endif">
                                                {{ $gamePlayer->morale }}
                                            </td>
                                            {{-- Overall --}}
                                            <td class="py-2 text-center">
                                                <span class="font-bold @if($gamePlayer->overall_score >= 80) text-green-600 @elseif($gamePlayer->overall_score >= 70) text-lime-600 @elseif($gamePlayer->overall_score >= 60) text-yellow-600 @else text-slate-500 @endif">
                                                    {{ $gamePlayer->overall_score }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                    </table>

                    {{-- Squad summary --}}
                    <div class="mt-8 pt-6 border-t">
                        <div class="flex gap-8 text-sm text-slate-600">
                            <div>
                                <span class="font-semibold text-slate-900">{{ $goalkeepers->count() + $defenders->count() + $midfielders->count() + $forwards->count() }}</span>
                                <span class="text-slate-400 ml-1">players</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-amber-100 text-amber-700">GK</span>
                                <span class="font-medium">{{ $goalkeepers->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-blue-100 text-blue-700">DF</span>
                                <span class="font-medium">{{ $defenders->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-green-100 text-green-700">MF</span>
                                <span class="font-medium">{{ $midfielders->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold bg-red-100 text-red-700">FW</span>
                                <span class="font-medium">{{ $forwards->count() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
