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
