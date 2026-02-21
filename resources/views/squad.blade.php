@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8">
                    @php
                        $squadNavItems = [
                            ['href' => route('game.squad', $game->id), 'label' => __('squad.squad'), 'active' => true],
                            ['href' => route('game.squad.development', $game->id), 'label' => __('squad.development'), 'active' => false],
                            ['href' => route('game.squad.stats', $game->id), 'label' => __('squad.stats'), 'active' => false],
                        ];
                        if ($game->isCareerMode()) {
                            $squadNavItems[] = ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false];
                        }
                    @endphp
                    <x-section-nav :items="$squadNavItems" />

                    <div class="mt-6"></div>

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

                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left border-b">
                            <tr>
                                <th class="font-semibold py-2 w-10"></th>
                                <th class="font-semibold py-2 text-center w-8 text-slate-400 hidden md:table-cell">#</th>
                                <th class="font-semibold py-2">{{ __('app.name') }}</th>
                                <th class="py-2 w-6"></th>
                                <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.country') }}</th>
                                <th class="font-semibold py-2 text-center w-12 hidden md:table-cell">{{ __('app.age') }}</th>

                                @if($game->isCareerMode())
                                <th class="font-semibold py-2 pl-3 pr-4 text-right border-l border-slate-200 w-24 hidden md:table-cell">{{ __('app.value') }}</th>
                                <th class="font-semibold py-2 pr-4 text-right w-24 hidden md:table-cell">{{ __('app.wage') }}</th>
                                <th class="font-semibold py-2 text-center w-20 hidden md:table-cell">{{ __('app.contract') }}</th>
                                @endif

                                <th class="font-semibold py-2 pl-3 text-center w-10 hidden md:table-cell">{{ __('squad.technical') }}</th>
                                <th class="font-semibold py-2 text-center w-10 hidden md:table-cell">{{ __('squad.physical') }}</th>
                                <th class="font-semibold py-2 text-center w-10 hidden md:table-cell">{{ __('squad.fitness') }}</th>
                                <th class="font-semibold py-2 text-center w-10 hidden md:table-cell">{{ __('squad.morale') }}</th>
                                <th class="font-semibold py-2 text-center w-10">{{ __('squad.overall') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([
                                ['name' => __('squad.goalkeepers'), 'players' => $goalkeepers],
                                ['name' => __('squad.defenders'), 'players' => $defenders],
                                ['name' => __('squad.midfielders'), 'players' => $midfielders],
                                ['name' => __('squad.forwards'), 'players' => $forwards],
                            ] as $group)
                                @if($group['players']->isNotEmpty())
                                    <tr class="bg-slate-200">
                                        <td colspan="16" class="py-2 px-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                            {{ $group['name'] }}
                                        </td>
                                    </tr>
                                    @foreach($group['players'] as $gamePlayer)
                                        @php
                                            $nextMatchday = $game->current_matchday + 1;
                                            $isUnavailable = !$gamePlayer->isAvailable($game->current_date, $nextMatchday);
                                            $unavailabilityReason = $gamePlayer->getUnavailabilityReason($game->current_date, $nextMatchday);
                                        @endphp
                                        <tr class="border-b border-slate-200 hover:bg-slate-50">
                                            {{-- Position --}}
                                            <td class="py-2 text-center">
                                                <x-position-badge :position="$gamePlayer->position" :tooltip="\App\Support\PositionMapper::toDisplayName($gamePlayer->position)" class="cursor-help" />
                                            </td>
                                            {{-- Number --}}
                                            <td class="py-2 text-center text-slate-400 text-xs hidden md:table-cell">{{ $gamePlayer->number ?? '-' }}</td>
                                            {{-- Name --}}
                                            <td class="py-2">
                                                <div class="flex items-center space-x-2">
                                                    <button x-data @click="$dispatch('show-player-detail', '{{ route('game.player.detail', [$game->id, $gamePlayer->id]) }}')" class="p-1.5 text-slate-300  rounded hover:text-slate-400 ">
                                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="w-5 h-5">
                                                            <path fill-rule="evenodd" d="M19.5 21a3 3 0 0 0 3-3V9a3 3 0 0 0-3-3h-5.379a.75.75 0 0 1-.53-.22L11.47 3.66A2.25 2.25 0 0 0 9.879 3H4.5a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3h15Zm-6.75-10.5a.75.75 0 0 0-1.5 0v2.25H9a.75.75 0 0 0 0 1.5h2.25v2.25a.75.75 0 0 0 1.5 0v-2.25H15a.75.75 0 0 0 0-1.5h-2.25V10.5Z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                    <div>
                                                    <div class="font-medium text-slate-900 @if($isUnavailable) text-slate-400 @endif">
                                                        {{ $gamePlayer->player->name }}
                                                    </div>
                                                    @if($unavailabilityReason)
                                                        <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                                    @endif
                                                    </div>
                                                </div>
                                            </td>
                                            {{-- Status icon (career mode only) --}}
                                            @if($game->isCareerMode())
                                            <td class="py-2 text-center">
                                            @if($gamePlayer->isRetiring())
                                                    {{-- Retiring: person walking away --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.retiring') }}" class="w-4 h-4 text-orange-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                    </svg>
                                                @elseif($gamePlayer->isLoanedIn($game->team_id))
                                                    {{-- On loan: rotate arrows --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.on_loan') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                                                    </svg>
                                                @elseif($gamePlayer->hasPreContractAgreement())
                                                    {{-- Leaving free: door open --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.leaving_free') }}" class="w-4 h-4 text-red-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                    </svg>
                                                @elseif($gamePlayer->hasRenewalAgreed())
                                                    {{-- Renewed: file with check --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.renewed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25h-4.5c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-9M10.125 2.25h.375a9 9 0 0 1 9 9v.375M10.125 2.25A3.375 3.375 0 0 1 13.5 5.625v1.5c0 .621.504 1.125 1.125 1.125h1.5a3.375 3.375 0 0 1 3.375 3.375M9 15l2.25 2.25L15 12" />
                                                    </svg>
                                                @elseif($gamePlayer->hasAgreedTransfer())
                                                    {{-- Sale agreed: handshake --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.sale_agreed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15m-3 0-3-3m0 0 3-3m-3 3H15" />
                                                    </svg>
                                                @elseif($gamePlayer->hasActiveLoanSearch())
                                                    {{-- Loan search: magnifying glass --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.loan_searching') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                    </svg>
                                                @elseif($gamePlayer->isTransferListed())
                                                    {{-- Listed for sale: money speech bubble --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.listed') }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" class="w-4 h-4 text-amber-500 mx-auto cursor-help" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M3.396 6.093a2 2 0 0 0 0 3.814 2 2 0 0 0 2.697 2.697 2 2 0 0 0 3.814 0 2.001 2.001 0 0 0 2.698-2.697 2 2 0 0 0-.001-3.814 2.001 2.001 0 0 0-2.697-2.698 2 2 0 0 0-3.814.001 2 2 0 0 0-2.697 2.697ZM6 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm3.47-1.53a.75.75 0 1 1 1.06 1.06l-4 4a.75.75 0 1 1-1.06-1.06l4-4ZM11 10a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" clip-rule="evenodd" />
                                                    </svg>
                                                @endif
                                            </td>
                                            @else
                                            <td class="py-2"></td>
                                            @endif
                                            {{-- Nationality --}}
                                            <td class="py-2 text-center hidden md:table-cell">
                                                @if($gamePlayer->nationality_flag)
                                                    <img src="/flags/{{ $gamePlayer->nationality_flag['code'] }}.svg" class="w-5 h-4 mx-auto rounded shadow-sm" title="{{ $gamePlayer->nationality_flag['name'] }}">
                                                @endif
                                            </td>
                                            {{-- Age --}}
                                            <td class="py-2 text-center hidden md:table-cell">{{ $gamePlayer->player->age }}</td>

                                            @if($game->isCareerMode())
                                            {{-- Market Value --}}
                                            <td class="border-l border-slate-200 py-2 pl-3 pr-4 text-right tabular-nums text-slate-600 hidden md:table-cell">{{ $gamePlayer->formatted_market_value }}</td>
                                            {{-- Annual Wage --}}
                                            <td class="py-2 pr-4 text-right tabular-nums text-slate-600 hidden md:table-cell">{{ $gamePlayer->formatted_wage }}</td>
                                            {{-- Contract --}}
                                            <td class="py-2 text-center text-slate-600 hidden md:table-cell">
                                                @if($gamePlayer->contract_until)
                                                    @if($gamePlayer->isContractExpiring($seasonEndDate))
                                                        <span class="text-red-600 font-medium" title="Contract expiring">
                                                            {{ $gamePlayer->contract_expiry_year }}
                                                        </span>
                                                    @else
                                                        {{ $gamePlayer->contract_expiry_year }}
                                                    @endif
                                                @endif
                                            </td>
                                            @endif

                                            {{-- Technical --}}
                                            <td class="border-l border-slate-200 py-2 pl-3 text-center hidden md:table-cell">
                                                <x-ability-bar :value="$gamePlayer->technical_ability" size="sm" class="text-xs font-medium justify-center @if($gamePlayer->technical_ability >= 80) text-green-600 @elseif($gamePlayer->technical_ability >= 70) text-lime-600 @elseif($gamePlayer->technical_ability < 60) text-slate-400 @endif" />
                                            </td>
                                            {{-- Physical --}}
                                            <td class="py-2 text-center hidden md:table-cell">
                                                <x-ability-bar :value="$gamePlayer->physical_ability" size="sm" class="text-xs font-medium justify-center @if($gamePlayer->physical_ability >= 80) text-green-600 @elseif($gamePlayer->physical_ability >= 70) text-lime-600 @elseif($gamePlayer->physical_ability < 60) text-slate-400 @endif" />
                                            </td>
                                            {{-- Fitness --}}
                                            <td class="py-2 text-center hidden md:table-cell">
                                                <span class="@if($gamePlayer->fitness >= 90) text-green-600 @elseif($gamePlayer->fitness >= 80) text-lime-600 @elseif($gamePlayer->fitness < 50) text-red-500 font-medium @elseif($gamePlayer->fitness < 70) text-yellow-600 @endif">
                                                    {{ $gamePlayer->fitness }}
                                                </span>
                                            </td>
                                            {{-- Morale --}}
                                            <td class="py-2 text-center hidden md:table-cell">
                                                <span class="@if($gamePlayer->morale >= 85) text-green-600 @elseif($gamePlayer->morale >= 75) text-lime-600 @elseif($gamePlayer->morale < 50) text-red-500 font-medium @elseif($gamePlayer->morale < 65) text-yellow-600 @endif">
                                                    {{ $gamePlayer->morale }}
                                                </span>
                                            </td>
                                            {{-- Overall --}}
                                            <td class="py-2 text-center">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold
                                                    @if($gamePlayer->overall_score >= 80) bg-emerald-500 text-white
                                                    @elseif($gamePlayer->overall_score >= 70) bg-lime-500 text-white
                                                    @elseif($gamePlayer->overall_score >= 60) bg-amber-500 text-white
                                                    @else bg-slate-300 text-slate-700
                                                    @endif">
                                                    {{ $gamePlayer->overall_score }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                    </div>

                    {{-- Squad summary --}}
                    @php
                        $allPlayers = $goalkeepers->concat($defenders)->concat($midfielders)->concat($forwards);
                        $avgFitness = $allPlayers->avg('fitness');
                        $avgMorale = $allPlayers->avg('morale');
                        $lowFitnessCount = $allPlayers->filter(fn($p) => $p->fitness < 70)->count();
                        $lowMoraleCount = $allPlayers->filter(fn($p) => $p->morale < 65)->count();
                    @endphp
                    <div class="pt-6 border-t">
                        <div class="flex flex-wrap gap-8 text-sm text-slate-600">
                            <div>
                                <span class="font-semibold text-slate-900">{{ $allPlayers->count() }}</span>
                                <span class="text-slate-400 ml-1">{{ __('app.players') }}</span>
                            </div>
                            @if($game->isCareerMode())
                            @php $formattedWageBill = \App\Support\Money::format($allPlayers->sum('annual_wage')); @endphp
                            <div>
                                <span class="text-slate-400">{{ __('squad.wage_bill') }}:</span>
                                <span class="font-semibold text-slate-900">{{ $formattedWageBill }}{{ __('squad.per_year') }}</span>
                            </div>
                            @endif
                            <div class="flex items-center gap-1">
                                <x-position-badge group="Goalkeeper" size="sm" />
                                <span class="font-medium">{{ $goalkeepers->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-position-badge group="Defender" size="sm" />
                                <span class="font-medium">{{ $defenders->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-position-badge group="Midfielder" size="sm" />
                                <span class="font-medium">{{ $midfielders->count() }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <x-position-badge group="Forward" size="sm" />
                                <span class="font-medium">{{ $forwards->count() }}</span>
                            </div>
                            <div class="border-l pl-8 flex items-center gap-1">
                                <span class="text-slate-400">{{ __('squad.avg_fitness') }}:</span>
                                <span class="font-semibold @if($avgFitness >= 85) text-green-600 @elseif($avgFitness < 70) text-yellow-600 @else text-slate-900 @endif">{{ round($avgFitness) }}</span>
                                @if($lowFitnessCount > 0)
                                    <span class="text-xs text-yellow-600">({{ $lowFitnessCount }} {{ __('squad.low') }})</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="text-slate-400">{{ __('squad.avg_morale') }}:</span>
                                <span class="font-semibold @if($avgMorale >= 80) text-green-600 @elseif($avgMorale < 65) text-yellow-600 @else text-slate-900 @endif">{{ round($avgMorale) }}</span>
                                @if($lowMoraleCount > 0)
                                    <span class="text-xs text-yellow-600">({{ $lowMoraleCount }} {{ __('squad.low') }})</span>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <x-player-detail-modal />
</x-app-layout>
