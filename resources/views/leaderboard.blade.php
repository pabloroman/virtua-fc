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
                    {{ __('leaderboard.title') }}
                </h1>
                <p class="text-sm text-text-muted">{{ __('leaderboard.subtitle') }}</p>
            </div>

            {{-- Navigation Links --}}
            <div class="flex justify-center gap-6">
                <a href="{{ route('leaderboard.teams') }}" class="inline-flex items-center gap-1.5 text-sm text-accent-blue hover:underline">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                    </svg>
                    {{ __('leaderboard.browse_by_team') }}
                </a>
                <a href="{{ route('leaderboard.tournament') }}" class="inline-flex items-center gap-1.5 text-sm text-accent-blue hover:underline">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-4.5A3.375 3.375 0 0 0 13.125 10.875h-2.25A3.375 3.375 0 0 0 7.5 14.25v4.5m9-13.5a2.25 2.25 0 0 0-2.25-2.25h-2.5A2.25 2.25 0 0 0 9.5 5.25v1.5a2.25 2.25 0 0 0 2.25 2.25h2.5A2.25 2.25 0 0 0 16.5 6.75v-1.5Z" />
                    </svg>
                    {{ __('leaderboard.browse_tournament') }}
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

            {{-- Filters --}}
            <x-section-card>
                <div class="p-4" x-data="{
                    country: @js($selectedCountry ?? ''),
                    province: @js($selectedProvince ?? ''),
                    sort: @js($currentSort),
                    provinces: @js($provinces),
                    apply() {
                        const params = new URLSearchParams();
                        if (this.country) params.set('country', this.country);
                        if (this.province) params.set('province', this.province);
                        if (this.sort !== 'win_percentage') params.set('sort', this.sort);
                        window.location.href = '{{ route('leaderboard') }}' + (params.toString() ? '?' + params.toString() : '');
                    },
                    async updateProvinces() {
                        this.province = '';
                        if (!this.country) {
                            this.provinces = [];
                        }
                        this.apply();
                    }
                }">
                    <div class="flex flex-col md:flex-row gap-3">
                        {{-- Country filter --}}
                        <div class="flex-1">
                            <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('leaderboard.filter_country') }}</label>
                            <select x-model="country" @change="updateProvinces()"
                                class="w-full bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary px-3 py-2 min-h-[44px]">
                                <option value="">{{ __('leaderboard.all_countries') }}</option>
                                @foreach($countries as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Province filter --}}
                        <div class="flex-1">
                            <label class="text-[10px] text-text-muted uppercase tracking-wider block mb-1">{{ __('leaderboard.filter_province') }}</label>
                            <select x-model="province" @change="apply()"
                                class="w-full bg-surface-700 border border-border-default rounded-lg text-sm text-text-primary px-3 py-2 min-h-[44px]"
                                :disabled="!country || provinces.length === 0">
                                <option value="">{{ __('leaderboard.all_provinces') }}</option>
                                <template x-for="p in provinces" :key="p">
                                    <option :value="p" x-text="p" :selected="p === province"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Sort --}}
                        <div class="flex-1">
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
                        $sortUrl = function (string $column) use ($selectedCountry, $selectedProvince) {
                            $params = array_filter([
                                'country' => $selectedCountry,
                                'province' => $selectedProvince,
                                'sort' => $column === 'win_percentage' ? null : $column,
                            ]);
                            return route('leaderboard', $params);
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
                                        @if($manager->team_name)
                                            <div class="flex items-center gap-1 mt-0.5">
                                                <img src="{{ $manager->team_image }}" alt="{{ $manager->team_name }}" class="size-3.5 shrink-0">
                                                <span class="text-xs text-text-muted truncate">{{ $manager->team_name }}</span>
                                            </div>
                                        @else
                                            <span class="text-xs text-text-muted">{{ $manager->matches_played }} {{ __('leaderboard.matches_suffix') }}</span>
                                        @endif
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
                                        @if($manager->team_name)
                                            <div class="flex items-center gap-1 mt-0.5">
                                                <img src="{{ $manager->team_image }}" alt="{{ $manager->team_name }}" class="size-3.5 shrink-0">
                                                <span class="text-xs text-text-muted truncate">{{ $manager->team_name }}</span>
                                            </div>
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
