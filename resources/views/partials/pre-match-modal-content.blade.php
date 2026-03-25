{{-- Match Info --}}
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-4">
    <x-competition-pill :competition="$match->competition" :round-name="$match->round_name" :round-number="$match->round_number" :short="true" />
    <span class="text-xs text-text-muted">
        {{ $match->homeTeam->stadium_name ?? '' }} &middot; {{ $match->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
    </span>
</div>

{{-- Team Face-Off (compact) --}}
<div class="flex items-center justify-center gap-4 py-3">
    <div class="flex flex-col items-center text-center min-w-0 flex-1">
        <x-team-crest :team="$match->homeTeam" class="w-10 h-10 md:w-12 md:h-12 mb-1" />
        <span class="text-xs md:text-sm font-bold text-text-primary truncate max-w-full">{{ $match->homeTeam->name }}</span>
    </div>
    <span class="text-base font-black text-text-body shrink-0">{{ __('game.vs') }}</span>
    <div class="flex flex-col items-center text-center min-w-0 flex-1">
        <x-team-crest :team="$match->awayTeam" class="w-10 h-10 md:w-12 md:h-12 mb-1" />
        <span class="text-xs md:text-sm font-bold text-text-primary truncate max-w-full">{{ $match->awayTeam->name }}</span>
    </div>
</div>

{{-- Warning Banner --}}
@if($hasIssues)
<div class="mt-4 p-3 rounded-lg bg-accent-gold/10 border border-accent-gold/20">
    <div class="flex items-start gap-2">
        <svg class="w-5 h-5 text-accent-gold shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
        <div>
            <p class="text-sm font-semibold text-accent-gold">{{ __('messages.pre_match_warning_title') }}</p>
            <ul class="mt-1 space-y-0.5">
                @foreach($issues as $issue)
                <li class="text-xs text-accent-gold/80">{{ $issue['message'] }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endif

{{-- Tactics Summary --}}
<div class="mt-4 flex flex-wrap items-center gap-2">
    <span class="px-2 py-1 rounded-md bg-surface-700 text-xs font-medium text-text-body">{{ $formationLabel }}</span>
    <span class="px-2 py-1 rounded-md bg-surface-700 text-xs font-medium text-text-body">{{ $mentalityLabel }}</span>
    <span class="px-2 py-1 rounded-md bg-surface-700 text-xs font-medium text-text-body">{{ $playingStyleLabel }}</span>
    <span class="px-2 py-1 rounded-md bg-surface-700 text-xs font-medium text-text-body">{{ $pressingLabel }}</span>
    <span class="px-2 py-1 rounded-md bg-surface-700 text-xs font-medium text-text-body">{{ $defensiveLineLabel }}</span>
</div>

{{-- Starting XI --}}
<div class="mt-4">
    <h4 class="text-xs font-semibold text-text-muted uppercase tracking-wider mb-2">{{ __('messages.pre_match_starting_xi') }}</h4>

    @if($lineupPlayers->isEmpty())
    <div class="py-6 text-center">
        <p class="text-sm text-text-muted">{{ __('messages.pre_match_no_lineup_set') }}</p>
    </div>
    @else
    <div class="divide-y divide-border-default">
        @foreach($lineupPlayers as $player)
        @php $isUnavailable = in_array($player->id, $unavailablePlayerIds); @endphp
        <div class="flex items-center gap-2 py-2 px-1 {{ $isUnavailable ? 'bg-accent-red/10 rounded-md' : '' }}">
            <x-position-badge :position="$player->position" size="sm" />
            <span class="text-sm text-text-body flex-1 truncate {{ $isUnavailable ? 'line-through opacity-60' : '' }}">
                {{ $player->name }}
            </span>
            @if($isUnavailable)
            <svg class="w-4 h-4 text-accent-red shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            @endif
            <span class="text-xs font-semibold {{ $player->overall_score >= 75 ? 'text-accent-green' : ($player->overall_score >= 65 ? 'text-text-body' : 'text-accent-gold') }}">
                {{ $player->overall_score }}
            </span>
            {{-- Fitness bar --}}
            <div class="w-12 h-1.5 rounded-full bg-surface-600 overflow-hidden shrink-0">
                <div class="h-full rounded-full {{ $player->fitness >= 80 ? 'bg-accent-green' : ($player->fitness >= 60 ? 'bg-accent-gold' : 'bg-accent-red') }}"
                     style="width: {{ $player->fitness }}%"></div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- Action Buttons --}}
<div class="mt-6 flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-end gap-2">
    <a href="{{ route('game.lineup', $game->id) }}"
       class="inline-flex items-center justify-center px-4 py-2 min-h-[44px] text-sm rounded-lg border border-border-strong font-semibold text-text-body uppercase tracking-wider hover:bg-surface-700 transition ease-in-out duration-150">
        {{ __('messages.pre_match_edit_lineup') }}
    </a>
    <form method="post" action="{{ route('game.advance', $game->id) }}" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <x-primary-button-spin color="{{ $hasIssues ? 'amber' : 'blue' }}">
            {{ $hasIssues ? __('messages.pre_match_play_anyway') : __('messages.pre_match_play') }}
        </x-primary-button-spin>
    </form>
</div>
