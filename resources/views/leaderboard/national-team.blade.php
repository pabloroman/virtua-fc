<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-center">
            <x-application-logo />
        </div>
    </x-slot>

    <div class="py-6 md:py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Team Header --}}
            <div class="text-center space-y-3">
                <div class="flex justify-center">
                    <div class="w-16 h-16 md:w-20 md:h-20 flex items-center justify-center">
                        <x-team-crest :team="$team" class="max-w-full max-h-full object-contain" />
                    </div>
                </div>
                <h1 class="font-heading text-2xl md:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    {{ $team->name }}
                </h1>
                <p class="text-sm text-text-muted">{{ __('leaderboard.national_team_subtitle') }}</p>
            </div>

            {{-- Navigation --}}
            <div class="flex justify-center gap-4">
                <a href="{{ route('leaderboard.national-teams') }}" class="text-sm text-accent-blue hover:underline">
                    &larr; {{ __('leaderboard.back_to_national_teams') }}
                </a>
                <span class="text-text-faint">|</span>
                <a href="{{ route('leaderboard.tournament') }}" class="text-sm text-accent-blue hover:underline">
                    {{ __('leaderboard.browse_tournament') }}
                </a>
            </div>

            @if($stats['tournamentsPlayed'] === 0)
                <x-section-card>
                    <div class="p-8 text-center">
                        <p class="text-sm text-text-muted">{{ __('leaderboard.no_tournament_data') }}</p>
                    </div>
                </x-section-card>
            @else
                {{-- Result Distribution --}}
                <x-section-card :title="__('leaderboard.result_distribution')">
                    <div class="p-4 space-y-2">
                        @php
                            $resultColors = [
                                'champion' => 'bg-accent-green',
                                'runner_up' => 'bg-amber-500',
                                'semi_finalist' => 'bg-accent-blue',
                                'quarter_finalist' => 'bg-violet-500',
                                'round_of_16' => 'bg-surface-600',
                                'round_of_32' => 'bg-surface-600',
                                'group_stage' => 'bg-surface-600',
                            ];
                            $resultTextColors = [
                                'champion' => 'text-accent-green',
                                'runner_up' => 'text-amber-500',
                                'semi_finalist' => 'text-accent-blue',
                                'quarter_finalist' => 'text-violet-400',
                                'round_of_16' => 'text-text-secondary',
                                'round_of_32' => 'text-text-muted',
                                'group_stage' => 'text-text-muted',
                            ];
                        @endphp
                        @foreach($resultDistribution as $result)
                            @if($result['count'] > 0)
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-medium w-28 shrink-0 {{ $resultTextColors[$result['label']] ?? 'text-text-secondary' }}">
                                        {{ __('season.result_' . $result['label']) }}
                                    </span>
                                    <div class="flex-1 h-5 bg-surface-700 rounded-full overflow-hidden">
                                        <div class="h-full {{ $resultColors[$result['label']] ?? 'bg-surface-600' }} rounded-full"
                                             style="width: {{ max($result['percentage'], 4) }}%">
                                        </div>
                                    </div>
                                    <span class="text-xs text-text-muted w-8 text-right shrink-0 tabular-nums">{{ $result['count'] }}</span>
                                    <span class="text-[10px] text-text-faint w-10 text-right shrink-0 tabular-nums">{{ $result['percentage'] }}%</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-section-card>

                {{-- Player Selection Frequency --}}
                <x-section-card :title="__('leaderboard.player_frequency')">
                    @if($playerFrequency->isEmpty())
                        <div class="p-8 text-center">
                            <p class="text-sm text-text-muted">{{ __('leaderboard.no_tournament_data') }}</p>
                        </div>
                    @else
                        <p class="px-4 pt-3 text-xs text-text-muted">{{ __('leaderboard.player_frequency_subtitle') }}</p>

                        <div class="divide-y divide-border-default">
                            @foreach($playerFrequency as $index => $player)
                                @php
                                    $isTop10 = $index < 10;
                                    $barColor = $player['percentage'] >= 80 ? 'bg-accent-green' : ($player['percentage'] >= 50 ? 'bg-accent-gold' : 'bg-accent-blue');
                                    $textColor = $player['percentage'] >= 80 ? 'text-accent-green' : ($player['percentage'] >= 50 ? 'text-accent-gold' : 'text-text-secondary');
                                @endphp

                                {{-- Mobile Layout --}}
                                <div class="md:hidden px-4 py-3 space-y-2">
                                    <div class="flex items-center gap-3">
                                        @if($isTop10)
                                            <span class="font-heading text-sm font-bold w-5 text-center shrink-0 {{ $index < 3 ? 'text-amber-500' : 'text-text-muted' }}">{{ $index + 1 }}</span>
                                        @endif
                                        <x-position-badge :position="$player['position']" size="sm" />
                                        <span class="text-sm font-medium text-text-primary flex-1 truncate">{{ $player['player_name'] }}</span>
                                        <span class="text-sm font-semibold {{ $textColor }} shrink-0">{{ $player['percentage'] }}%</span>
                                    </div>
                                    <div class="h-1.5 bg-surface-700 rounded-full overflow-hidden">
                                        <div class="h-full {{ $barColor }} rounded-full" style="width: {{ $player['percentage'] }}%"></div>
                                    </div>
                                </div>

                                {{-- Desktop Layout --}}
                                <div class="hidden md:flex items-center gap-3 px-4 py-2.5">
                                    @if($isTop10)
                                        <span class="font-heading text-sm font-bold w-5 text-center shrink-0 {{ $index < 3 ? 'text-amber-500' : 'text-text-muted' }}">{{ $index + 1 }}</span>
                                    @else
                                        <span class="w-5 shrink-0"></span>
                                    @endif
                                    <x-position-badge :position="$player['position']" size="sm" />
                                    <span class="text-sm font-medium text-text-primary w-44 shrink-0 truncate">{{ $player['player_name'] }}</span>
                                    <div class="flex-1 h-1.5 bg-surface-700 rounded-full overflow-hidden">
                                        <div class="h-full {{ $barColor }} rounded-full" style="width: {{ $player['percentage'] }}%"></div>
                                    </div>
                                    <span class="text-sm font-semibold {{ $textColor }} w-12 text-right shrink-0">{{ $player['percentage'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-section-card>
            @endif
        </div>
    </div>
</x-app-layout>
