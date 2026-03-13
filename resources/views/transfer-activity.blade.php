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

    <div class="max-w-7xl mx-auto px-4 pb-8">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mt-6 mb-6">
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    {{ __('transfers.transfer_activity_title', ['window' => __('transfers.transfer_activity_' . $window)]) }}
                </h2>
                <p class="text-xs text-text-muted mt-0.5">
                    {{ __('notifications.ai_transfer_message', ['count' => $leagueTransferCount + $restOfWorldCount]) }}
                </p>
            </div>
            <a href="{{ route('show-game', $game->id) }}"
               class="inline-flex items-center gap-1.5 text-sm text-text-secondary hover:text-text-primary min-h-[44px]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                {{ __('app.back') }}
            </a>
        </div>

        {{-- League Section — Team-Grouped --}}
        <div class="mb-8">
            <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary mb-4">{{ $competitionName }}</h3>

            @if(count($leagueTeamActivity) > 0)
                <div class="columns-1 md:columns-2 gap-6">
                    @foreach($leagueTeamActivity as $teamId => $activity)
                        <div class="break-inside-avoid pb-3 mb-3 border-b border-border-default last:border-b-0 last:mb-0 last:pb-0">
                            {{-- Team header --}}
                            <div class="flex items-center gap-2 mb-2">
                                @if($teams->has($teamId))
                                    <x-team-crest :team="$teams->get($teamId)" class="w-6 h-6 shrink-0" />
                                @endif
                                <span class="font-semibold text-sm text-text-primary">{{ $activity['teamName'] }}</span>
                            </div>

                            {{-- Transfer rows --}}
                            <div class="space-y-1 pl-1 md:pl-8">
                                {{-- OUT transfers --}}
                                @foreach($activity['out'] as $transfer)
                                    <div class="flex items-center gap-1.5 md:gap-2 text-sm min-h-[28px]">
                                        <span class="text-accent-red font-bold w-4 shrink-0 text-center" title="{{ __('transfers.transfer_activity_out') }}">&#x2197;</span>
                                        <x-position-badge :position="$transfer['position']" size="sm" />
                                        <span class="text-text-primary truncate min-w-0">{{ $transfer['playerName'] }}</span>
                                        <span class="text-text-secondary shrink-0">&rarr;</span>
                                        <span class="flex items-center gap-1 truncate min-w-0 text-text-muted">
                                            @if(isset($transfer['toTeamId']) && $teams->has($transfer['toTeamId']))
                                                <x-team-crest :team="$teams->get($transfer['toTeamId'])" class="w-4 h-4 shrink-0" />
                                            @endif
                                            <span class="truncate">{{ $transfer['toTeamName'] }}</span>
                                        </span>
                                        <span class="ml-auto text-text-secondary whitespace-nowrap text-xs font-medium">{{ $transfer['formattedFee'] }}</span>
                                    </div>
                                @endforeach

                                {{-- IN transfers --}}
                                @foreach($activity['in'] as $transfer)
                                    <div class="flex items-center gap-1.5 md:gap-2 text-sm min-h-[28px]">
                                        <span class="text-accent-green font-bold w-4 shrink-0 text-center" title="{{ __('transfers.transfer_activity_in') }}">&#x2199;</span>
                                        <x-position-badge :position="$transfer['position']" size="sm" />
                                        <span class="text-text-primary truncate min-w-0">{{ $transfer['playerName'] }}</span>
                                        @if($transfer['fromTeamId'])
                                            <span class="text-text-secondary shrink-0">&larr;</span>
                                            <span class="flex items-center gap-1 truncate min-w-0 text-text-muted">
                                                @if($teams->has($transfer['fromTeamId']))
                                                    <x-team-crest :team="$teams->get($transfer['fromTeamId'])" class="w-4 h-4 shrink-0" />
                                                @endif
                                                <span class="truncate">{{ $transfer['fromTeamName'] }}</span>
                                            </span>
                                        @endif
                                        <span class="ml-auto whitespace-nowrap text-xs font-medium {{ $transfer['type'] === 'free_agent' ? 'text-accent-green' : 'text-text-secondary' }}">
                                            {{ $transfer['formattedFee'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-text-secondary italic py-3">{{ __('transfers.transfer_activity_no_transfers') }}</p>
            @endif
        </div>

        {{-- Rest of World Section — Team-Grouped --}}
        @if(count($restOfWorldTeamActivity) > 0)
            <div>
                <h3 class="font-heading text-sm font-semibold uppercase tracking-widest text-text-secondary mb-4">{{ __('transfers.transfer_activity_other_leagues') }}</h3>

                <div class="columns-1 md:columns-2 gap-6">
                    @foreach($restOfWorldTeamActivity as $teamId => $activity)
                        <div class="break-inside-avoid pb-3 mb-3 border-b border-border-default last:border-b-0 last:mb-0 last:pb-0">
                            {{-- Team header --}}
                            <div class="flex items-center gap-2 mb-2">
                                @if($teams->has($teamId))
                                    <x-team-crest :team="$teams->get($teamId)" class="w-6 h-6 shrink-0" />
                                @endif
                                <span class="font-semibold text-sm text-text-primary">{{ $activity['teamName'] }}</span>
                            </div>

                            {{-- Transfer rows --}}
                            <div class="space-y-1 pl-1 md:pl-8">
                                {{-- OUT transfers --}}
                                @foreach($activity['out'] as $transfer)
                                    <div class="flex items-center gap-1.5 md:gap-2 text-sm min-h-[28px]">
                                        <span class="text-accent-red font-bold w-4 shrink-0 text-center" title="{{ __('transfers.transfer_activity_out') }}">&#x2197;</span>
                                        <x-position-badge :position="$transfer['position']" size="sm" />
                                        <span class="text-text-primary truncate min-w-0">{{ $transfer['playerName'] }}</span>
                                        <span class="text-text-secondary shrink-0">&rarr;</span>
                                        <span class="flex items-center gap-1 truncate min-w-0 text-text-muted">
                                            @if(isset($transfer['toTeamId']) && $teams->has($transfer['toTeamId']))
                                                <x-team-crest :team="$teams->get($transfer['toTeamId'])" class="w-4 h-4 shrink-0" />
                                            @endif
                                            <span class="truncate">{{ $transfer['toTeamName'] }}</span>
                                        </span>
                                        <span class="ml-auto text-text-secondary whitespace-nowrap text-xs font-medium">{{ $transfer['formattedFee'] }}</span>
                                    </div>
                                @endforeach

                                {{-- IN transfers --}}
                                @foreach($activity['in'] as $transfer)
                                    <div class="flex items-center gap-1.5 md:gap-2 text-sm min-h-[28px]">
                                        <span class="text-accent-green font-bold w-4 shrink-0 text-center" title="{{ __('transfers.transfer_activity_in') }}">&#x2199;</span>
                                        <x-position-badge :position="$transfer['position']" size="sm" />
                                        <span class="text-text-primary truncate min-w-0">{{ $transfer['playerName'] }}</span>
                                        @if($transfer['fromTeamId'])
                                            <span class="text-text-secondary shrink-0">&larr;</span>
                                            <span class="flex items-center gap-1 truncate min-w-0 text-text-muted">
                                                @if($teams->has($transfer['fromTeamId']))
                                                    <x-team-crest :team="$teams->get($transfer['fromTeamId'])" class="w-4 h-4 shrink-0" />
                                                @endif
                                                <span class="truncate">{{ $transfer['fromTeamName'] }}</span>
                                            </span>
                                        @endif
                                        <span class="ml-auto whitespace-nowrap text-xs font-medium {{ $transfer['type'] === 'free_agent' ? 'text-accent-green' : 'text-text-secondary' }}">
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
</x-app-layout>
