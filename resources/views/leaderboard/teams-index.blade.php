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
                    {{ __('leaderboard.teams_title') }}
                </h1>
                <p class="text-sm text-text-muted">{{ __('leaderboard.teams_subtitle') }}</p>
            </div>

            {{-- Back to main leaderboard --}}
            <div class="text-center">
                <a href="{{ route('leaderboard') }}" class="text-sm text-accent-blue hover:underline">
                    &larr; {{ __('leaderboard.back_to_leaderboard') }}
                </a>
            </div>

            {{-- Teams by Country --}}
            @if($teamsByCountry->isEmpty())
                <x-section-card>
                    <div class="p-8 text-center">
                        <p class="text-sm text-text-muted">{{ __('leaderboard.no_teams') }}</p>
                    </div>
                </x-section-card>
            @else
                @foreach($teamsByCountry as $group)
                    <x-section-card :title="$group['name']">
                        <div class="p-4">
                            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-3 md:gap-4">
                                @foreach($group['teams'] as $team)
                                    <a href="{{ route('leaderboard.team', $team->slug) }}"
                                       class="group flex flex-col items-center gap-2 p-3 rounded-xl border border-transparent hover:border-border-default hover:bg-surface-700/50 transition-all">
                                        <div class="w-12 h-12 md:w-14 md:h-14 flex items-center justify-center shrink-0">
                                            <x-team-crest :team="$team" class="max-w-full max-h-full object-contain" />
                                        </div>
                                        <div class="text-center min-w-0 w-full">
                                            <div class="text-[11px] md:text-xs font-medium text-text-secondary group-hover:text-text-primary truncate transition-colors">
                                                {{ $team->name }}
                                            </div>
                                            <div class="text-[10px] text-text-faint mt-0.5">
                                                {{ trans_choice('leaderboard.managers_count', $team->managers_count) }}
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </x-section-card>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
