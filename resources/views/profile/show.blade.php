<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-center">
            <x-application-logo />
        </div>
    </x-slot>

    <div class="py-6 md:py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Profile Header --}}
            <x-section-card>
                <div class="p-5 flex flex-col items-center text-center gap-3">
                    @if($user->avatar)
                        <div class="size-20 rounded-full overflow-hidden flex items-start justify-center">
                            <img src="{{ $user->getAvatarUrl() }}" alt="{{ $user->username }}" class="size-28 max-w-none -mt-1">
                        </div>
                    @else
                        <div class="size-20 rounded-full bg-surface-700 flex items-center justify-center">
                            <svg class="w-10 h-10 text-text-muted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </div>
                    @endif

                    <div>
                        <h3 class="text-2xl font-heading font-bold text-text-primary">{{ $user->name }}</h3>
                        @if($user->username)
                            <span class="text-sm text-text-muted">{{ '@' . $user->username }}</span>
                        @endif
                    </div>

                    @if($user->bio)
                        <p class="text-sm text-text-secondary max-w-md">{{ $user->bio }}</p>
                    @endif

                    <p class="text-xs text-text-muted">
                        {{ __('profile.member_since', ['date' => $user->created_at->translatedFormat('M Y')]) }}
                    </p>
                </div>
            </x-section-card>

            {{-- Career Stats --}}
            @if($careerStats['matches'] > 0)
                <x-section-card :title="__('profile.career_stats')">
                    <div class="p-5">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach([
                                ['label' => __('profile.matches_played'), 'value' => $careerStats['matches']],
                                ['label' => __('profile.wins'), 'value' => $careerStats['wins']],
                                ['label' => __('profile.draws'), 'value' => $careerStats['draws']],
                                ['label' => __('profile.losses'), 'value' => $careerStats['losses']],
                                ['label' => __('profile.win_percentage'), 'value' => $careerStats['win_percentage'] . '%'],
                                ['label' => __('profile.best_streak'), 'value' => $careerStats['best_streak']],
                                ['label' => __('profile.seasons'), 'value' => $careerStats['seasons']],
                            ] as $stat)
                                <div class="bg-surface-700 rounded-lg px-3 py-2.5 text-center">
                                    <p class="text-lg font-heading font-bold text-text-primary">{{ $stat['value'] }}</p>
                                    <p class="text-[10px] uppercase tracking-wider text-text-muted">{{ $stat['label'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-section-card>
            @endif

            {{-- Trophy Room --}}
            @if($trophies->isNotEmpty())
                <x-section-card :title="__('profile.trophy_room')">
                    <x-slot:badge>
                        <span class="text-[10px] font-semibold text-accent-gold bg-accent-gold/15 px-2 py-0.5 rounded-full">
                            {{ $trophies->count() }}
                        </span>
                    </x-slot:badge>

                    <div class="divide-y divide-border-default">
                        @foreach($trophies as $trophy)
                            @php
                                $typeConfig = match($trophy->trophy_type) {
                                    'league' => ['label' => __('profile.league_title'), 'color' => 'text-accent-gold', 'bg' => 'bg-accent-gold/15'],
                                    'cup' => ['label' => __('profile.cup_title'), 'color' => 'text-accent-blue', 'bg' => 'bg-accent-blue/15'],
                                    'european' => ['label' => __('profile.european_title'), 'color' => 'text-accent-green', 'bg' => 'bg-accent-green/15'],
                                    'supercup' => ['label' => __('profile.supercup_title'), 'color' => 'text-accent-orange', 'bg' => 'bg-accent-orange/15'],
                                    default => ['label' => $trophy->trophy_type, 'color' => 'text-text-muted', 'bg' => 'bg-surface-700'],
                                };
                            @endphp
                            <div class="px-5 py-3 flex items-center gap-3">
                                {{-- Trophy icon --}}
                                <div class="w-8 h-8 rounded-lg {{ $typeConfig['bg'] }} flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4 {{ $typeConfig['color'] }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a.75.75 0 0 0 0 1.5h12.17a.75.75 0 0 0 0-1.5h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.707 6.707 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 3.246 2.632 5.88 5.834 5.88 3.203 0 5.834-2.634 5.834-5.88V3.357a47.62 47.62 0 0 0-5.834-.357c-1.993 0-3.948.119-5.834.357v1.893Z" clip-rule="evenodd" />
                                    </svg>
                                </div>

                                {{-- Trophy details --}}
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-text-primary truncate">{{ __($trophy->competition->name) }}</p>
                                    <p class="text-xs text-text-muted truncate">{{ $trophy->team->name }} · {{ $trophy->season }}</p>
                                </div>

                                {{-- Type badge --}}
                                <span class="text-[10px] font-semibold uppercase tracking-wider {{ $typeConfig['color'] }} {{ $typeConfig['bg'] }} px-2 py-0.5 rounded-full shrink-0">
                                    {{ $typeConfig['label'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </x-section-card>
            @endif

            {{-- Games --}}
            <x-section-card :title="__('profile.games')">
                @if($user->games->isEmpty())
                    <div class="p-5 text-center">
                        <p class="text-sm text-text-muted">{{ __('profile.no_games') }}</p>
                    </div>
                @else
                    <div class="divide-y divide-border-default">
                        @foreach($user->games as $game)
                            <div class="px-5 py-3 flex items-center gap-3">
                                @if($game->team && $game->team->image)
                                    <img src="{{ $game->team->image }}" alt="{{ $game->team->name }}" class="w-8 h-8 shrink-0 object-contain">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-surface-700 flex items-center justify-center shrink-0">
                                        <span class="text-xs text-text-muted">?</span>
                                    </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-text-primary truncate">{{ $game->team?->name ?? '—' }}</p>
                                    <p class="text-xs text-text-muted truncate">{{ $game->competition?->name ?? '—' }} · {{ $game->season }}</p>
                                </div>

                                @if($game->game_mode === 'tournament')
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-2 py-0.5 rounded-full shrink-0">
                                        {{ __('game.tournament') }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-section-card>
        </div>
    </div>
</x-app-layout>
