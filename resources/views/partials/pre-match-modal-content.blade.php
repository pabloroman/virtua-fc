{{-- Match Info --}}
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-4">
    @if($game->isTournamentMode())
        <span class="text-xs font-medium text-text-secondary">
            {{ __($match->round_name ?? '') }}
        </span>
    @else
        <x-competition-pill :competition="$match->competition" :round-name="$match->round_name" :round-number="$match->round_number" :short="true" />
    @endif
    <span class="text-xs text-text-muted">
        {{ $match->venueName() ?? '' }}
        @if(!empty($attendance ?? null))
            &middot; {{ __('game.attendance') }}: {{ number_format($attendance) }} ({{ $attendancePercent }}%)
        @endif
        &middot; {{ $match->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
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

{{-- Issue Explanation --}}
<div class="mt-4 p-3 rounded-lg bg-accent-gold/10 border border-accent-gold/20">
    <div class="flex items-start gap-2">
        <svg class="w-5 h-5 text-accent-gold shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
        <div>
            <p class="text-sm font-semibold text-accent-gold">{{ $issueMessage }}</p>
            <p class="text-xs text-accent-gold/80 mt-1">{{ __('messages.pre_match_auto_explanation') }}</p>
        </div>
    </div>
</div>

{{-- Auto-lineup checkbox --}}
<label class="mt-4 flex items-start gap-2 cursor-pointer group">
    <input type="checkbox"
           class="mt-0.5 rounded border-border-strong bg-surface-700 text-accent-blue focus:ring-accent-blue"
           onchange="localStorage.setItem('autoLineup', this.checked ? '1' : '0')">
    <span class="text-xs text-text-secondary group-hover:text-text-body transition-colors">{{ __('messages.pre_match_auto_lineup') }}</span>
</label>

{{-- Action Buttons --}}
<div class="mt-6 flex flex-row items-stretch md:items-center md:justify-end gap-2">
    <a href="{{ route('game.lineup', $game->id) }}"
       class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2 min-h-[44px] text-sm rounded-lg border border-border-strong font-semibold text-text-body uppercase tracking-wider hover:bg-surface-700 transition ease-in-out duration-150">
        {{ __('messages.pre_match_edit_lineup') }}
    </a>
    <form method="post" action="{{ route('game.advance', $game->id) }}" x-data="{ loading: false }" @submit="if (loading) { $event.preventDefault(); return; } loading = true; $dispatch('matchday-advance-starting')" class="flex-1 md:flex-none">
        @csrf
        <x-primary-button-spin color="blue" class="w-full md:w-auto">
            {{ __('messages.pre_match_continue') }}
        </x-primary-button-spin>
    </form>
</div>
