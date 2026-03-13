@php
    $comp = $nextMatch->competition;
    $isPreseason = \App\Support\CompetitionColors::category($comp) === 'preseason';

    $cupTie = $nextMatch->cup_tie_id ? $nextMatch->cupTie : null;
    $firstLegScore = null;
    if ($cupTie && $cupTie->first_leg_match_id && $cupTie->firstLegMatch?->played) {
        $fl = $cupTie->firstLegMatch;
        $firstLegScore = $fl->home_score . ' - ' . $fl->away_score;
    }
@endphp
<div class="rounded-xl overflow-hidden border border-border-strong bg-surface-800">
    {{-- Competition & Match Info --}}
    <div class="px-4 pt-4 md:px-6 md:pt-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <x-competition-pill :competition="$comp" :round-name="$nextMatch->round_name" :round-number="$nextMatch->round_number" />
            <span class="text-xs text-text-muted truncate">
                {{ $nextMatch->homeTeam->stadium_name ?? '' }} &middot; {{ $nextMatch->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
            </span>
        </div>
    </div>

    {{-- First Leg Score (cup ties) --}}
    @if($firstLegScore)
        <div class="px-4 pt-3 md:px-6 text-center">
            <span class="text-xs text-text-muted font-medium">1st leg: {{ $firstLegScore }}</span>
        </div>
    @endif

    {{-- Team Face-Off --}}
    <div class="px-4 py-5 md:px-6 md:py-6">
        <div class="flex items-start justify-center gap-3 md:gap-6">
            {{-- Home Team --}}
            <div class="flex-1 flex flex-col items-center text-center min-w-0">
                <x-team-crest :team="$nextMatch->homeTeam" class="w-14 h-14 md:w-20 md:h-20 mb-2" />
                <h4 class="text-sm md:text-xl font-bold text-text-primary truncate max-w-full">{{ $nextMatch->homeTeam->name }}</h4>
                @if(!$isPreseason)
                    @if($homeStanding)
                    <div class="text-xs text-text-muted mt-1.5">
                        {{ $homeStanding->position }}{{ $homeStanding->position == 1 ? 'st' : ($homeStanding->position == 2 ? 'nd' : ($homeStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $homeStanding->points }} {{ __('game.pts') }}
                    </div>
                    @endif
                    <div class="flex gap-1 mt-2">
                        @php $homeForm = $nextMatch->home_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                        @forelse($homeForm as $result)
                            <span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center
                                @if($result === 'W') bg-accent-green text-white
                                @elseif($result === 'D') bg-slate-500 text-white
                                @else bg-accent-red text-white @endif">
                                {{ $result }}
                            </span>
                        @empty
                            <span class="text-text-secondary text-xs">{{ __('game.no_form') }}</span>
                        @endforelse
                    </div>
                @endif
            </div>

            {{-- VS Divider --}}
            <div class="flex flex-col items-center justify-center pt-4 md:pt-6 shrink-0">
                <span class="text-lg md:text-2xl font-black text-text-body tracking-tight">{{ __('game.vs') }}</span>
            </div>

            {{-- Away Team --}}
            <div class="flex-1 flex flex-col items-center text-center min-w-0">
                <x-team-crest :team="$nextMatch->awayTeam" class="w-14 h-14 md:w-20 md:h-20 mb-2" />
                <h4 class="text-sm md:text-xl font-bold text-text-primary truncate max-w-full">{{ $nextMatch->awayTeam->name }}</h4>
                @if(!$isPreseason)
                    @if($awayStanding)
                    <div class="text-xs text-text-muted mt-1.5">
                        {{ $awayStanding->position }}{{ $awayStanding->position == 1 ? 'st' : ($awayStanding->position == 2 ? 'nd' : ($awayStanding->position == 3 ? 'rd' : 'th')) }} &middot; {{ $awayStanding->points }} {{ __('game.pts') }}
                    </div>
                    @endif
                    <div class="flex gap-1 mt-2">
                        @php $awayForm = $nextMatch->away_team_id === $game->team_id ? $playerForm : $opponentForm; @endphp
                        @forelse($awayForm as $result)
                            <span class="w-5 h-5 rounded-sm text-[10px] font-bold flex items-center justify-center
                                @if($result === 'W') bg-accent-green text-white
                                @elseif($result === 'D') bg-slate-500 text-white
                                @else bg-accent-red text-white @endif">
                                {{ $result }}
                            </span>
                        @empty
                            <span class="text-text-secondary text-xs">{{ __('game.no_form') }}</span>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>
