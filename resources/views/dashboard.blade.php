<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary leading-tight text-center">
            {{ __('app.load_game') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                <div class="flex items-center gap-3">
                    <h3 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('game.your_games') }}</h3>
                    <span class="text-sm text-text-secondary">{{ __('game.game_slots_used', ['count' => $gameCount, 'max' => $maxGames]) }}</span>
                </div>
                @if($canCreateGame)
                    <a href="{{ route('select-team') }}" class="inline-flex items-center justify-center px-4 py-2 min-h-[44px] sm:min-h-0 text-sm bg-surface-700 border border-border-strong font-semibold text-text-body shadow-xs hover:bg-surface-600 hover:text-text-primary rounded-lg focus:outline-hidden focus:ring-2 focus:ring-accent-blue focus:ring-offset-2 focus:ring-offset-surface-900 transition ease-in-out duration-150">+ {{ __('app.new_game') }}</a>
                @endif
            </div>
        </div>

        @if($errors->has('limit'))
            <x-flash-message type="error" class="mb-4">{{ $errors->first('limit') }}</x-flash-message>
        @endif

        <x-flash-message type="success" :message="session('success')" class="mb-4" />

        <ul role="list" class="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3">
            @foreach($games as $game)
                <li class="col-span-1 flex flex-col rounded-lg bg-surface-800 text-center shadow-sm border border-border-default" x-data="{ confirmDelete: false }">
                    <div class="flex flex-1 flex-col p-8 space-y-3" x-show="!confirmDelete">
                        @if($game->isTournamentMode())
                            <div class="min-h-20 content-center">
                            <x-team-crest :team="$game->team" class="rounded-md object-cover mx-auto h-16 w-20 shrink-0" />
                            </div>
                        @else
                            <x-team-crest :team="$game->team" class="object-cover mx-auto h-20 w-20 shrink-0" />
                        @endif
                        <h3 class="text-xl font-semibold leading-tight text-text-primary">{{ $game->team->name }}</h3>
                        <dl class="flex flex-col justify-between">
                            @if($game->isTournamentMode())
                                <dd class="mb-1">
                                    <span class="inline-flex items-center rounded-full bg-accent-gold/10 px-2.5 py-0.5 text-xs font-medium text-accent-gold ring-1 ring-inset ring-amber-600/20">
                                        {{ __('game.mode_tournament_badge') }}
                                    </span>
                                </dd>
                            @elseif($game->isCareerMode())
                            <dd class="mb-1">
                                <span class="inline-flex items-center rounded-full bg-accent-gold/10 px-2.5 py-0.5 text-xs font-medium text-accent-gold ring-1 ring-inset ring-amber-600/20">
                                    {{ __('game.mode_career') }}
                                </span>
                            </dd>
                            @endif

                            <hr class="pt-4 mt-4 border-t border-border-default">

                            @if($game->current_date)
                                <dd class="mt-2 mb-2">
                                    <span class="inline-flex items-center rounded-full bg-accent-green/10 px-2 py-1 text-xs font-medium text-accent-green ring-1 ring-inset ring-green-600/20">
                                        {{ __('game.matchday_n', ['number' => $game->current_matchday + 1]) }} - {{ $game->current_date->format('d/m/Y') }}
                                    </span>
                                </dd>
                            @endif
                            <dd class="text-xs text-text-secondary">
                                {{ __('game.last_played', ['time' => $game->updated_at->diffForHumans()]) }}
                            </dd>
                        </dl>
                        <div class="flex items-center justify-center gap-3">
                            <x-primary-button class="text-md p-0!">
                                <a class="inline-flex px-4 py-2" href="{{ route('show-game', $game->id) }}">{{ __('app.continue') }}</a>
                            </x-primary-button>
                            <x-icon-button
                                @click="confirmDelete = true"
                                class="hover:text-accent-red hover:bg-accent-red/10"
                                title="{{ __('game.delete_game') }}"
                            >
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </x-icon-button>
                        </div>
                    </div>

                    {{-- Confirmation overlay --}}
                    <div class="flex flex-1 flex-col items-center justify-center p-8 space-y-4" x-show="confirmDelete" x-cloak>
                        <svg class="w-10 h-10 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <p class="text-sm text-text-secondary text-center">{{ __('game.confirm_delete_game') }}</p>
                        <div class="flex gap-3">
                            <x-secondary-button type="button" @click="confirmDelete = false">
                                {{ __('app.cancel') }}
                            </x-secondary-button>
                            <form method="POST" action="{{ route('game.destroy', $game->id) }}">
                                @csrf
                                @method('DELETE')
                                <x-danger-button type="submit">
                                    {{ __('game.delete_game') }}
                                </x-danger-button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>


        {{-- Tournament History --}}
        @if($tournamentHistory->isNotEmpty())
        <div class="mt-10">
            <h3 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-6">{{ __('game.tournament_history') }}</h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                @foreach($tournamentHistory as $summary)
                @php
                    $resultBadgeClass = match($summary->result_label) {
                        'champion'          => 'bg-accent-gold/20 text-accent-gold border-accent-gold/20',
                        'runner_up'         => 'bg-surface-600 text-text-body border-border-default',
                        'third_place'       => 'bg-accent-orange/10 text-accent-orange border-accent-orange/20',
                        'semi_finalist'     => 'bg-accent-blue/10 text-blue-400 border-accent-blue/20',
                        'quarter_finalist'  => 'bg-accent-blue/10 text-accent-blue border-accent-blue/20',
                        default             => 'bg-surface-700 text-text-secondary border-border-default',
                    };
                @endphp
                <a href="{{ route('tournament-summary.show', $summary->id) }}" class="block group">
                    <div class="rounded-lg bg-surface-800 border border-border-default p-4 transition hover:border-accent-blue/50 hover:bg-surface-700">
                        <div class="flex items-center gap-3">
                            <x-team-crest :team="$summary->team" class="w-10 h-10 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-text-primary text-sm truncate">{{ $summary->team->name }}</div>
                                <div class="text-xs text-text-secondary truncate">{{ __($summary->competition->name ?? 'game.wc2026_name') }}</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-border-default">
                            <span class="inline-block px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-full border {{ $resultBadgeClass }}">
                                {{ __('season.result_' . $summary->result_label) }}
                            </span>
                            <span class="text-[10px] text-text-muted">{{ $summary->tournament_date->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Manager Profile --}}
        <div class="mt-10">
            <h3 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary mb-6">{{ __('profile.your_manager_profile') }}</h3>

            @if($user->username)
                <div class="flex items-center gap-4 rounded-lg bg-surface-800 border border-border-default p-5">
                    <div class="size-14 rounded-full overflow-hidden shrink-0 flex items-start justify-center">
                        <img src="{{ $user->getAvatarUrl() }}" alt="{{ $user->username }}" class="size-20 max-w-none -mt-1">
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-heading font-semibold text-text-primary text-base tracking-wide truncate">{{ $user->username }}</p>
                        @if($user->country)
                            <p class="text-sm text-text-secondary truncate">{{ \Locale::getDisplayRegion('und_'.$user->country, app()->getLocale()) }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <a href="{{ route('manager.profile', $user->username) }}" class="text-xs text-text-muted hover:text-accent-blue transition">{{ __('profile.view_profile') }}</a>
                        <a href="{{ route('profile.edit') }}" class="text-xs text-text-muted hover:text-accent-blue transition">{{ __('profile.edit_profile') }}</a>
                    </div>
                </div>
            @else
                <a href="{{ route('profile.edit') }}" class="block group">
                    <div class="flex flex-col items-center gap-3 rounded-lg bg-surface-800 border border-dashed border-border-strong p-8 transition hover:border-accent-blue/50">
                        <div class="w-14 h-14 rounded-full bg-surface-700 flex items-center justify-center">
                            <svg class="w-7 h-7 text-text-muted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </div>
                        <p class="text-sm text-text-secondary">{{ __('profile.no_profile_yet') }}</p>
                        <span class="text-sm font-semibold text-accent-blue group-hover:underline">{{ __('profile.create_profile') }}</span>
                    </div>
                </a>
            @endif
        </div>
    </div>
</x-app-layout>
