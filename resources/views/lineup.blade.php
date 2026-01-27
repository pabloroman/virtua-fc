@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch $match */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$match"></x-game-header>
    </x-slot>

    <div x-data="{
        selectedPlayers: @js($currentLineup ?? []),
        autoLineup: @js($autoLineup ?? []),
        get selectedCount() { return this.selectedPlayers.length },
        isSelected(id) { return this.selectedPlayers.includes(id) },
        toggle(id, isUnavailable) {
            if (isUnavailable) return;
            if (this.isSelected(id)) {
                this.selectedPlayers = this.selectedPlayers.filter(p => p !== id)
            } else if (this.selectedCount < 11) {
                this.selectedPlayers.push(id)
            }
        },
        quickSelect() { this.selectedPlayers = [...this.autoLineup] },
        clearSelection() { this.selectedPlayers = [] }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-12">
                    {{-- Match Info --}}
                    <div class="mb-6">
                        <h3 class="font-semibold text-xl text-slate-900 mb-2">Lineup</h3>
                    </div>

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

                    <form method="POST" action="{{ route('game.lineup.save', [$game->id, $match->id]) }}">
                        @csrf

                        {{-- Hidden inputs for selected players --}}
                        <template x-for="playerId in selectedPlayers" :key="playerId">
                            <input type="hidden" name="players[]" :value="playerId">
                        </template>

                        {{-- Selection Count & Actions --}}
                        <div class="flex items-center justify-between mb-6 p-4 bg-slate-50 rounded-lg">
                            <div class="flex items-center gap-4">
                                <div class="text-lg">
                                    <span class="font-bold" :class="selectedCount === 11 ? 'text-sky-600' : 'text-slate-900'" x-text="selectedCount"></span>
                                    <span class="text-slate-500">/ 11 selected</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" @click="clearSelection()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-200 rounded transition-colors">
                                    Clear
                                </button>
                                <button type="button" @click="quickSelect()" class="px-4 py-2 text-sm bg-slate-200 text-slate-700 hover:bg-slate-300 rounded transition-colors">
                                    Auto Select
                                </button>
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-wide hover:bg-red-700 focus:bg-red-700 active:bg-red-900 transition ease-in-out duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="selectedCount !== 11"
                                >
                                    Confirm
                                </button>
                            </div>
                        </div>

                        {{-- Player Table --}}
                        <table class="w-full text-sm">
                            <thead class="text-left border-b">
                                <tr class="text-slate-900">
                                    <th class="font-semibold py-2 w-10"></th>
                                    <th class="font-semibold py-2 w-10"></th>
                                    <th class="font-semibold py-2">Name</th>
                                    <th class="font-semibold py-2 w-10"></th>
                                    <th class="font-semibold py-2 text-center">Age</th>
                                    <th class="font-semibold py-2 w-4"></th>
                                    <th class="font-semibold py-2 text-center w-12">TEC</th>
                                    <th class="font-semibold py-2 text-center w-12">PHY</th>
                                    <th class="font-semibold py-2 text-center w-12">FIT</th>
                                    <th class="font-semibold py-2 text-center w-12">MOR</th>
                                    <th class="font-semibold py-2 text-center w-12">OVR</th>
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
                                            <td colspan="11" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                                {{ $group['name'] }}
                                            </td>
                                        </tr>
                                        @foreach($group['players'] as $player)
                                            @php
                                                $isUnavailable = !$player->isAvailable($matchDate, $matchday);
                                                $unavailabilityReason = $player->getUnavailabilityReason($matchDate, $matchday);
                                                $positionDisplay = $player->position_display;
                                            @endphp
                                            <tr
                                                @click="toggle('{{ $player->id }}', {{ $isUnavailable ? 'true' : 'false' }})"
                                                class="border-b border-slate-100 transition-colors
                                                    @if($isUnavailable)
                                                        text-slate-400 cursor-not-allowed
                                                    @else
                                                        cursor-pointer hover:bg-slate-50
                                                    @endif"
                                                :class="{
                                                    'bg-sky-50': isSelected('{{ $player->id }}'),
                                                    'opacity-50': !isSelected('{{ $player->id }}') && selectedCount >= 11 && !{{ $isUnavailable ? 'true' : 'false' }}
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
                                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded text-xs font-bold {{ $positionDisplay['bg'] }} {{ $positionDisplay['text'] }}">
                                                        {{ $positionDisplay['abbreviation'] }}
                                                    </span>
                                                </td>
                                                {{-- Name --}}
                                                <td class="py-2">
                                                    <div class="font-medium @if($isUnavailable) text-slate-400 @else text-slate-900 @endif">
                                                        {{ $player->name }}
                                                    </div>
                                                    @if($unavailabilityReason)
                                                        <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                                    @endif
                                                </td>
                                                {{-- Nationality --}}
                                                <td class="py-2">
                                                    @if($player->nationality_flag)
                                                        <img src="/flags/{{ $player->nationality_flag['code'] }}.svg" class="w-5 h-4 rounded shadow-sm" title="{{ $player->nationality_flag['name'] }}">
                                                    @endif
                                                </td>
                                                {{-- Age --}}
                                                <td class="py-2 text-center">{{ $player->age }}</td>
                                                {{-- Separator --}}
                                                <td class="py-2">
                                                    <div class="w-px h-6 bg-slate-200 mx-auto"></div>
                                                </td>
                                                {{-- Technical --}}
                                                <td class="py-2 text-center @if($player->technical_ability >= 80) text-green-600 @elseif($player->technical_ability >= 70) text-lime-600 @elseif($player->technical_ability < 60) text-slate-400 @endif">
                                                    {{ $player->technical_ability }}
                                                </td>
                                                {{-- Physical --}}
                                                <td class="py-2 text-center @if($player->physical_ability >= 80) text-green-600 @elseif($player->physical_ability >= 70) text-lime-600 @elseif($player->physical_ability < 60) text-slate-400 @endif">
                                                    {{ $player->physical_ability }}
                                                </td>
                                                {{-- Fitness --}}
                                                <td class="py-2 text-center @if($player->fitness < 70) text-yellow-600 @elseif($player->fitness < 50) text-red-500 @endif">
                                                    {{ $player->fitness }}
                                                </td>
                                                {{-- Morale --}}
                                                <td class="py-2 text-center @if($player->morale < 60) text-red-500 @elseif($player->morale < 70) text-yellow-600 @endif">
                                                    {{ $player->morale }}
                                                </td>
                                                {{-- Overall --}}
                                                <td class="py-2 text-center">
                                                    <span class="font-bold @if($player->overall_score >= 80) text-green-600 @elseif($player->overall_score >= 70) text-lime-600 @elseif($player->overall_score >= 60) text-yellow-600 @else text-slate-500 @endif">
                                                        {{ $player->overall_score }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
