@php
/** @var App\Models\Game $game */
/** @var array $leagueTeamActivity */
/** @var int $leagueTransferCount */
/** @var array $restOfWorldTransfers */
/** @var string $competitionName */
/** @var \Illuminate\Support\Collection $teams */
/** @var string $window */
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 md:p-8">

                    {{-- Header --}}
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-slate-900">
                                {{ __('transfers.transfer_activity_title', ['window' => __('transfers.transfer_activity_' . $window)]) }}
                            </h2>
                            <p class="text-sm text-slate-500 mt-1">
                                {{ __('notifications.ai_transfer_message', ['count' => $leagueTransferCount + count($restOfWorldTransfers)]) }}
                            </p>
                        </div>
                        <a href="{{ route('show-game', $game->id) }}"
                           class="inline-flex items-center gap-1.5 text-sm text-slate-600 hover:text-slate-900 min-h-[44px]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            {{ __('app.back') }}
                        </a>
                    </div>

                    {{-- League Section — Team-Grouped --}}
                    <div class="mb-8">
                        <h3 class="text-base font-semibold text-slate-800 mb-4">{{ $competitionName }}</h3>

                        @if(count($leagueTeamActivity) > 0)
                            <div class="columns-1 md:columns-2 gap-6">
                                @foreach($leagueTeamActivity as $teamId => $activity)
                                    <div class="break-inside-avoid pb-3 mb-3 border-b border-slate-100 last:border-b-0 last:mb-0 last:pb-0">
                                        {{-- Team header --}}
                                        <div class="flex items-center gap-2 mb-2">
                                            @if($teams->has($teamId))
                                                <x-team-crest :team="$teams->get($teamId)" class="w-6 h-6 shrink-0" />
                                            @endif
                                            <span class="font-semibold text-sm text-slate-900">{{ $activity['teamName'] }}</span>
                                        </div>

                                        {{-- Transfer rows --}}
                                        <div class="space-y-1 pl-1 md:pl-8">
                                            {{-- OUT transfers --}}
                                            @foreach($activity['out'] as $transfer)
                                                <div class="flex items-center gap-1.5 md:gap-2 text-sm min-h-[28px]">
                                                    <span class="text-red-500 font-bold w-4 shrink-0 text-center" title="{{ __('transfers.transfer_activity_out') }}">&#x2197;</span>
                                                    <x-position-badge :position="$transfer['position']" size="sm" />
                                                    <span class="text-slate-800 truncate min-w-0">{{ $transfer['playerName'] }}</span>
                                                    <span class="text-slate-400 shrink-0">&rarr;</span>
                                                    <span class="flex items-center gap-1 truncate min-w-0 {{ ($transfer['type'] ?? '') === 'foreign' ? 'text-amber-600 italic' : 'text-slate-500' }}">
                                                        @if(isset($transfer['toTeamId']) && $teams->has($transfer['toTeamId']))
                                                            <x-team-crest :team="$teams->get($transfer['toTeamId'])" class="w-4 h-4 shrink-0" />
                                                        @endif
                                                        <span class="truncate">{{ $transfer['toTeamName'] ?? __('transfers.transfer_activity_foreign') }}</span>
                                                    </span>
                                                    <span class="ml-auto text-slate-600 whitespace-nowrap text-xs font-medium">{{ $transfer['formattedFee'] }}</span>
                                                </div>
                                            @endforeach

                                            {{-- IN transfers --}}
                                            @foreach($activity['in'] as $transfer)
                                                <div class="flex items-center gap-1.5 md:gap-2 text-sm min-h-[28px]">
                                                    <span class="text-emerald-500 font-bold w-4 shrink-0 text-center" title="{{ __('transfers.transfer_activity_in') }}">&#x2199;</span>
                                                    <x-position-badge :position="$transfer['position']" size="sm" />
                                                    <span class="text-slate-800 truncate min-w-0">{{ $transfer['playerName'] }}</span>
                                                    @if($transfer['fromTeamId'])
                                                        <span class="text-slate-400 shrink-0">&larr;</span>
                                                        <span class="flex items-center gap-1 truncate min-w-0 text-slate-500">
                                                            @if($teams->has($transfer['fromTeamId']))
                                                                <x-team-crest :team="$teams->get($transfer['fromTeamId'])" class="w-4 h-4 shrink-0" />
                                                            @endif
                                                            <span class="truncate">{{ $transfer['fromTeamName'] }}</span>
                                                        </span>
                                                    @endif
                                                    <span class="ml-auto whitespace-nowrap text-xs font-medium {{ $transfer['type'] === 'free_agent' ? 'text-emerald-600' : 'text-slate-600' }}">
                                                        {{ $transfer['formattedFee'] }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-slate-400 italic py-3">{{ __('transfers.transfer_activity_no_transfers') }}</p>
                        @endif
                    </div>

                    {{-- Rest of World Section --}}
                    @if(count($restOfWorldTransfers) > 0)
                        <div>
                            <h3 class="text-base font-semibold text-slate-800 mb-4">{{ __('transfers.transfer_activity_other_leagues') }}</h3>

                            <div>
                                <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    {{ __('transfers.transfer_activity_transfers') }}
                                    <span class="text-sm font-normal text-slate-400">({{ count($restOfWorldTransfers) }})</span>
                                </h4>

                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-slate-200 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                                <th class="py-2 pr-3">{{ __('transfers.transfer_activity_player') }}</th>
                                                <th class="py-2 pr-3">{{ __('transfers.transfer_activity_position') }}</th>
                                                <th class="py-2 pr-3">{{ __('transfers.transfer_activity_from') }}</th>
                                                <th class="py-2 pr-3">{{ __('transfers.transfer_activity_to') }}</th>
                                                <th class="py-2 pr-3 text-right">{{ __('transfers.transfer_activity_fee') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($restOfWorldTransfers as $transfer)
                                                <tr class="hover:bg-slate-50">
                                                    <td class="py-2.5 pr-3 font-medium text-slate-900 truncate max-w-[140px] md:max-w-none">
                                                        {{ $transfer['playerName'] }}
                                                    </td>
                                                    <td class="py-2.5 pr-3">
                                                        <x-position-badge :position="$transfer['position'] ?? null" size="sm" />
                                                    </td>
                                                    <td class="py-2.5 pr-3 text-slate-600 max-w-[100px] md:max-w-none">
                                                        <div class="flex items-center gap-1.5">
                                                            @if(isset($transfer['fromTeamId']) && $teams->has($transfer['fromTeamId']))
                                                                <x-team-crest :team="$teams->get($transfer['fromTeamId'])" class="w-5 h-5 shrink-0" />
                                                            @endif
                                                            <span class="truncate">{{ $transfer['fromTeamName'] }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="py-2.5 pr-3 max-w-[100px] md:max-w-none {{ ($transfer['type'] ?? '') === 'foreign' ? 'text-amber-600 italic' : 'text-slate-600' }}">
                                                        <div class="flex items-center gap-1.5">
                                                            @if(isset($transfer['toTeamId']) && $teams->has($transfer['toTeamId']))
                                                                <x-team-crest :team="$teams->get($transfer['toTeamId'])" class="w-5 h-5 shrink-0" />
                                                            @endif
                                                            <span class="truncate">{{ $transfer['toTeamName'] }}</span>
                                                            @if(($transfer['type'] ?? '') === 'foreign')
                                                                <span class="hidden md:inline text-xs text-amber-500 ml-1 shrink-0">({{ __('transfers.transfer_activity_foreign') }})</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="py-2.5 pr-3 text-right text-slate-700 whitespace-nowrap">
                                                        {{ $transfer['formattedFee'] }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
