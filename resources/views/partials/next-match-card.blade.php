@php
    $comp = $nextMatch->competition;
    $isPreseason = \App\Support\CompetitionColors::category($comp) === 'preseason';

    $cupTie = $nextMatch->cup_tie_id ? $nextMatch->cupTie : null;
    $firstLegScore = null;
    if ($cupTie && $cupTie->first_leg_match_id && $cupTie->firstLegMatch?->played) {
        $fl = $cupTie->firstLegMatch;
        $firstLegScore = $fl->home_score . ' - ' . $fl->away_score;
    }

    $userIsHome = $nextMatch->home_team_id === $game->team_id;
    $homeForm = $userIsHome ? $playerForm : $opponentForm;
    $awayForm = $userIsHome ? $opponentForm : $playerForm;

    $showStandings = !$isPreseason && !$cupTie;
    $showForm = !$isPreseason;
@endphp
<div class="rounded-xl overflow-hidden border border-border-strong bg-surface-800">
    {{-- Competition & Match Info --}}
    <div class="px-4 py-3 md:px-6 md:py-4 border-b border-border-default">
        <x-match-card-header :match="$nextMatch" :tournament-mode="$game->isTournamentMode()" />
    </div>

    {{-- Team Face-Off --}}
    <div class="px-4 py-5 md:px-6 md:py-6">
        <div class="flex items-start justify-center gap-3 md:gap-6">
            @include('partials.next-match-team-block', [
                'team' => $nextMatch->homeTeam,
                'standing' => $homeStanding,
                'form' => $homeForm,
                'showStanding' => $showStandings,
                'showForm' => $showForm,
            ])

            <div class="flex flex-col items-center justify-center pt-4 md:pt-6 shrink-0">
                <span class="text-base md:text-xl font-semibold text-text-body tracking-tight">{{ __('game.vs') }}</span>
                @if($firstLegScore)
                    <div class="mt-2 md:mt-3 text-center">
                        <div class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">{{ __('cup.first_leg') }}</div>
                        <div class="text-sm md:text-base font-heading font-bold text-text-secondary tabular-nums whitespace-nowrap leading-none mt-1">{{ $firstLegScore }}</div>
                    </div>
                @endif
            </div>

            @include('partials.next-match-team-block', [
                'team' => $nextMatch->awayTeam,
                'standing' => $awayStanding,
                'form' => $awayForm,
                'showStanding' => $showStandings,
                'showForm' => $showForm,
            ])
        </div>
    </div>

    {{-- Pre-Match Narrative --}}
    @if(!empty($narratives))
        <div class="px-4 pb-4 md:px-6 md:pb-5">
            <div class="border-t border-border-default pt-3">
                <div class="flex items-center gap-1.5 mb-2">
                    <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                    </svg>
                    <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wide">{{ __('game.match_preview') }}</span>
                </div>
                <div class="space-y-1.5">
                    @foreach($narratives as $narrative)
                        <p class="text-xs text-text-secondary leading-relaxed">{{ $narrative->text }}</p>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Pre-Match Actions --}}
    <div class="px-4 pb-4 md:px-6 md:pb-5 pt-3 border-t border-border-default">
        <div class="grid grid-cols-2 gap-2">
            <x-primary-button-link :href="route('game.lineup', $game->id)" size="sm" class="w-full gap-1.5">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                {{ __('app.starting_xi') }}
            </x-primary-button-link>

            <x-secondary-button-link :href="route('game.opponent-analysis', $game->id)" size="sm" class="w-full gap-1.5">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15.75 15.75l-2.489-2.489m0 0a3.375 3.375 0 10-4.773-4.773 3.375 3.375 0 004.774 4.774zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('app.scout_opponent') }}
            </x-secondary-button-link>
        </div>
    </div>
</div>
