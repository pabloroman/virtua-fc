@php
    /** @var App\Models\Game $game */
    /** @var App\Models\GameMatch $match */
    /** @var App\Models\Team $opponent */
    /** @var array{pattern: string, primary: string, secondary: string, number: string}|null $opponentColors */

    $inModal = $inModal ?? false;
    $opponentColors = $opponentColors ?? null;
    $coachTips = $coachTips ?? [];

    $oppAvg = $opponentData['teamAverage'] ?: 0;
    $advantage = $userTeamAverage - $oppAvg;

    $mentalityClass = match($opponentData['mentality']) {
        'defensive' => 'text-accent-blue',
        'attacking' => 'text-accent-red',
        default => 'text-text-secondary',
    };
@endphp

@unless($inModal)
    {{-- Page heading (page-only; modal supplies its own header) --}}
    <div class="mt-6 mb-5">
        <p class="text-[10px] font-semibold text-text-muted uppercase tracking-widest mb-1">{{ __('opponent.eyebrow') }}</p>
        <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('opponent.title', ['team' => $opponent->name]) }}</h2>
        <p class="text-sm text-text-secondary mt-1">{{ __('opponent.subtitle') }}</p>
    </div>
@endunless

@unless($inModal)
    {{-- Match meta strip (page-only; the modal already lives inside the
         pre-match flow and doesn't need to repeat the fixture metadata). --}}
    <div class="rounded-xl border border-border-default bg-surface-800 px-4 py-3 mb-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2">
        <x-competition-pill :competition="$match->competition" :round-name="$match->round_name" :round-number="$match->round_number" />
        <span class="text-xs text-text-muted">
            {{ $match->venueName() ?? '' }} &middot; {{ $match->scheduled_date->locale(app()->getLocale())->translatedFormat('d M Y') }}
        </span>
    </div>
@endunless

{{-- Tale of the tape --}}
@php
    $sideLayout = 'flex items-center gap-3 min-w-0';
    $formPill = 'w-4 h-4 rounded-xs text-[9px] font-bold flex items-center justify-center';
@endphp
<div class="rounded-xl border border-border-default bg-surface-800 px-3 py-3 md:px-4 md:py-3 mb-5">
    <div class="flex items-center justify-between gap-3">
        {{-- User --}}
        <div class="flex-1 {{ $sideLayout }}">
            <x-team-crest :team="$game->team" class="w-10 h-10 md:w-11 md:h-11 shrink-0" />
            <div class="min-w-0 flex-1">
                <div class="flex items-baseline gap-2 min-w-0">
                    <h4 class="text-sm font-bold text-text-primary truncate">{{ $game->team->short_name ?? $game->team->name }}</h4>
                    <span class="font-heading text-xl md:text-2xl font-black text-text-primary tabular-nums leading-none ml-auto shrink-0">{{ $userTeamAverage ?: '-' }}</span>
                </div>
                <div class="flex items-center gap-2 mt-1 text-[10px] text-text-muted min-w-0">
                    @if($userStanding)
                        <span class="tabular-nums shrink-0">{{ $userStanding->position }} · {{ $userStanding->points }} {{ __('game.pts') }}</span>
                    @endif
                    <div class="flex gap-0.5 shrink-0">
                        @forelse($playerForm as $result)
                            <span class="{{ $formPill }}
                                @if($result === 'W') bg-accent-green text-white
                                @elseif($result === 'D') bg-surface-600 text-text-body
                                @else bg-accent-red text-white @endif">{{ $result }}</span>
                        @empty
                            <span>—</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Centre divider: VS + advantage --}}
        <div class="flex flex-col items-center justify-center shrink-0">
            <span class="text-sm md:text-base font-black text-text-faint tracking-tight leading-none">{{ __('game.vs') }}</span>
            @if($userTeamAverage && $oppAvg)
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full whitespace-nowrap mt-1 leading-none
                    @if($advantage > 0) bg-accent-green/10 text-accent-green
                    @elseif($advantage < 0) bg-accent-red/10 text-accent-red
                    @else bg-surface-700 text-text-secondary @endif">
                    {{ $advantage > 0 ? '+' . $advantage : ($advantage < 0 ? $advantage : '=') }}
                </span>
            @endif
        </div>

        {{-- Opponent (mirror layout: rating on the inner side, crest on the outer) --}}
        <div class="flex-1 {{ $sideLayout }} flex-row-reverse text-right">
            <x-team-crest :team="$opponent" class="w-10 h-10 md:w-11 md:h-11 shrink-0" />
            <div class="min-w-0 flex-1">
                <div class="flex items-baseline gap-2 min-w-0">
                    <span class="font-heading text-xl md:text-2xl font-black text-text-primary tabular-nums leading-none shrink-0">{{ $oppAvg ?: '-' }}</span>
                    <h4 class="text-sm font-bold text-text-primary truncate ml-auto">{{ $opponent->short_name ?? $opponent->name }}</h4>
                </div>
                <div class="flex items-center justify-end gap-2 mt-1 text-[10px] text-text-muted min-w-0">
                    <div class="flex gap-0.5 shrink-0">
                        @forelse($opponentData['form'] as $result)
                            <span class="{{ $formPill }}
                                @if($result === 'W') bg-accent-green text-white
                                @elseif($result === 'D') bg-surface-600 text-text-body
                                @else bg-accent-red text-white @endif">{{ $result }}</span>
                        @empty
                            <span>—</span>
                        @endforelse
                    </div>
                    @if($opponentStanding)
                        <span class="tabular-nums shrink-0">{{ $opponentStanding->position }} · {{ $opponentStanding->points }} {{ __('game.pts') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 {{ $inModal ? 'lg:grid-cols-2' : 'lg:grid-cols-3' }} gap-5">

    {{-- Sidebar column.
         Standalone page: holds the tactical insights only (left col).
         Modal: also absorbs the radar + key-players cards so we can keep the
         pitch as a balanced second column instead of squeezing three columns
         into the narrower modal width. --}}
    <div class="space-y-5 {{ $inModal ? 'lg:order-2' : '' }}">

        {{-- Coach recommendations --}}
        <x-section-card :title="__('squad.coach_recommendations')">
            <div class="p-4">
                @if($inModal)
                    {{-- Inside the lineup modal, recommendations are reactive to the
                         lineup state the manager is editing (selected XI, formation,
                         mentality), so we let the lineupManager Alpine getter drive them. --}}
                    <template x-if="coachTips.length > 0">
                        <div class="space-y-2">
                            <template x-for="tip in coachTips" :key="tip.id">
                                <div class="flex items-start gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0" :class="tip.type === 'warning' ? 'bg-amber-400' : 'bg-sky-400'"></span>
                                    <span class="text-xs text-text-secondary leading-relaxed" x-text="tip.message"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="coachTips.length === 0">
                        <p class="text-xs text-text-secondary italic" x-text="translations.coach_no_tips"></p>
                    </template>
                @else
                    {{-- Standalone scout page has no lineup context yet: render the
                         pre-lineup tips computed server-side from the projected best XI. --}}
                    @if(count($coachTips) > 0)
                        <div class="space-y-2">
                            @foreach($coachTips as $tip)
                                <div class="flex items-start gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full mt-1.5 shrink-0 {{ $tip['type'] === 'warning' ? 'bg-amber-400' : 'bg-sky-400' }}"></span>
                                    <span class="text-xs text-text-secondary leading-relaxed">{{ $tip['message'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-text-secondary italic">{{ __('squad.coach_no_tips') }}</p>
                    @endif
                @endif
            </div>
        </x-section-card>

        {{-- Tactical mindset --}}
        <x-section-card :title="__('opponent.tactical_mindset')">
            <div class="divide-y divide-border-default">
                <div class="px-5 py-3 flex items-center justify-between gap-3">
                    <span class="text-xs text-text-muted uppercase tracking-wide">{{ __('opponent.formation') }}</span>
                    <span class="text-sm font-bold text-text-primary">{{ $opponentData['formation'] }}</span>
                </div>

                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <span class="text-xs text-text-muted uppercase tracking-wide">{{ __('opponent.mentality') }}</span>
                        <span class="text-sm font-bold {{ $mentalityClass }}">{{ __('squad.mentality_' . $opponentData['mentality']) }}</span>
                    </div>
                    <p class="text-xs text-text-secondary leading-relaxed">{{ $tacticsSummaries['mentality']['summary'] }}</p>
                </div>

                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <span class="text-xs text-text-muted uppercase tracking-wide">{{ __('opponent.playing_style') }}</span>
                        <span class="text-sm font-semibold text-text-body">{{ $tacticsSummaries['playingStyle']['label'] }}</span>
                    </div>
                    <p class="text-xs text-text-secondary leading-relaxed">{{ $tacticsSummaries['playingStyle']['summary'] }}</p>
                </div>

                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <span class="text-xs text-text-muted uppercase tracking-wide">{{ __('opponent.pressing') }}</span>
                        <span class="text-sm font-semibold text-text-body">{{ $tacticsSummaries['pressing']['label'] }}</span>
                    </div>
                    <p class="text-xs text-text-secondary leading-relaxed">{{ $tacticsSummaries['pressing']['summary'] }}</p>
                </div>

                <div class="px-5 py-3">
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <span class="text-xs text-text-muted uppercase tracking-wide">{{ __('opponent.defensive_line') }}</span>
                        <span class="text-sm font-semibold text-text-body">{{ $tacticsSummaries['defensiveLine']['label'] }}</span>
                    </div>
                    <p class="text-xs text-text-secondary leading-relaxed">{{ $tacticsSummaries['defensiveLine']['summary'] }}</p>
                </div>
            </div>
        </x-section-card>

        @if($inModal)
            {{-- In the 2-column modal layout the radar joins the sidebar.
                 Key Players sits underneath the pitch (see middle column). --}}
            @include('partials.opponent.strengths-radar')
        @endif

    </div>

    {{-- Middle column: predicted XI pitch with the Key Players card pinned
         directly underneath in every layout variant. Reorders to the left
         (`lg:order-1`) inside the modal so the visual sits next to its
         narrative sidebar. --}}
    <div class="space-y-5 {{ $inModal ? 'lg:order-1' : '' }}">
        <x-section-card :title="__('opponent.predicted_xi')" :badge="$opponentData['formation']">
            <div class="p-4 md:p-6">
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

                        @foreach($pitchSlots as $entry)
                            @php
                                $slot = $entry['slot'];
                                $player = $entry['player'];
                                // Grid: 9 cols × 14 rows. Mirror the y-axis so the
                                // opponent attacks from the top of the pitch.
                                $xPct = (($slot['col'] + 0.5) / 9) * 100;
                                $yPct = (1 - (($slot['row'] + 0.5) / 14)) * 100;
                                $shirtStyle = \App\Support\ShirtStyle::background($slot['role'], $opponentColors);
                                $numberStyle = \App\Support\ShirtStyle::number($slot['role'], $opponentColors);
                                $rating = $player ? $player->getEffectiveRating() : null;
                                $ratingClass = match(true) {
                                    $rating === null => 'bg-surface-600 text-text-muted',
                                    $rating >= 80 => 'bg-accent-green text-white',
                                    $rating >= 70 => 'bg-lime-500 text-white',
                                    $rating >= 60 => 'bg-accent-gold text-white',
                                    default => 'bg-accent-orange text-white',
                                };
                            @endphp
                            <div class="absolute transform -translate-x-1/2 -translate-y-1/2 flex flex-col items-center"
                                 style="left: {{ $xPct }}%; top: {{ $yPct }}%;">
                                <div class="relative w-11 h-11 rounded-xl shadow-lg border border-white/20" style="{{ $shirtStyle }}">
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="font-bold text-xs leading-none inline-flex items-center justify-center w-7 h-7 rounded-full" style="{{ $numberStyle }}">
                                            {{ $player?->number ?? $slot['displayLabel'] }}
                                        </span>
                                    </div>
                                    @if($rating !== null)
                                        <span class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-0.5 rounded-sm text-[9px] font-bold leading-none flex items-center justify-center shadow-sm {{ $ratingClass }}">{{ $rating }}</span>
                                    @endif
                                </div>
                                @if($player)
                                    <span class="mt-0.5 text-[8px] max-w-[66px] font-semibold text-white uppercase tracking-wide leading-tight text-center line-clamp-2 break-words drop-shadow-[0_1px_2px_rgba(0,0,0,0.8)]">
                                        {{ $player->name }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="text-[10px] text-text-muted text-center mt-3 italic">{{ __('opponent.prediction_disclaimer') }}</p>
            </div>
        </x-section-card>

        {{-- Key Players sits right under the pitch so the named threats line
             up visually with the shirts they wear above. --}}
        @include('partials.opponent.key-players')
    </div>

    @unless($inModal)
        {{-- Right column (standalone page only): head-to-head radar and the
             lineup CTA. The radar moves into the sidebar inside the modal
             so the grid can stay at 2 columns. --}}
        <div class="space-y-5">
            @include('partials.opponent.strengths-radar')

            {{-- Quick actions (redundant inside the lineup modal). --}}
            <div class="flex flex-col gap-2">
                <x-primary-button-link :href="route('game.lineup', $game->id)" class="w-full justify-center">
                    {{ __('opponent.cta_set_lineup') }}
                </x-primary-button-link>
            </div>
        </div>
    @endunless

</div>
