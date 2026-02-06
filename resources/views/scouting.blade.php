@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
                {{ session('success') }}
            </div>
            @endif
            @if(session('error'))
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700">
                {{ session('error') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="font-semibold text-xl text-slate-900">Transfers</h3>
                        <div class="flex items-center gap-6 text-sm">
                            <div class="text-slate-600">
                                @if($isTransferWindow)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                        {{ $currentWindow }} Window Open
                                    </span>
                                @else
                                    Window: <span class="font-semibold text-slate-900">Closed</span>
                                @endif
                            </div>
                            @if($game->finances)
                            <div class="text-slate-600">
                                Budget: <span class="font-semibold text-slate-900">{{ $game->finances->formatted_transfer_budget }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Tab Navigation --}}
                    <x-transfers-nav :game="$game" active="scouting" />

                    {{-- State: No active search → Show search form --}}
                    @if(!$report)
                        <div class="mt-6">
                            <h4 class="font-semibold text-lg text-slate-900 mb-4">Scout Search</h4>
                            <p class="text-sm text-slate-600 mb-6">Send your scout to find available players matching your criteria. One search at a time.</p>

                            <form method="post" action="{{ route('game.scouting.search', $game->id) }}" class="max-w-xl space-y-4">
                                @csrf

                                {{-- Position --}}
                                <div>
                                    <label for="position" class="block text-sm font-medium text-slate-700 mb-1">Position <span class="text-red-500">*</span></label>
                                    <select name="position" id="position" required class="w-full border-slate-300 rounded-lg shadow-sm text-sm focus:ring-sky-500 focus:border-sky-500">
                                        <option value="">Select position...</option>
                                        <optgroup label="Specific Positions">
                                            <option value="GK">Goalkeeper (GK)</option>
                                            <option value="CB">Centre-Back (CB)</option>
                                            <option value="LB">Left-Back (LB)</option>
                                            <option value="RB">Right-Back (RB)</option>
                                            <option value="DM">Defensive Midfield (DM)</option>
                                            <option value="CM">Central Midfield (CM)</option>
                                            <option value="AM">Attacking Midfield (AM)</option>
                                            <option value="LW">Left Winger (LW)</option>
                                            <option value="RW">Right Winger (RW)</option>
                                            <option value="CF">Centre-Forward (CF)</option>
                                        </optgroup>
                                        <optgroup label="Position Groups (broader search)">
                                            <option value="any_defender">Any Defender (CB, LB, RB)</option>
                                            <option value="any_midfielder">Any Midfielder (DM, CM, AM)</option>
                                            <option value="any_forward">Any Forward (LW, RW, CF)</option>
                                        </optgroup>
                                    </select>
                                    @error('position')
                                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- League --}}
                                <div>
                                    <label for="league" class="block text-sm font-medium text-slate-700 mb-1">League</label>
                                    <select name="league" id="league" class="w-full border-slate-300 rounded-lg shadow-sm text-sm focus:ring-sky-500 focus:border-sky-500">
                                        <option value="all">All leagues</option>
                                        @foreach($leagues as $league)
                                            <option value="{{ $league->id }}">{{ $league->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Age Range --}}
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="age_min" class="block text-sm font-medium text-slate-700 mb-1">Min Age</label>
                                        <input type="number" name="age_min" id="age_min" min="16" max="45" placeholder="e.g. 18" class="w-full border-slate-300 rounded-lg shadow-sm text-sm focus:ring-sky-500 focus:border-sky-500">
                                    </div>
                                    <div>
                                        <label for="age_max" class="block text-sm font-medium text-slate-700 mb-1">Max Age</label>
                                        <input type="number" name="age_max" id="age_max" min="16" max="45" placeholder="e.g. 28" class="w-full border-slate-300 rounded-lg shadow-sm text-sm focus:ring-sky-500 focus:border-sky-500">
                                    </div>
                                </div>

                                {{-- Max Budget --}}
                                <div>
                                    <label for="max_budget" class="block text-sm font-medium text-slate-700 mb-1">Max Transfer Fee (euros)</label>
                                    <input type="number" name="max_budget" id="max_budget" min="0" step="100000" placeholder="e.g. 20000000" class="w-full border-slate-300 rounded-lg shadow-sm text-sm focus:ring-sky-500 focus:border-sky-500">
                                    <p class="text-xs text-slate-500 mt-1">Leave empty for no limit</p>
                                </div>

                                <div class="pt-2">
                                    <button type="submit" class="px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold rounded-lg transition-colors">
                                        Start Scout Search
                                    </button>
                                </div>
                            </form>
                        </div>

                    {{-- State: Search in progress --}}
                    @elseif($report->isSearching())
                        <div class="mt-6">
                            <div class="text-center py-12 border rounded-lg bg-slate-50">
                                <svg class="w-16 h-16 mx-auto mb-4 text-sky-500 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <h4 class="text-lg font-semibold text-slate-900 mb-2">Scout is searching...</h4>
                                <p class="text-slate-600 mb-1">
                                    {{ $report->weeks_remaining }} {{ Str::plural('week', $report->weeks_remaining) }} remaining
                                </p>
                                <p class="text-sm text-slate-500 mb-6">
                                    Looking for: <span class="font-medium">{{ $report->filters['position'] }}</span>
                                    @if($report->filters['league'] !== 'all')
                                        in <span class="font-medium">{{ $report->filters['league'] }}</span>
                                    @endif
                                </p>
                                <div class="w-48 mx-auto bg-slate-200 rounded-full h-2 mb-6">
                                    @php $progress = (($report->weeks_total - $report->weeks_remaining) / $report->weeks_total) * 100; @endphp
                                    <div class="bg-sky-500 h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                                </div>
                                <form method="post" action="{{ route('game.scouting.cancel', $game->id) }}">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 rounded-lg transition-colors">
                                        Cancel Search
                                    </button>
                                </form>
                            </div>
                        </div>

                    {{-- State: Results ready --}}
                    @elseif($report->isCompleted())
                        <div class="mt-6">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-semibold text-lg text-slate-900">Scout Results</h4>
                                <form method="post" action="{{ route('game.scouting.cancel', $game->id) }}">
                                    @csrf
                                    <button type="submit" class="text-sm text-sky-600 hover:text-sky-800">
                                        New Search
                                    </button>
                                </form>
                            </div>

                            @if($scoutedPlayers->isEmpty())
                                <div class="text-center py-8 text-slate-500 border rounded-lg bg-slate-50">
                                    <p>No players found matching your criteria.</p>
                                    <p class="text-sm mt-1">Try broadening your search.</p>
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead class="text-left border-b border-slate-200">
                                            <tr>
                                                <th class="font-medium text-slate-500 pb-2">Player</th>
                                                <th class="font-medium text-slate-500 pb-2">Pos</th>
                                                <th class="font-medium text-slate-500 pb-2 text-center">Age</th>
                                                <th class="font-medium text-slate-500 pb-2">Team</th>
                                                <th class="font-medium text-slate-500 pb-2 text-right">Value</th>
                                                <th class="font-medium text-slate-500 pb-2 text-center">Contract</th>
                                                <th class="font-medium text-slate-500 pb-2 text-center">Ability</th>
                                                <th class="font-medium text-slate-500 pb-2 text-right"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($scoutedPlayers as $player)
                                                @php
                                                    $fuzz = rand(3, 7);
                                                    $avgAbility = (int)(($player->current_technical_ability + $player->current_physical_ability) / 2);
                                                    $abilityLow = max(1, $avgAbility - $fuzz);
                                                    $abilityHigh = min(99, $avgAbility + $fuzz);
                                                    $positionDisplay = $player->position_display;
                                                @endphp
                                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                                    <td class="py-3">
                                                        <div class="font-medium text-slate-900">{{ $player->name }}</div>
                                                        @if($player->nationality_flag)
                                                            <div class="flex items-center gap-1 mt-0.5">
                                                                <img src="/flags/{{ $player->nationality_flag['code'] }}.svg" class="w-4 h-3 rounded shadow-sm">
                                                                <span class="text-xs text-slate-500">{{ $player->nationality_flag['name'] }}</span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    <td class="py-3">
                                                        <span class="px-1.5 py-0.5 text-xs font-medium rounded {{ $positionDisplay['bg'] }} {{ $positionDisplay['text'] }}">
                                                            {{ $positionDisplay['abbreviation'] }}
                                                        </span>
                                                    </td>
                                                    <td class="py-3 text-center text-slate-600">{{ $player->age }}</td>
                                                    <td class="py-3">
                                                        <div class="flex items-center gap-2">
                                                            <img src="{{ $player->team->image }}" class="w-5 h-5">
                                                            <span class="text-slate-600">{{ $player->team->name }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="py-3 text-right text-slate-900">{{ $player->formatted_market_value }}</td>
                                                    <td class="py-3 text-center text-slate-600">{{ $player->contract_expiry_year ?? '-' }}</td>
                                                    <td class="py-3 text-center">
                                                        <span class="text-slate-700 font-medium">{{ $abilityLow }}-{{ $abilityHigh }}</span>
                                                    </td>
                                                    <td class="py-3 text-right">
                                                        <a href="{{ route('game.scouting.player', [$game->id, $player->id]) }}"
                                                           class="px-3 py-1.5 text-xs font-semibold text-sky-600 hover:text-sky-800 hover:bg-sky-50 rounded-lg transition-colors">
                                                            View Report
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
