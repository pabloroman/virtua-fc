<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-center">
            <x-application-logo />
        </div>
    </x-slot>

    <div class="py-6 md:py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Page Title --}}
            <div class="text-center space-y-1">
                <h1 class="font-heading text-2xl md:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    {{ __('leaderboard.national_teams_title') }}
                </h1>
                <p class="text-sm text-text-muted">{{ __('leaderboard.national_teams_subtitle') }}</p>
            </div>

            {{-- Navigation --}}
            <div class="text-center">
                <a href="{{ route('leaderboard.tournament') }}" class="inline-flex items-center gap-1.5 text-sm text-accent-blue hover:underline">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    {{ __('leaderboard.browse_tournament') }}
                </a>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-surface-800 border border-border-default rounded-xl px-4 py-3 text-center">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('leaderboard.national_teams_title') }}</div>
                    <div class="font-heading text-xl font-bold text-text-primary mt-0.5">{{ $totalTeams }}</div>
                </div>
                <div class="bg-surface-800 border border-border-default rounded-xl px-4 py-3 text-center">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('leaderboard.total_tournaments') }}</div>
                    <div class="font-heading text-xl font-bold text-text-primary mt-0.5">{{ number_format($totalTournaments) }}</div>
                </div>
            </div>

            {{-- Teams Grid --}}
            @if($teams->isEmpty())
                <x-section-card>
                    <div class="p-8 text-center">
                        <p class="text-sm text-text-muted">{{ __('leaderboard.no_national_teams') }}</p>
                    </div>
                </x-section-card>
            @else
                <x-section-card>
                    <div class="p-4">
                        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3 md:gap-4">
                            @foreach($teams as $team)
                                <a href="{{ route('leaderboard.national-team', $team->slug) }}"
                                   class="group flex flex-col items-center gap-2 p-3 rounded-xl border border-transparent hover:border-border-default hover:bg-surface-700/50 transition-all">
                                    <div class="w-12 h-12 md:w-14 md:h-14 flex items-center justify-center shrink-0">
                                        <x-team-crest :team="$team" class="max-w-full max-h-full object-contain" />
                                    </div>
                                    <div class="text-center min-w-0 w-full">
                                        <div class="text-[11px] md:text-xs font-medium text-text-secondary group-hover:text-text-primary truncate transition-colors">
                                            {{ $team->name }}
                                        </div>
                                        <div class="text-[10px] text-text-faint mt-0.5">
                                            {{ trans_choice('leaderboard.tournaments_count', $team->tournaments_count, ['count' => $team->tournaments_count]) }}
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </x-section-card>
            @endif
        </div>
    </div>
</x-app-layout>
