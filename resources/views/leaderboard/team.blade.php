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
                <p class="text-sm text-text-muted">{{ __('leaderboard.team_subtitle') }}</p>
            </div>

            {{-- Navigation --}}
            <div class="flex justify-center gap-4">
                <a href="{{ route('leaderboard.teams') }}" class="text-sm text-accent-blue hover:underline">
                    &larr; {{ __('leaderboard.back_to_teams') }}
                </a>
                <span class="text-text-faint">|</span>
                <a href="{{ route('leaderboard') }}" class="text-sm text-accent-blue hover:underline">
                    {{ __('leaderboard.title') }}
                </a>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-surface-800 border border-border-default rounded-xl px-4 py-3 text-center">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('leaderboard.total_managers') }}</div>
                    <div class="font-heading text-xl font-bold text-text-primary mt-0.5">{{ number_format($totalManagers) }}</div>
                </div>
                <div class="bg-surface-800 border border-border-default rounded-xl px-4 py-3 text-center">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('leaderboard.total_matches') }}</div>
                    <div class="font-heading text-xl font-bold text-text-primary mt-0.5">{{ number_format($totalMatches) }}</div>
                </div>
            </div>

            {{-- Sort Filter --}}
            <x-section-card>
                <div class="p-4" x-data="{
                    sort: @js($currentSort),
                    apply() {
                        const params = new URLSearchParams();
                        if (this.sort !== 'win_percentage') params.set('sort', this.sort);
                        window.location.href = '{{ route('leaderboard.team', $team->slug) }}' + (params.toString() ? '?' + params.toString() : '');
                    }
                }">
                    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
                        <div class="flex-1 w-full sm:w-auto">
                            <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('leaderboard.sort_by') }}</label>
                            <select x-model="sort" @change="apply()"
                                class="w-full bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary px-3 py-2 min-h-[44px]">
                                <option value="win_percentage">{{ __('leaderboard.win_percentage_full') }}</option>
                                <option value="longest_unbeaten_streak">{{ __('leaderboard.unbeaten_streak_full') }}</option>
                                <option value="matches_played">{{ __('leaderboard.matches_played_full') }}</option>
                                <option value="seasons_completed">{{ __('leaderboard.seasons_full') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </x-section-card>

            {{-- Leaderboard Table --}}
            <x-section-card>
                @if($managers->isEmpty())
                    <div class="p-8 text-center">
                        <p class="text-sm text-text-muted">{{ __('leaderboard.no_results') }}</p>
                        <p class="text-xs text-text-faint mt-1">{{ __('leaderboard.min_matches', ['count' => $minMatches]) }}</p>
                    </div>
                @else
                    @php
                        $sortUrl = function (string $column) use ($team) {
                            $params = array_filter([
                                'sort' => $column === 'win_percentage' ? null : $column,
                            ]);
                            return route('leaderboard.team', ['slug' => $team->slug] + $params);
                        };
                        $sortIcon = '<svg class="size-3 inline-block ml-0.5 -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>';
                    @endphp

                    {{-- Desktop Header --}}
                    <div class="hidden md:grid grid-cols-[3rem_1fr_4rem_5rem_8rem_4rem_4rem] gap-2 px-4 py-2.5 border-b border-border-default">
                        <div class="text-[10px] text-text-muted uppercase tracking-wider text-center">{{ __('leaderboard.rank') }}</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('leaderboard.manager') }}</div>
                        <a href="{{ $sortUrl('matches_played') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'matches_played' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.matches_played') }}{!! $currentSort === 'matches_played' ? $sortIcon : '' !!}
                        </a>
                        <a href="{{ $sortUrl('win_percentage') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'win_percentage' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.win_percentage') }}{!! $currentSort === 'win_percentage' ? $sortIcon : '' !!}
                        </a>
                        <div class="text-[10px] text-text-muted uppercase tracking-wider text-center">{{ __('leaderboard.record') }}</div>
                        <a href="{{ $sortUrl('longest_unbeaten_streak') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'longest_unbeaten_streak' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.unbeaten_streak') }}{!! $currentSort === 'longest_unbeaten_streak' ? $sortIcon : '' !!}
                        </a>
                        <a href="{{ $sortUrl('seasons_completed') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'seasons_completed' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.seasons') }}{!! $currentSort === 'seasons_completed' ? $sortIcon : '' !!}
                        </a>
                    </div>

                    <div class="divide-y divide-border-default">
                        @foreach($managers as $index => $manager)
                            @php
                                $rank = $managers->firstItem() + $index;
                                $avatarUrl = \Illuminate\Support\Facades\Storage::disk('assets')->url('managers/'.($manager->avatar ?? 'blue').'.png');
                            @endphp

                            {{-- Mobile Layout --}}
                            <div class="md:hidden px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="font-heading text-lg font-bold text-text-muted w-8 text-center shrink-0 {{ $rank <= 3 ? 'text-amber-500' : '' }}">
                                        {{ $rank }}
                                    </span>

                                    <div class="size-9 rounded-full overflow-hidden shrink-0 flex items-start justify-center">
                                        <img src="{{ $avatarUrl }}" alt="" class="size-12 max-w-none -mt-0.5">
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            @if($manager->username)
                                                <a href="{{ route('manager.profile', $manager->username) }}" class="text-sm font-medium text-text-primary hover:text-accent-blue truncate">
                                                    {{ $manager->name }}
                                                </a>
                                            @else
                                                <span class="text-sm font-medium text-text-primary truncate">{{ $manager->name }}</span>
                                            @endif
                                        </div>
                                        <span class="text-xs text-text-muted">{{ $manager->matches_played }} {{ __('leaderboard.matches_suffix') }}</span>
                                    </div>

                                    <div class="text-right shrink-0">
                                        <div class="font-heading text-lg font-bold text-text-primary">{{ number_format($manager->win_percentage, 1) }}%</div>
                                        <div class="text-[10px] text-text-muted">
                                            {{ $manager->longest_unbeaten_streak }} {{ __('leaderboard.unbeaten_streak') }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Desktop Layout --}}
                            <div class="hidden md:grid grid-cols-[3rem_1fr_4rem_5rem_8rem_4rem_4rem] gap-2 px-4 py-2.5 items-center transition-colors hover:bg-surface-700/30">
                                <span class="font-heading text-base font-bold text-center {{ $rank <= 3 ? 'text-amber-500' : 'text-text-muted' }}">
                                    {{ $rank }}
                                </span>

                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="size-8 rounded-full overflow-hidden shrink-0 flex items-start justify-center">
                                        <img src="{{ $avatarUrl }}" alt="" class="size-11 max-w-none -mt-0.5">
                                    </div>
                                    <div class="min-w-0">
                                        @if($manager->username)
                                            <a href="{{ route('manager.profile', $manager->username) }}" class="text-sm font-medium text-text-primary hover:text-accent-blue truncate block">
                                                {{ $manager->name }}
                                            </a>
                                        @else
                                            <span class="text-sm font-medium text-text-primary truncate block">{{ $manager->name }}</span>
                                        @endif
                                    </div>
                                </div>

                                <span class="text-sm text-text-secondary text-center">{{ $manager->matches_played }}</span>

                                <span class="text-sm font-semibold text-text-primary text-center">{{ number_format($manager->win_percentage, 1) }}%</span>

                                <div class="text-center text-xs text-text-secondary">
                                    <span class="text-green-400">{{ $manager->matches_won }}{{ __('leaderboard.wins') }}</span>
                                    <span class="text-text-muted mx-0.5">/</span>
                                    <span class="text-text-muted">{{ $manager->matches_drawn }}{{ __('leaderboard.draws') }}</span>
                                    <span class="text-text-muted mx-0.5">/</span>
                                    <span class="text-red-400">{{ $manager->matches_lost }}{{ __('leaderboard.losses') }}</span>
                                </div>

                                <span class="text-sm text-text-secondary text-center">{{ $manager->longest_unbeaten_streak }}</span>

                                <span class="text-sm text-text-secondary text-center">{{ $manager->seasons_completed }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    @if($managers->hasPages())
                        <div class="px-4 py-3 border-t border-border-default">
                            {{ $managers->links() }}
                        </div>
                    @endif

                    {{-- Min matches note --}}
                    <div class="px-4 py-2 border-t border-border-default">
                        <p class="text-[10px] text-text-faint text-center">{{ __('leaderboard.min_matches', ['count' => $minMatches]) }}</p>
                    </div>
                @endif
            </x-section-card>
        </div>
    </div>
</x-app-layout>
