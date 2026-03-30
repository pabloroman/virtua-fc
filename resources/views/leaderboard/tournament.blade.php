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
                    {{ __('leaderboard.tournament_title') }}
                </h1>
                <p class="text-sm text-text-muted">{{ __('leaderboard.tournament_subtitle') }}</p>
            </div>

            {{-- Navigation Links --}}
            <div class="text-center">
                <a href="{{ route('leaderboard') }}" class="inline-flex items-center gap-1.5 text-sm text-accent-blue hover:underline">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    {{ __('leaderboard.back_to_career') }}
                </a>
                <span class="text-text-faint">|</span>
                <a href="{{ route('leaderboard.national-teams') }}" class="text-sm text-accent-blue hover:underline">
                    {{ __('leaderboard.browse_national_teams') }}
                </a>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-surface-800 border border-border-default rounded-xl px-4 py-3 text-center">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('leaderboard.total_players') }}</div>
                    <div class="font-heading text-xl font-bold text-text-primary mt-0.5">{{ number_format($totalPlayers) }}</div>
                </div>
                <div class="bg-surface-800 border border-border-default rounded-xl px-4 py-3 text-center">
                    <div class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('leaderboard.total_tournaments') }}</div>
                    <div class="font-heading text-xl font-bold text-text-primary mt-0.5">{{ number_format($totalTournaments) }}</div>
                </div>
            </div>

            {{-- Filters --}}
            <x-section-card>
                <div class="p-4" x-data="{
                    country: @js($selectedCountry ?? ''),
                    team: @js($selectedTeam ?? ''),
                    sort: @js($currentSort),
                    apply() {
                        const params = new URLSearchParams();
                        if (this.country) params.set('country', this.country);
                        if (this.team) params.set('team', this.team);
                        if (this.sort !== 'tournaments_won') params.set('sort', this.sort);
                        window.location.href = '{{ route('leaderboard.tournament') }}' + (params.toString() ? '?' + params.toString() : '');
                    }
                }">
                    <div class="flex flex-col md:flex-row gap-3">
                        {{-- Country filter --}}
                        <div class="flex-1">
                            <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('leaderboard.filter_country') }}</label>
                            <select x-model="country" @change="apply()"
                                class="w-full bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary px-3 py-2 min-h-[44px]">
                                <option value="">{{ __('leaderboard.all_countries') }}</option>
                                @foreach($countries as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Team played filter --}}
                        <div class="flex-1">
                            <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('leaderboard.filter_team_played') }}</label>
                            <select x-model="team" @change="apply()"
                                class="w-full bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary px-3 py-2 min-h-[44px]">
                                <option value="">{{ __('leaderboard.all_teams') }}</option>
                                @foreach($teamsPlayed as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Sort --}}
                        <div class="flex-1">
                            <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('leaderboard.sort_by') }}</label>
                            <select x-model="sort" @change="apply()"
                                class="w-full bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary px-3 py-2 min-h-[44px]">
                                <option value="tournaments_won">{{ __('leaderboard.titles_full') }}</option>
                                <option value="best_finish">{{ __('leaderboard.best_finish_full') }}</option>
                                <option value="total_tournaments">{{ __('leaderboard.tournaments_full') }}</option>
                                <option value="win_rate">{{ __('leaderboard.tournament_win_rate_full') }}</option>
                                <option value="goals_scored">{{ __('leaderboard.tournament_goals_full') }}</option>
                            </select>
                        </div>
                    </div>
                </div>
            </x-section-card>

            {{-- Leaderboard Table --}}
            <x-section-card>
                @if($rankings->isEmpty())
                    <div class="p-8 text-center">
                        <p class="text-sm text-text-muted">{{ __('leaderboard.tournament_no_results') }}</p>
                    </div>
                @else
                    @php
                        $sortUrl = function (string $column) use ($selectedCountry, $selectedTeam) {
                            $params = array_filter([
                                'country' => $selectedCountry,
                                'team' => $selectedTeam,
                                'sort' => $column === 'tournaments_won' ? null : $column,
                            ]);
                            return route('leaderboard.tournament', $params);
                        };
                        $sortIcon = '<svg class="size-3 inline-block ml-0.5 -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>';

                        $bestFinishLabel = function (int $points): string {
                            $label = \App\Modules\Report\Services\TournamentSnapshotService::resultLabelFromPoints($points);
                            return __('season.result_' . $label);
                        };
                    @endphp

                    {{-- Desktop Header --}}
                    <div class="hidden md:grid grid-cols-[3rem_1fr_3.5rem_3rem_6rem_4.5rem_3.5rem_6rem] gap-2 px-4 py-2.5 border-b border-border-default">
                        <div class="text-[10px] text-text-muted uppercase tracking-wider text-center">{{ __('leaderboard.rank') }}</div>
                        <div class="text-[10px] text-text-muted uppercase tracking-wider">{{ __('leaderboard.manager') }}</div>
                        <a href="{{ $sortUrl('total_tournaments') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'total_tournaments' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.tournaments') }}{!! $currentSort === 'total_tournaments' ? $sortIcon : '' !!}
                        </a>
                        <a href="{{ $sortUrl('tournaments_won') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'tournaments_won' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.titles') }}{!! $currentSort === 'tournaments_won' ? $sortIcon : '' !!}
                        </a>
                        <a href="{{ $sortUrl('best_finish') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'best_finish' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.best_finish') }}{!! $currentSort === 'best_finish' ? $sortIcon : '' !!}
                        </a>
                        <a href="{{ $sortUrl('win_rate') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'win_rate' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.tournament_win_rate') }}{!! $currentSort === 'win_rate' ? $sortIcon : '' !!}
                        </a>
                        <a href="{{ $sortUrl('goals_scored') }}" class="text-[10px] uppercase tracking-wider text-center {{ $currentSort === 'goals_scored' ? 'text-accent-blue font-semibold' : 'text-text-muted hover:text-text-secondary' }} transition">
                            {{ __('leaderboard.tournament_goals') }}{!! $currentSort === 'goals_scored' ? $sortIcon : '' !!}
                        </a>
                        <div class="text-[10px] text-text-muted uppercase tracking-wider text-center">{{ __('leaderboard.tournament_record') }}</div>
                    </div>

                    <div class="divide-y divide-border-default">
                        @foreach($rankings as $index => $manager)
                            @php
                                $rank = $rankings->firstItem() + $index;
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
                                        <div class="flex items-center gap-2 mt-0.5">
                                            @if($manager->tournaments_won > 0)
                                                <span class="text-xs text-accent-gold font-semibold">{{ $manager->tournaments_won }}x</span>
                                            @endif
                                            <span class="text-xs text-text-muted">{{ $manager->total_tournaments }} {{ __('leaderboard.tournaments_suffix') }}</span>
                                        </div>
                                    </div>

                                    <div class="text-right shrink-0">
                                        <div class="font-heading text-lg font-bold text-text-primary">{{ number_format($manager->win_rate ?? 0, 1) }}%</div>
                                        <div class="text-[10px] text-text-muted">
                                            {{ $bestFinishLabel((int) $manager->best_finish) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Desktop Layout --}}
                            <div class="hidden md:grid grid-cols-[3rem_1fr_3.5rem_3rem_6rem_4.5rem_3.5rem_6rem] gap-2 px-4 py-2.5 items-center transition-colors hover:bg-surface-700/30">
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

                                <span class="text-sm text-text-secondary text-center">{{ $manager->total_tournaments }}</span>

                                <span class="text-sm font-semibold text-center {{ $manager->tournaments_won > 0 ? 'text-accent-gold' : 'text-text-secondary' }}">{{ $manager->tournaments_won }}</span>

                                <span class="text-xs text-text-secondary text-center truncate">{{ $bestFinishLabel((int) $manager->best_finish) }}</span>

                                <span class="text-sm font-semibold text-text-primary text-center">{{ number_format($manager->win_rate ?? 0, 1) }}%</span>

                                <span class="text-sm text-text-secondary text-center">{{ $manager->total_goals }}</span>

                                <div class="text-center text-xs text-text-secondary">
                                    <span class="text-green-400">{{ $manager->total_wins }}{{ __('leaderboard.wins') }}</span>
                                    <span class="text-text-muted mx-0.5">/</span>
                                    <span class="text-text-muted">{{ $manager->total_draws }}{{ __('leaderboard.draws') }}</span>
                                    <span class="text-text-muted mx-0.5">/</span>
                                    <span class="text-red-400">{{ $manager->total_losses }}{{ __('leaderboard.losses') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Pagination --}}
                    @if($rankings->hasPages())
                        <div class="px-4 py-3 border-t border-border-default">
                            {{ $rankings->links() }}
                        </div>
                    @endif
                @endif
            </x-section-card>
        </div>
    </div>
</x-app-layout>
