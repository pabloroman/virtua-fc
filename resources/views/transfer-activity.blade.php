@php
/** @var App\Models\Game $game */
/** @var array $leagueTeamActivity */
/** @var int $leagueTransferCount */
/** @var array $restOfWorldTeamActivity */
/** @var int $restOfWorldCount */
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
                                {{ __('notifications.ai_transfer_message', ['count' => $leagueTransferCount + $restOfWorldCount]) }}
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
                                                    <span class="flex items-center gap-1 truncate min-w-0 text-slate-500">
                                                        @if(isset($transfer['toTeamId']) && $teams->has($transfer['toTeamId']))
                                                            <x-team-crest :team="$teams->get($transfer['toTeamId'])" class="w-4 h-4 shrink-0" />
                                                        @endif
                                                        <span class="truncate">{{ $transfer['toTeamName'] }}</span>
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

                    {{-- Rest of World Section — Team-Grouped --}}
                    @if(count($restOfWorldTeamActivity) > 0)
                        <div>
                            <h3 class="text-base font-semibold text-slate-800 mb-4">{{ __('transfers.transfer_activity_other_leagues') }}</h3>

                            <div class="columns-1 md:columns-2 gap-6">
                                @foreach($restOfWorldTeamActivity as $teamId => $activity)
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
                                                    <span class="flex items-center gap-1 truncate min-w-0 text-slate-500">
                                                        @if(isset($transfer['toTeamId']) && $teams->has($transfer['toTeamId']))
                                                            <x-team-crest :team="$teams->get($transfer['toTeamId'])" class="w-4 h-4 shrink-0" />
                                                        @endif
                                                        <span class="truncate">{{ $transfer['toTeamName'] }}</span>
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
                        </div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
