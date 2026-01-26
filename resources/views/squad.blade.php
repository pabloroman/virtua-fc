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
                        <thead class="text-left text-gray-500 border-b">
                            <tr>
                                <th class="py-2">Name</th>
                                <th class="py-2">Position</th>
                                <th class="py-2 text-center">Age</th>
                                <th class="py-2 text-center">OVR</th>
                                <th class="py-2 text-center" title="Technical / Physical / Fitness / Morale">TEC/PHY/FIT/MOR</th>
                                <th class="py-2 text-right">Value</th>
                                <th class="py-2">Contract</th>
                                <th class="py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([
                                ['name' => 'Goalkeepers', 'players' => $goalkeepers, 'bg' => 'bg-amber-50'],
                                ['name' => 'Defenders', 'players' => $defenders, 'bg' => 'bg-white'],
                                ['name' => 'Midfielders', 'players' => $midfielders, 'bg' => 'bg-slate-50'],
                                ['name' => 'Forwards', 'players' => $forwards, 'bg' => 'bg-white'],
                            ] as $group)
                                @if($group['players']->isNotEmpty())
                                    @foreach($group['players'] as $gamePlayer)
                                        @php
                                            $nextMatchday = $game->current_matchday + 1;
                                            $isUnavailable = !$gamePlayer->isAvailable($game->current_date, $nextMatchday);
                                            $unavailabilityReason = $gamePlayer->getUnavailabilityReason($game->current_date, $nextMatchday);
                                            $overall = $gamePlayer->overall_score;
                                        @endphp
                                        <tr class="border-t {{ $group['bg'] }} @if($isUnavailable) text-gray-400 @endif">
                                            <td class="py-2 font-medium">{{ $gamePlayer->player->name }}</td>
                                            <td class="py-2 text-gray-600">{{ $gamePlayer->position }}</td>
                                            <td class="py-2 text-center">{{ $gamePlayer->player->age }}</td>
                                            <td class="py-2 text-center">
                                                <span class="font-semibold @if($overall >= 80) text-green-600 @elseif($overall >= 70) text-lime-600 @elseif($overall >= 60) text-yellow-600 @else text-gray-500 @endif">
                                                    {{ $overall }}
                                                </span>
                                            </td>
                                            <td class="py-2 text-center text-xs text-gray-500">
                                                <span title="Technical">{{ $gamePlayer->technical_ability }}</span>
                                                <span class="text-gray-300">/</span>
                                                <span title="Physical">{{ $gamePlayer->physical_ability }}</span>
                                                <span class="text-gray-300">/</span>
                                                <span title="Fitness" class="@if($gamePlayer->fitness < 70) text-yellow-600 @endif">{{ $gamePlayer->fitness }}</span>
                                                <span class="text-gray-300">/</span>
                                                <span title="Morale" class="@if($gamePlayer->morale < 60) text-red-500 @endif">{{ $gamePlayer->morale }}</span>
                                            </td>
                                            <td class="py-2 text-right">{{ $gamePlayer->market_value }}</td>
                                            <td class="py-2 text-gray-600">
                                                @if($gamePlayer->contract_until)
                                                    {{ $gamePlayer->contract_until->format('M Y') }}
                                                @endif
                                            </td>
                                            <td class="py-2">
                                                @if($unavailabilityReason)
                                                    <span class="text-red-600" title="{{ $unavailabilityReason }}">{{ $unavailabilityReason }}</span>
                                                @else
                                                    <span class="text-green-600">Available</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                    </table>

                    {{-- Squad summary --}}
                    <div class="mt-6 pt-6 border-t">
                        <div class="flex gap-8 text-sm text-gray-600">
                            <div>
                                <span class="font-medium">{{ $goalkeepers->count() + $defenders->count() + $midfielders->count() + $forwards->count() }}</span>
                                <span class="text-gray-400">players</span>
                            </div>
                            <div>
                                <span class="font-medium">{{ $goalkeepers->count() }}</span>
                                <span class="text-gray-400">GK</span>
                            </div>
                            <div>
                                <span class="font-medium">{{ $defenders->count() }}</span>
                                <span class="text-gray-400">DEF</span>
                            </div>
                            <div>
                                <span class="font-medium">{{ $midfielders->count() }}</span>
                                <span class="text-gray-400">MID</span>
                            </div>
                            <div>
                                <span class="font-medium">{{ $forwards->count() }}</span>
                                <span class="text-gray-400">FWD</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
