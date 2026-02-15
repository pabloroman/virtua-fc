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
                            $squadNavItems[] = ['href' => route('game.squad.academy', $game->id), 'label' => __('squad.academy'), 'active' => false, 'badge' => $academyCount > 0 ? $academyCount : null];
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
                                <th class="font-semibold py-2 text-right w-8"></th>
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
                                        <tr class="border-b border-slate-200 @if($isUnavailable) text-slate-400 @endif hover:bg-slate-50">
                                            {{-- Position --}}
                                            <td class="py-2 text-center">
                                                <x-position-badge :position="$gamePlayer->position" :tooltip="\App\Support\PositionMapper::toDisplayName($gamePlayer->position)" class="cursor-help" />
                                            </td>
                                            {{-- Number --}}
                                            <td class="py-2 text-center text-slate-400 text-xs hidden md:table-cell">{{ $gamePlayer->number ?? '-' }}</td>
                                            {{-- Name --}}
                                            <td class="py-2">
                                                <div class="font-medium text-slate-900 @if($isUnavailable) text-slate-400 @endif">
                                                    {{ $gamePlayer->player->name }}
                                                </div>
                                                @if($unavailabilityReason)
                                                    <div class="text-xs text-red-500">{{ $unavailabilityReason }}</div>
                                                @endif
                                            </td>
                                            {{-- Status icon (career mode only) --}}
                                            @if($game->isCareerMode())
                                            <td class="py-2 text-center">
                                                @if($gamePlayer->isRetiring())
                                                    {{-- Retiring: person walking away --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.retiring') }}" class="w-4 h-4 text-orange-500 mx-auto cursor-help" fill="currentColor" viewBox="0 0 512 512">
                                                        <path d="M377 52c0 28.7-23.3 52-52 52s-52-23.3-52-52 23.3-52 52-52 52 23.3 52 52zm-131.2 98.4l-40.6 20.3c-13 6.5-27.2 9.3-41.4 8.3l-17-1.2c-17.6-1.2-33.2 10.3-36.5 27.7l-1.3 6.6c-3.5 17.7 7.9 34.9 25.6 38.4 2.1.4 4.3.6 6.4.6 15.5 0 29.1-10.8 32.4-26l.2-.8 13.1.9c-5 23.1-1 47.5 13.6 67.5l33.2 45.5-43.5 93.5c-7.7 16.5-.5 36.1 16 43.8 4.5 2.1 9.2 3.1 13.9 3.1 12.4 0 24.3-7 29.9-19.1l49.7-106.8c3-6.5 4.3-13.7 3.5-20.8l-5-47 19.8-34.6 28 63.3c1.1 2.4 2.4 4.7 4 6.8l57.6 76.8c10.6 14.1 30.6 17 44.8 6.4 14.1-10.6 17-30.6 6.4-44.8l-54.4-72.5-43.2-97.4c-8.8-19.8-27.6-33.1-49.2-34.5l-7.2-.5 47.6-23.8c15.8-7.9 22.2-27.1 14.3-42.9s-27.1-22.2-42.9-14.3z"/>
                                                    </svg>
                                                @elseif($gamePlayer->isLoanedIn($game->team_id))
                                                    {{-- On loan: rotate arrows --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.on_loan') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help" fill="currentColor" viewBox="0 0 512 512">
                                                        <path d="M105.1 202.6c7.7-21.8 20.2-42.3 37.8-59.8c62.5-62.5 163.8-62.5 226.3 0L386.3 160 352 160c-17.7 0-32 14.3-32 32s14.3 32 32 32l111.5 0c0 0 0 0 0 0l.4 0c17.7 0 32-14.3 32-32l0-112c0-17.7-14.3-32-32-32s-32 14.3-32 32l0 35.2L414.4 97.6c-87.5-87.5-229.3-87.5-316.8 0C73.2 122 55.6 150.7 44.8 181.4c-5.9 16.7 2.9 34.9 19.5 40.8s34.9-2.9 40.8-19.5zM39 289.3c-5 1.5-9.8 4.2-13.7 8.2c-4 4-6.7 8.8-8.1 14c-.3 1.2-.6 2.5-.8 3.8c-.3 1.7-.4 3.4-.4 5.1L16 432c0 17.7 14.3 32 32 32s32-14.3 32-32l0-35.1 17.6 17.5c0 0 0 0 0 0c87.5 87.4 229.3 87.4 316.7 0c24.4-24.4 42.1-53.1 52.9-83.7c5.9-16.7-2.9-34.9-19.5-40.8s-34.9 2.9-40.8 19.5c-7.7 21.8-20.2 42.3-37.8 59.8c-62.5 62.5-163.8 62.5-226.3 0l-17.5-17.5L160 352c17.7 0 32-14.3 32-32s-14.3-32-32-32L48.4 288c-3.2 0-6.4 .4-9.4 1.3z"/>
                                                    </svg>
                                                @elseif($gamePlayer->hasPreContractAgreement())
                                                    {{-- Leaving free: door open --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.leaving_free') }}" class="w-4 h-4 text-red-500 mx-auto cursor-help" fill="currentColor" viewBox="0 0 576 512">
                                                        <path d="M320 32c0-9.9-4.5-19.2-12.3-25.2S291.8-1.4 282.1 .8L114.1 40.8C100.3 44.3 90 56.7 90 71l0 361 0 16 0 32c0 17.7 14.3 32 32 32l64 0c17.7 0 32-14.3 32-32l0-64 192 0c17.7 0 32-14.3 32-32l0-16 0-296c0-17.7-14.3-32-32-32l-90 0 0-32zM240 304c0 13.3-10.7 24-24 24s-24-10.7-24-24s10.7-24 24-24s24 10.7 24 24zm256-80l0 208c0 8.8-7.2 16-16 16l-48 0 0 64 48 0c44.2 0 80-35.8 80-80l0-208c0-44.2-35.8-80-80-80l-48 0 0 64 48 0c8.8 0 16 7.2 16 16z"/>
                                                    </svg>
                                                @elseif($gamePlayer->hasRenewalAgreed())
                                                    {{-- Renewed: file with check --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.renewed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="currentColor" viewBox="0 0 384 512">
                                                        <path d="M64 0C28.7 0 0 28.7 0 64L0 448c0 35.3 28.7 64 64 64l256 0c35.3 0 64-28.7 64-64l0-288-128 0c-17.7 0-32-14.3-32-32L224 0 64 0zM256 0l0 128 128 0L256 0zM168 375c-9.4 9.4-24.6 9.4-33.9 0l-48-48c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l31 31 79-79c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-96 96z"/>
                                                    </svg>
                                                @elseif($gamePlayer->hasAgreedTransfer())
                                                    {{-- Sale agreed: handshake --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.sale_agreed') }}" class="w-4 h-4 text-green-500 mx-auto cursor-help" fill="currentColor" viewBox="0 0 640 512">
                                                        <path d="M323.4 85.2l-96.8 78.4c-16.1 13-19.2 36.4-7 53.1c12.9 17.8 38 21.3 55.3 7.8l99.3-77.2c7-5.4 17-4.2 22.5 2.8s4.2 17-2.8 22.5l-20.9 16.2L550.2 352l41.8 0c26.5 0 48-21.5 48-48l0-128c0-26.5-21.5-48-48-48l-76 0-4 0-.2 0-46.6-37.7c-11.9-9.6-26.7-14.8-42-14.3c-15.3 .5-29.8 6.7-40.8 17.4l-2 2-16.2-12.7c-21-16.5-51.7-16.1-72.3 1zM64 192l0 128c0 26.5 21.5 48 48 48l89.8 0L64 192zm288 128l-.3 0-117 89.4c-16.1 12.3-37.7 15.2-56.6 7.5l-14.5-5.9c-18-7.3-34.8-17.4-49.8-30L48.1 319.9C17.3 292.7 0 254.5 0 214.4l0-22.4c0-26.5 21.5-48 48-48l108.4 0 47.2-38.2c21-17 48.1-26.1 75.5-27.1c27.5-1 54.5 5.9 76.8 21.5l3.6 2.5 7.4-6.2c34.7-29.1 85.7-27.1 118.3 4.7l1.1 1.1 20.7 0c44.2 0 80 35.8 80 80l0 128c0 44.2-35.8 80-80 80l-48 0-40 0-40 0-88 0zm-18.5 55.6l-8.6 6.6c-28.6 21.8-68 24.5-99.6 6.4l-7.1-4.1-3.4 1.4c-24 9.8-50.8 7-72.4-5.5l49.2 42.4c20 17.2 46.5 25 72.8 21.5l37.1-5c13.8-1.9 27.6 2.4 38.4 11.7l32.4 27.9c2.8 2.4 6.4 3.7 10.1 3.7c8.5 0 15.3-6.9 15.3-15.3l0-11.5c0-11.5 5.7-22.3 15.2-28.7l17-11.5c3.3-2.2 5.3-5.9 5.3-9.9c0-6.5-5.3-11.7-11.7-11.7l-44.5 0c-8.8 0-17.4-2.4-24.9-7l-24.4-14.8z"/>
                                                    </svg>
                                                @elseif($gamePlayer->hasActiveLoanSearch())
                                                    {{-- Loan search: magnifying glass --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.loan_searching') }}" class="w-4 h-4 text-sky-500 mx-auto cursor-help animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                    </svg>
                                                @elseif($gamePlayer->isTransferListed())
                                                    {{-- Listed for sale: money speech bubble --}}
                                                    <svg x-data="" x-tooltip.raw="{{ __('squad.listed') }}" class="w-4 h-4 text-amber-500 mx-auto cursor-help" fill="currentColor" viewBox="0 0 640 640">
                                                        <path d="M320 544C461.4 544 576 436.5 576 304C576 171.5 461.4 64 320 64C178.6 64 64 171.5 64 304C64 358.3 83.2 408.3 115.6 448.5L66.8 540.8C62 549.8 63.5 560.8 70.4 568.3C77.3 575.8 88.2 578.1 97.5 574.1L215.9 523.4C247.7 536.6 282.9 544 320 544zM324 192C335 192 344 201 344 212L344 216L352 216C363 216 372 225 372 236C372 247 363 256 352 256L304.5 256C297.6 256 292 261.6 292 268.5C292 274.6 296.4 279.8 302.4 280.8L344.1 287.8C369.4 292 388 313.9 388 339.6C388 365.7 369 387.3 344 391.4L344 396.1C344 407.1 335 416.1 324 416.1C313 416.1 304 407.1 304 396.1L304 392.1L280 392.1C269 392.1 260 383.1 260 372.1C260 361.1 269 352.1 280 352.1L335.5 352.1C342.4 352.1 348 346.5 348 339.6C348 333.5 343.6 328.3 337.6 327.3L295.9 320.3C270.6 316.1 252 294.2 252 268.5C252 239.7 275.2 216.3 304 216L304 212C304 201 313 192 324 192z"/>
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
                                                    @if($gamePlayer->isContractExpiring())
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
                                            {{-- Actions (career mode only) --}}
                                            <td class="py-2 text-right">
                                                @if($game->isCareerMode() && $gamePlayer->isTransferListed())
                                                    <div x-data="{ open: false }" @click.outside="open = false" class="relative inline-block">
                                                        <button @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100">
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                                                        </button>
                                                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                                             class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 py-1" style="display: none;">
                                                            <form method="post" action="{{ route('game.transfers.unlist', [$game->id, $gamePlayer->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-slate-100">
                                                                    {{ __('squad.unlist_from_sale') }}
                                                                </button>
                                                            </form>
                                                            @if($isTransferWindow)
                                                            <form method="post" action="{{ route('game.loans.out', [$game->id, $gamePlayer->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-amber-600 hover:bg-slate-100">
                                                                    {{ __('squad.loan_out') }}
                                                                </button>
                                                            </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @elseif($game->isCareerMode() && !$gamePlayer->isRetiring() && !$gamePlayer->isLoanedIn($game->team_id) && !$gamePlayer->hasPreContractAgreement() && !$gamePlayer->hasRenewalAgreed() && !$gamePlayer->hasAgreedTransfer() && !$gamePlayer->hasActiveLoanSearch())
                                                    <div x-data="{ open: false }" @click.outside="open = false" class="relative inline-block">
                                                        <button @click="open = !open" class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100">
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="4" r="1.5"/><circle cx="10" cy="10" r="1.5"/><circle cx="10" cy="16" r="1.5"/></svg>
                                                        </button>
                                                        <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                                                             class="absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50 py-1" style="display: none;">
                                                            <form method="post" action="{{ route('game.transfers.list', [$game->id, $gamePlayer->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-sky-600 hover:bg-slate-100">
                                                                    {{ __('squad.list_for_sale') }}
                                                                </button>
                                                            </form>
                                                            @if($isTransferWindow)
                                                            <form method="post" action="{{ route('game.loans.out', [$game->id, $gamePlayer->id]) }}">
                                                                @csrf
                                                                <button type="submit" class="w-full text-left px-4 py-2 text-sm text-amber-600 hover:bg-slate-100">
                                                                    {{ __('squad.loan_out') }}
                                                                </button>
                                                            </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
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
</x-app-layout>
