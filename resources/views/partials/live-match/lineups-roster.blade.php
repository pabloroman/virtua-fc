{{--
    Two-column lineups + ratings list.

    Originally inlined in `live-match.blade.php` (Lineups / Ratings tab).
    Extracted so the post-match summary can reuse the same component without
    duplicating markup.

    Required PHP variables:
        $match           — App\Models\GameMatch (provides home/away teams)
        $homeFormation   — formation label string, e.g. '4-3-3'
        $awayFormation   — formation label string

    Required parent Alpine scope (provided by `liveMatch` factory or
    `matchSummaryLineups` factory):
        homeLineupRoster, awayLineupRoster   — [{ id, name, positionAbbr, positionGroup, ... }]
        phase                                 — 'live' | 'full_time' | …
        playerRatings                         — { [playerId]: number }
        ratingColor(rating)                   — string of CSS classes
        getEventIcons()                       — { goals, yellowCards, redCards }
        getSubMap()                           — { subbedOut: { [id]: { minute, replacedById, replacedByName } }, ... }
--}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-3xl mx-auto">
    {{-- Home --}}
    <div>
        <div class="flex items-center gap-2 mb-3">
            <x-team-crest :team="$match->homeTeam" class="w-5 h-5 shrink-0" />
            <span class="font-heading font-bold text-sm uppercase tracking-wide text-text-primary">{{ $match->homeTeam->name }}</span>
            <span class="text-[10px] text-text-muted ml-auto">{{ $homeFormation }}</span>
        </div>
        <div class="space-y-0.5">
            <template x-if="homeLineupRoster.length === 0">
                <p class="text-xs text-text-muted italic px-3 py-3">{{ __('game.lineup_unknown') }}</p>
            </template>
            <template x-for="p in homeLineupRoster" :key="p.id">
                <div>
                    {{-- Starter row --}}
                    <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-surface-700">
                        <span class="inline-flex items-center justify-center w-6 h-6 text-[10px] -skew-x-12 font-semibold text-white shrink-0"
                              :class="{ 'bg-accent-gold': p.positionGroup === 'GK', 'bg-accent-blue': p.positionGroup === 'DEF', 'bg-accent-green': p.positionGroup === 'MID', 'bg-accent-red': p.positionGroup === 'FWD', 'bg-surface-600': !['GK','DEF','MID','FWD'].includes(p.positionGroup) }">
                            <span class="skew-x-12" x-text="p.positionAbbr"></span>
                        </span>
                        <span class="text-xs flex-1 truncate" x-text="p.name"
                              :class="(phase === 'full_time' && getSubMap().subbedOut[p.id]) ? 'text-text-muted' : 'text-text-body'"></span>
                        {{-- Event icons (full time only) --}}
                        <template x-if="phase === 'full_time'">
                            <span class="flex items-center gap-0.5 shrink-0">
                                <template x-if="getEventIcons().goals[p.id]">
                                    <span class="flex items-center gap-px text-accent-green">
                                        <svg class="w-3 h-3" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                                        <span x-show="getEventIcons().goals[p.id] > 1" x-text="'×' + getEventIcons().goals[p.id]" class="text-[8px] font-bold"></span>
                                    </span>
                                </template>
                                <template x-if="getEventIcons().yellowCards[p.id]">
                                    <span class="w-2 h-3 rounded-[1px] bg-accent-gold shrink-0"></span>
                                </template>
                                <template x-if="getEventIcons().redCards[p.id]">
                                    <span class="w-2 h-3 rounded-[1px] bg-accent-red shrink-0"></span>
                                </template>
                            </span>
                        </template>
                        {{-- Sub-out indicator --}}
                        <span x-show="phase === 'full_time' && getSubMap().subbedOut[p.id]" x-cloak
                              class="flex items-center gap-0.5 text-accent-red shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg>
                            <span class="text-[9px] font-semibold" x-text="getSubMap().subbedOut[p.id]?.minute + '\''"></span>
                        </span>
                        {{-- Rating badge --}}
                        <span x-show="phase === 'full_time' && playerRatings[p.id]"
                              x-cloak
                              class="inline-flex items-center justify-center min-w-[1.5rem] h-5 rounded-full px-1 text-[9px] font-semibold shrink-0"
                              :class="ratingColor(playerRatings[p.id])"
                              x-text="playerRatings[p.id]?.toFixed(1)"></span>
                    </div>
                    {{-- Sub-in row (shown below subbed-out player) --}}
                    <template x-if="phase === 'full_time' && getSubMap().subbedOut[p.id]">
                        <div class="flex items-center gap-2.5 px-3 py-1.5 pl-9 rounded-lg">
                            <span class="flex items-center text-accent-green shrink-0">
                                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg>
                            </span>
                            <span class="text-xs text-text-body flex-1 truncate" x-text="getSubMap().subbedOut[p.id]?.replacedByName"></span>
                            {{-- Event icons for sub-in player --}}
                            <template x-if="getSubMap().subbedOut[p.id]?.replacedById">
                                <span class="flex items-center gap-0.5 shrink-0">
                                    <template x-if="getEventIcons().goals[getSubMap().subbedOut[p.id]?.replacedById]">
                                        <span class="flex items-center gap-px text-text-secondary">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                                            <span x-show="getEventIcons().goals[getSubMap().subbedOut[p.id]?.replacedById] > 1" x-text="'×' + getEventIcons().goals[getSubMap().subbedOut[p.id]?.replacedById]" class="text-[8px] font-bold text-text-secondary"></span>
                                        </span>
                                    </template>
                                    <template x-if="getEventIcons().yellowCards[getSubMap().subbedOut[p.id]?.replacedById]">
                                        <span class="w-2 h-3 rounded-[1px] bg-accent-gold shrink-0"></span>
                                    </template>
                                    <template x-if="getEventIcons().redCards[getSubMap().subbedOut[p.id]?.replacedById]">
                                        <span class="w-2 h-3 rounded-[1px] bg-accent-red shrink-0"></span>
                                    </template>
                                </span>
                            </template>
                            {{-- Rating for sub-in --}}
                            <span x-show="playerRatings[getSubMap().subbedOut[p.id]?.replacedById]"
                                  x-cloak
                                  class="inline-flex items-center justify-center min-w-[1.5rem] h-5 rounded-full px-1 text-[9px] font-semibold shrink-0"
                                  :class="ratingColor(playerRatings[getSubMap().subbedOut[p.id]?.replacedById])"
                                  x-text="playerRatings[getSubMap().subbedOut[p.id]?.replacedById]?.toFixed(1)"></span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- Away --}}
    <div>
        <div class="flex items-center gap-2 mb-3">
            <x-team-crest :team="$match->awayTeam" class="w-5 h-5 shrink-0" />
            <span class="font-heading font-bold text-sm uppercase tracking-wide text-text-primary">{{ $match->awayTeam->name }}</span>
            <span class="text-[10px] text-text-muted ml-auto">{{ $awayFormation }}</span>
        </div>
        <div class="space-y-0.5">
            <template x-if="awayLineupRoster.length === 0">
                <p class="text-xs text-text-muted italic px-3 py-3">{{ __('game.lineup_unknown') }}</p>
            </template>
            <template x-for="p in awayLineupRoster" :key="p.id">
                <div>
                    {{-- Starter row --}}
                    <div class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg hover:bg-surface-700">
                        <span class="inline-flex items-center justify-center w-6 h-6 text-[10px] -skew-x-12 font-semibold text-white shrink-0"
                              :class="{ 'bg-accent-gold': p.positionGroup === 'GK', 'bg-accent-blue': p.positionGroup === 'DEF', 'bg-accent-green': p.positionGroup === 'MID', 'bg-accent-red': p.positionGroup === 'FWD', 'bg-surface-600': !['GK','DEF','MID','FWD'].includes(p.positionGroup) }">
                            <span class="skew-x-12" x-text="p.positionAbbr"></span>
                        </span>
                        <span class="text-xs flex-1 truncate" x-text="p.name"
                              :class="(phase === 'full_time' && getSubMap().subbedOut[p.id]) ? 'text-text-muted' : 'text-text-body'"></span>
                        {{-- Event icons (full time only) --}}
                        <template x-if="phase === 'full_time'">
                            <span class="flex items-center gap-0.5 shrink-0">
                                <template x-if="getEventIcons().goals[p.id]">
                                    <span class="flex items-center gap-px text-accent-green">
                                        <svg class="w-3 h-3" viewBox="0 0 16 16" fill="currentColor"><circle cx="8" cy="8" r="8"/></svg>
                                        <span x-show="getEventIcons().goals[p.id] > 1" x-text="'×' + getEventIcons().goals[p.id]" class="text-[8px] font-bold"></span>
                                    </span>
                                </template>
                                <template x-if="getEventIcons().yellowCards[p.id]">
                                    <span class="w-2 h-3 rounded-[1px] bg-accent-gold shrink-0"></span>
                                </template>
                                <template x-if="getEventIcons().redCards[p.id]">
                                    <span class="w-2 h-3 rounded-[1px] bg-accent-red shrink-0"></span>
                                </template>
                            </span>
                        </template>
                        {{-- Sub-out indicator --}}
                        <span x-show="phase === 'full_time' && getSubMap().subbedOut[p.id]" x-cloak
                              class="flex items-center gap-0.5 text-accent-red shrink-0">
                            <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg>
                            <span class="text-[9px] font-semibold" x-text="getSubMap().subbedOut[p.id]?.minute + '\''"></span>
                        </span>
                        {{-- Rating badge --}}
                        <span x-show="phase === 'full_time' && playerRatings[p.id]"
                              x-cloak
                              class="inline-flex items-center justify-center min-w-[1.5rem] h-5 rounded-full px-1 text-[9px] font-semibold shrink-0"
                              :class="ratingColor(playerRatings[p.id])"
                              x-text="playerRatings[p.id]?.toFixed(1)"></span>
                    </div>
                    {{-- Sub-in row (shown below subbed-out player) --}}
                    <template x-if="phase === 'full_time' && getSubMap().subbedOut[p.id]">
                        <div class="flex items-center gap-2.5 px-3 py-1.5 pl-9 rounded-lg">
                            <span class="flex items-center text-accent-green shrink-0">
                                <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg>
                            </span>
                            <span class="text-xs text-text-body flex-1 truncate" x-text="getSubMap().subbedOut[p.id]?.replacedByName"></span>
                            {{-- Event icons for sub-in player --}}
                            <template x-if="getSubMap().subbedOut[p.id]?.replacedById">
                                <span class="flex items-center gap-0.5 shrink-0">
                                    <template x-if="getEventIcons().goals[getSubMap().subbedOut[p.id]?.replacedById]">
                                        <span class="flex items-center gap-px text-text-secondary">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5" fill="none"/><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>
                                            <span x-show="getEventIcons().goals[getSubMap().subbedOut[p.id]?.replacedById] > 1" x-text="'×' + getEventIcons().goals[getSubMap().subbedOut[p.id]?.replacedById]" class="text-[8px] font-bold text-text-secondary"></span>
                                        </span>
                                    </template>
                                    <template x-if="getEventIcons().yellowCards[getSubMap().subbedOut[p.id]?.replacedById]">
                                        <span class="w-2 h-3 rounded-[1px] bg-accent-gold shrink-0"></span>
                                    </template>
                                    <template x-if="getEventIcons().redCards[getSubMap().subbedOut[p.id]?.replacedById]">
                                        <span class="w-2 h-3 rounded-[1px] bg-accent-red shrink-0"></span>
                                    </template>
                                </span>
                            </template>
                            {{-- Rating for sub-in --}}
                            <span x-show="playerRatings[getSubMap().subbedOut[p.id]?.replacedById]"
                                  x-cloak
                                  class="inline-flex items-center justify-center min-w-[1.5rem] h-5 rounded-full px-1 text-[9px] font-semibold shrink-0"
                                  :class="ratingColor(playerRatings[getSubMap().subbedOut[p.id]?.replacedById])"
                                  x-text="playerRatings[getSubMap().subbedOut[p.id]?.replacedById]?.toFixed(1)"></span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>
