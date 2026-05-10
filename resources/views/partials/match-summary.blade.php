@php
    /** @var \App\Models\GameMatch $match */
    use App\Modules\Match\DTOs\MatchSummaryViewModel as Summary;
    use App\Modules\Match\Services\MatchSummaryPresenter;

    // Optional props (set by the caller via @include data array).
    $showHeader = $showHeader ?? false;
    $viewerTeamId = $viewerTeamId ?? null;
    $ratings = $ratings ?? null;

    $summary = app(MatchSummaryPresenter::class)->present(
        $match,
        $ratings,
        $showHeader ? $viewerTeamId : null,
    );

    $hasPossession = $match->home_possession !== null && $match->away_possession !== null;
@endphp

<div x-data="{ tab: 'summary' }" class="bg-surface-800 rounded-xl border border-border-default overflow-hidden">
    {{-- Optional header bar: "LAST RESULT · WIN · Competition" --}}
    @if($showHeader)
        <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-border-default bg-surface-800/60">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-[10px] text-text-faint uppercase tracking-widest">{{ __('game.last_result') }}</span>
                @if($summary->resultLabel)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border {{ $summary->resultBg }} {{ $summary->resultColor }}">
                        {{ $summary->resultLabel }}
                    </span>
                @endif
            </div>
            <x-competition-pill :competition="$match->competition" :round-name="$match->round_name" :round-number="$match->round_number" :short="true" class="scale-90 origin-right" />
        </div>
    @endif

    {{-- Score + meta --}}
    <div class="px-4 py-4 md:px-6">
        <div class="flex items-center justify-center gap-3 md:gap-5">
            <div class="flex items-center gap-2 flex-1 justify-end min-w-0">
                <span class="text-sm md:text-lg font-semibold text-text-primary truncate">{{ $match->homeTeam->short_name ?? $match->homeTeam->name }}</span>
                <x-team-crest :team="$match->homeTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
            </div>
            <div class="shrink-0 flex flex-col items-center gap-1">
                <div class="font-heading text-2xl md:text-4xl font-extrabold text-text-primary tabular-nums px-2">
                    {{ $summary->homeTotal }}<span class="text-text-muted mx-1">-</span>{{ $summary->awayTotal }}
                </div>
                @if($match->is_extra_time || $summary->hasPenalties)
                    <div class="text-[9px] md:text-[10px] text-text-muted uppercase tracking-widest tabular-nums">
                        @if($summary->hasPenalties)
                            {{ __('season.aet_abbr') }} &middot; {{ __('season.pens_abbr') }} {{ $match->home_score_penalties }}-{{ $match->away_score_penalties }}
                        @else
                            {{ __('season.aet_abbr') }}
                        @endif
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-1 min-w-0">
                <x-team-crest :team="$match->awayTeam" class="w-8 h-8 md:w-10 md:h-10 shrink-0" />
                <span class="text-sm md:text-lg font-semibold text-text-primary truncate">{{ $match->awayTeam->short_name ?? $match->awayTeam->name }}</span>
            </div>
        </div>
    </div>

    {{-- Tab strip --}}
    @if($summary->hasLineup() || $summary->hasPitch() || $summary->hasScorers() || $match->mvpPlayer)
        <div class="border-t border-border-default flex items-center gap-1 px-2 md:px-4">
            @php
                $tabs = [['id' => 'summary', 'label' => __('game.tab_summary')]];
                if ($summary->hasPitch()) $tabs[] = ['id' => 'pitch', 'label' => __('squad.pitch')];
                if ($summary->hasLineup()) $tabs[] = ['id' => 'players', 'label' => __('game.tab_players')];
            @endphp
            @foreach($tabs as $t)
                <button type="button"
                    @click="tab = @js($t['id'])"
                    :class="tab === @js($t['id']) ? 'border-accent-blue text-accent-blue' : 'border-transparent text-text-muted hover:text-text-body'"
                    class="px-3 py-2 text-[11px] font-semibold uppercase tracking-wider border-b-2 transition-colors">
                    {{ $t['label'] }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Tab: Summary (scorers + MVP + possession) --}}
    <div x-show="tab === 'summary'" class="px-4 py-4 md:px-6">
        @if($summary->hasScorers())
            <div class="grid grid-cols-2 gap-4 md:gap-8 text-xs">
                <div class="text-right space-y-0.5">
                    @foreach($summary->homeScorers as $g)
                        <div class="text-text-body inline-flex items-center justify-end gap-1 flex-wrap w-full">
                            <span class="text-text-muted tabular-nums">{{ $g['minute'] }}'</span>
                            @if($g['assistName'])
                                <span class="text-text-muted">{{ __('game.live_assist') }} {{ $g['assistName'] }} &middot;</span>
                            @endif
                            <span class="font-medium">@if($g['isOwnGoal'])<span class="text-accent-red font-normal">({{ __('game.og') }})</span> @endif{{ $g['name'] }}</span>
                            <svg class="w-2.5 h-2.5 shrink-0 {{ $g['isOwnGoal'] ? 'text-accent-red' : 'text-accent-green' }}" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                        </div>
                    @endforeach
                </div>
                <div class="text-left space-y-0.5">
                    @foreach($summary->awayScorers as $g)
                        <div class="text-text-body inline-flex items-center gap-1 flex-wrap w-full">
                            <svg class="w-2.5 h-2.5 shrink-0 {{ $g['isOwnGoal'] ? 'text-accent-red' : 'text-accent-green' }}" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                            <span class="font-medium">{{ $g['name'] }}@if($g['isOwnGoal']) <span class="text-accent-red font-normal">({{ __('game.og') }})</span>@endif</span>
                            @if($g['assistName'])
                                <span class="text-text-muted">&middot; {{ __('game.live_assist') }} {{ $g['assistName'] }}</span>
                            @endif
                            <span class="text-text-muted tabular-nums">{{ $g['minute'] }}'</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($match->mvpPlayer)
            <div class="{{ $summary->hasScorers() ? 'mt-3 pt-3 border-t border-border-default' : '' }} flex items-center justify-center gap-2 text-xs">
                <span class="text-accent-gold">&#9733;</span>
                <span class="uppercase tracking-wider text-text-muted font-semibold">{{ __('game.mvp') }}</span>
                <span class="font-semibold text-text-primary">{{ $match->mvpPlayer->name }}</span>
                <span class="text-text-muted">·</span>
                <span class="text-text-secondary">{{ $match->mvpPlayer->team_id === $match->home_team_id ? $match->homeTeam->name : $match->awayTeam->name }}</span>
            </div>
        @endif

        @if($hasPossession)
            <div class="mt-4 pt-3 border-t border-border-default">
                <div class="flex items-center justify-between text-[10px] uppercase tracking-wider text-text-muted mb-1">
                    <span>{{ __('game.possession') }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs font-semibold tabular-nums">
                    <span class="text-text-body shrink-0 w-8 text-right">{{ $match->home_possession }}%</span>
                    <div class="flex-1 h-2 rounded-full overflow-hidden flex">
                        <div class="bg-accent-blue" style="width: {{ $match->home_possession }}%"></div>
                        <div class="bg-surface-600" style="width: {{ $match->away_possession }}%"></div>
                    </div>
                    <span class="text-text-body shrink-0 w-8">{{ $match->away_possession }}%</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Tab: Pitch --}}
    @if($summary->hasPitch())
        <div x-show="tab === 'pitch'" x-cloak class="px-3 py-3 md:px-5 md:py-4">
            <div class="pitch aspect-3/4 w-full max-w-md mx-auto relative">
                <div class="absolute inset-x-[4%] inset-y-[3%]">
                    <div class="absolute inset-0 border border-pitch-line pointer-events-none"></div>
                    <div class="pitch-center-line"></div>
                    <div class="pitch-center-circle"></div>
                    <div class="pitch-box-top"></div>
                    <div class="pitch-box-bottom"></div>
                    <div class="pitch-six-top"></div>
                    <div class="pitch-six-bottom"></div>
                    <div class="pitch-arc-top"></div>
                    <div class="pitch-arc-bottom"></div>
                    <div class="pitch-penalty-spot-top"></div>
                    <div class="pitch-penalty-spot-bottom"></div>

                    @foreach($summary->pitchEntries as $entry)
                        @php
                            $card = $entry['card'];
                            $pid = $card['id'];
                            $rating = $card['rating'];
                            $goals = $summary->goalsByPlayer[$pid] ?? 0;
                            $ownGoals = $summary->ownGoalsByPlayer[$pid] ?? 0;
                            $hasYellow = !empty($summary->yellowsByPlayer[$pid]);
                            $hasRed = !empty($summary->redsByPlayer[$pid]);
                            $sub = $summary->subsByOutId[$pid] ?? null;
                        @endphp
                        <div class="absolute transform -translate-x-1/2 -translate-y-1/2 flex flex-col items-center"
                             style="left: {{ $entry['xPct'] }}%; top: {{ $entry['yPct'] }}%;">
                            <div class="relative w-9 h-9 md:w-10 md:h-10 rounded-xl shadow-lg border border-white/20" style="{{ $entry['shirtStyle'] }}">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="font-bold text-[11px] leading-none inline-flex items-center justify-center w-6 h-6 rounded-full" style="{{ $entry['numberStyle'] }}">
                                        {{ $card['number'] ?? '' }}
                                    </span>
                                </div>
                                {{-- Rating badge top-right --}}
                                @if($rating !== null)
                                    <span class="absolute -top-1.5 -right-1.5 min-w-[20px] h-[16px] px-0.5 rounded-sm text-[9px] font-bold leading-none flex items-center justify-center shadow-sm tabular-nums {{ Summary::ratingClass($rating) }}">{{ number_format($rating, 1) }}</span>
                                @endif
                                {{-- Event indicators top-left --}}
                                @if($goals > 0 || $ownGoals > 0 || $hasYellow || $hasRed || $sub)
                                    <span class="absolute -top-1.5 -left-1.5 flex items-center gap-px">
                                        @if($goals > 0)
                                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-accent-green text-white shadow-sm text-[8px] font-bold">{{ $goals > 1 ? $goals : '' }}<svg class="w-2 h-2" viewBox="0 0 16 16" fill="currentColor" @if($goals > 1) style="display:none" @endif><circle cx="8" cy="8" r="8"/></svg></span>
                                        @endif
                                        @if($ownGoals > 0)
                                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-accent-red text-white shadow-sm text-[7px] font-bold uppercase">{{ __('game.og') }}</span>
                                        @endif
                                        @if($hasRed)
                                            <span class="w-2 h-2.5 rounded-[1px] bg-accent-red shadow-sm"></span>
                                        @elseif($hasYellow)
                                            <span class="w-2 h-2.5 rounded-[1px] bg-yellow-400 shadow-sm"></span>
                                        @endif
                                        @if($sub)
                                            <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full bg-accent-red/80 text-white shadow-sm">
                                                <svg class="w-2 h-2" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg>
                                            </span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                            <span class="mt-0.5 text-[8px] max-w-[66px] font-semibold text-white uppercase tracking-wide leading-tight text-center line-clamp-2 break-words drop-shadow-[0_1px_2px_rgba(0,0,0,0.8)]">
                                {{ $card['name'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
            {{-- Formation footer --}}
            <div class="mt-3 flex items-center justify-between text-[10px] text-text-muted uppercase tracking-wider px-1">
                <span class="inline-flex items-center gap-1.5">
                    <x-team-crest :team="$match->homeTeam" class="w-3.5 h-3.5" />
                    <span class="tabular-nums">{{ $match->home_formation ?? '' }}</span>
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="tabular-nums">{{ $match->away_formation ?? '' }}</span>
                    <x-team-crest :team="$match->awayTeam" class="w-3.5 h-3.5" />
                </span>
            </div>
        </div>
    @endif

    {{-- Tab: Players (full lineup with subs, events, ratings) --}}
    @if($summary->hasLineup())
        <div x-show="tab === 'players'" x-cloak class="px-3 py-3 md:px-5 md:py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                @foreach([
                    ['side' => 'home', 'roster' => $summary->homeRoster, 'team' => $match->homeTeam, 'formation' => $match->home_formation],
                    ['side' => 'away', 'roster' => $summary->awayRoster, 'team' => $match->awayTeam, 'formation' => $match->away_formation],
                ] as $col)
                    <div>
                        <div class="flex items-center gap-2 mb-2 px-1">
                            <x-team-crest :team="$col['team']" class="w-4 h-4 shrink-0" />
                            <span class="font-heading font-bold text-xs uppercase tracking-wide text-text-primary truncate">{{ $col['team']->name }}</span>
                            @if($col['formation'])
                                <span class="text-[10px] text-text-muted ml-auto tabular-nums">{{ $col['formation'] }}</span>
                            @endif
                        </div>
                        <div class="space-y-px">
                            @foreach($col['roster'] as $p)
                                @php
                                    $pid = $p['id'];
                                    $sub = $summary->subsByOutId[$pid] ?? null;
                                    $rating = $p['rating'];
                                    $goals = $summary->goalsByPlayer[$pid] ?? 0;
                                    $ownGoals = $summary->ownGoalsByPlayer[$pid] ?? 0;
                                    $hasYellow = !empty($summary->yellowsByPlayer[$pid]);
                                    $hasRed = !empty($summary->redsByPlayer[$pid]);
                                @endphp
                                <div class="flex items-center gap-2 px-1 py-1 rounded {{ $sub ? 'opacity-70' : '' }}">
                                    <span class="inline-flex items-center justify-center w-6 h-5 text-[9px] -skew-x-12 font-semibold text-white shrink-0 {{ Summary::positionPillClass($p['positionGroup'] ?? null) }}">
                                        <span class="skew-x-12">{{ $p['positionAbbr'] ?? '' }}</span>
                                    </span>
                                    <span class="text-xs flex-1 truncate {{ $sub ? 'text-text-muted' : 'text-text-body' }}">{{ $p['name'] }}</span>
                                    @if($goals > 0)
                                        <span class="inline-flex items-center gap-px text-accent-green shrink-0">
                                            <svg class="w-2.5 h-2.5" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                                            @if($goals > 1)<span class="text-[9px] font-bold tabular-nums">×{{ $goals }}</span>@endif
                                        </span>
                                    @endif
                                    @if($ownGoals > 0)
                                        <span class="inline-flex items-center gap-px text-accent-red shrink-0" title="{{ __('game.og') }}">
                                            <svg class="w-2.5 h-2.5" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                                            <span class="text-[8px] font-bold uppercase">{{ __('game.og') }}</span>
                                        </span>
                                    @endif
                                    @if($hasYellow)<span class="w-1.5 h-2.5 rounded-[1px] bg-yellow-400 shrink-0"></span>@endif
                                    @if($hasRed)<span class="w-1.5 h-2.5 rounded-[1px] bg-accent-red shrink-0"></span>@endif
                                    @if($sub)
                                        <span class="inline-flex items-center gap-0.5 text-accent-red shrink-0">
                                            <svg class="w-2.5 h-2.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg>
                                            <span class="text-[9px] font-semibold tabular-nums">{{ $sub['minute'] }}'</span>
                                        </span>
                                    @endif
                                    @if($rating !== null)
                                        <span class="inline-flex items-center justify-center min-w-[1.6rem] h-4 px-1 rounded text-[10px] font-semibold shrink-0 tabular-nums {{ Summary::ratingClass($rating) }}">
                                            {{ number_format($rating, 1) }}
                                        </span>
                                    @endif
                                </div>
                                @if($sub && $sub['in'])
                                    @php
                                        $inP = $sub['in'];
                                        $inId = $inP['id'];
                                        $inRating = $inP['rating'];
                                        $inGoals = $summary->goalsByPlayer[$inId] ?? 0;
                                        $inOwn = $summary->ownGoalsByPlayer[$inId] ?? 0;
                                        $inYellow = !empty($summary->yellowsByPlayer[$inId]);
                                        $inRed = !empty($summary->redsByPlayer[$inId]);
                                    @endphp
                                    <div class="flex items-center gap-2 px-1 py-1 pl-7 rounded">
                                        <span class="inline-flex items-center text-accent-green shrink-0">
                                            <svg class="w-2.5 h-2.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg>
                                        </span>
                                        <span class="text-xs flex-1 truncate text-text-body">{{ $inP['name'] }}</span>
                                        @if($inGoals > 0)
                                            <span class="inline-flex items-center gap-px text-accent-green shrink-0">
                                                <svg class="w-2.5 h-2.5" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                                                @if($inGoals > 1)<span class="text-[9px] font-bold tabular-nums">×{{ $inGoals }}</span>@endif
                                            </span>
                                        @endif
                                        @if($inOwn > 0)
                                            <span class="inline-flex items-center gap-px text-accent-red shrink-0" title="{{ __('game.og') }}">
                                                <svg class="w-2.5 h-2.5" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                                                <span class="text-[8px] font-bold uppercase">{{ __('game.og') }}</span>
                                            </span>
                                        @endif
                                        @if($inYellow)<span class="w-1.5 h-2.5 rounded-[1px] bg-yellow-400 shrink-0"></span>@endif
                                        @if($inRed)<span class="w-1.5 h-2.5 rounded-[1px] bg-accent-red shrink-0"></span>@endif
                                        @if($inRating !== null)
                                            <span class="inline-flex items-center justify-center min-w-[1.6rem] h-4 px-1 rounded text-[10px] font-semibold shrink-0 tabular-nums {{ Summary::ratingClass($inRating) }}">
                                                {{ number_format($inRating, 1) }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
