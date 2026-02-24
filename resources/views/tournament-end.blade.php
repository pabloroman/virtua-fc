@php
/** @var App\Models\Game $game */
/** @var App\Models\Competition $competition */
/** @var \Illuminate\Support\Collection $groupStandings */
/** @var \Illuminate\Support\Collection $knockoutTies */
/** @var string|null $championTeamId */
/** @var App\Models\Team|null $championTeam */
/** @var App\Models\Team|null $finalistTeam */
/** @var App\Models\CupTie|null $finalTie */
/** @var App\Models\GameMatch|null $finalMatch */
/** @var \Illuminate\Support\Collection $finalGoalEvents */
/** @var \Illuminate\Support\Collection $yourMatches */
/** @var App\Models\GameStanding|null $playerStanding */
/** @var array $yourRecord */
/** @var string $finishLabel */
/** @var \Illuminate\Support\Collection $topScorers */
/** @var \Illuminate\Support\Collection $topAssisters */
/** @var \Illuminate\Support\Collection $topGoalkeepers */
/** @var \Illuminate\Support\Collection $yourSquadStats */

$isChampion = $championTeamId === $game->team_id;
$yourGoalScorers = $yourSquadStats->where('goals', '>', 0)->sortByDesc('goals');
$yourAppearances = $yourSquadStats->where('appearances', '>', 0)->sortByDesc('appearances');
@endphp

<x-app-layout>
    <div class="min-h-screen" x-data="{
        showResults: false,
        resultsTab: 'groups',
        showSquadStats: false,
        copied: false,
        copyToClipboard() {
            const text = '{{ $isChampion ? "🏆" : "⚽" }} {{ __($competition->name ?? "game.wc2026_name") }}\n{{ __($finishLabel) }} - {{ $game->team->name }}\n{{ __("season.played_abbr") }}{{ $yourRecord["played"] }} {{ __("season.won") }}{{ $yourRecord["won"] }} {{ __("season.drawn") }}{{ $yourRecord["drawn"] }} {{ __("season.lost") }}{{ $yourRecord["lost"] }} ({{ $yourRecord["goalsFor"] }}-{{ $yourRecord["goalsAgainst"] }})';
            navigator.clipboard.writeText(text).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        }
    }">

        {{-- ============================================= --}}
        {{-- HEADER: Champion Celebration + Final Result   --}}
        {{-- ============================================= --}}
        <div class="relative overflow-hidden bg-gradient-to-b from-amber-600 via-amber-500 to-amber-400 py-10 md:py-16">
            {{-- Decorative background --}}
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-20 -left-20 w-60 h-60 bg-white/5 rounded-full"></div>
                <div class="absolute -bottom-10 -right-10 w-80 h-80 bg-white/5 rounded-full"></div>
                <div class="absolute top-8 left-1/4 text-amber-300/30 text-4xl">&#9733;</div>
                <div class="absolute top-16 right-1/4 text-amber-300/30 text-3xl">&#9733;</div>
                <div class="absolute bottom-8 left-1/3 text-amber-300/30 text-2xl">&#9733;</div>
                <div class="absolute bottom-12 right-1/3 text-amber-300/20 text-5xl">&#9733;</div>
            </div>

            <div class="relative max-w-4xl mx-auto px-4 text-center">
                {{-- Trophy + Title --}}
                <div class="text-5xl md:text-7xl mb-3">&#127942;</div>
                <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-1 tracking-tight">
                    {{ __('season.tournament_champion') }}
                </h1>
                <p class="text-base md:text-lg text-amber-100 font-medium mb-6">
                    {{ __($competition->name ?? 'game.wc2026_name') }}
                </p>

                {{-- Champion Flag + Name --}}
                @if($championTeam)
                <div class="inline-flex flex-col items-center mb-8">
                    <x-team-crest :team="$championTeam"
                        class="w-24 h-24 md:w-32 md:h-32 drop-shadow-lg" />
                    <div class="mt-3 text-xl md:text-2xl font-bold text-white">{{ $championTeam->name }}</div>
                </div>
                @endif

                {{-- The Final --}}
                @if($finalMatch && $championTeam && $finalistTeam)
                <div class="max-w-lg mx-auto">
                    <div class="text-xs font-semibold text-amber-200 uppercase tracking-widest mb-3">
                        {{ __('season.the_final') }}
                    </div>

                    {{-- Score display --}}
                    <div class="flex items-center justify-center gap-3 md:gap-5 mb-3">
                        {{-- Home team --}}
                        <div class="flex flex-col items-center gap-1 flex-1 min-w-0">
                            <x-team-crest :team="$finalMatch->homeTeam" class="w-10 h-10 md:w-12 md:h-12 shrink-0" />
                            <span class="text-xs md:text-sm font-semibold text-white truncate max-w-[100px] md:max-w-none">
                                {{ $finalMatch->homeTeam->name }}
                            </span>
                        </div>

                        {{-- Score --}}
                        <div class="shrink-0 text-center">
                            <div class="text-3xl md:text-5xl font-bold text-white tabular-nums">
                                {{ $finalMatch->home_score }} - {{ $finalMatch->away_score }}
                            </div>
                            @if($finalMatch->is_extra_time)
                            <div class="text-xs text-amber-200 mt-1">
                                @if($finalMatch->home_score_penalties !== null)
                                    {{ __('season.aet_abbr') }} &middot; {{ __('season.pens_abbr') }} {{ $finalMatch->home_score_penalties }}-{{ $finalMatch->away_score_penalties }}
                                @else
                                    {{ __('season.aet_abbr') }}
                                @endif
                            </div>
                            @endif
                        </div>

                        {{-- Away team --}}
                        <div class="flex flex-col items-center gap-1 flex-1 min-w-0">
                            <x-team-crest :team="$finalMatch->awayTeam" class="w-10 h-10 md:w-12 md:h-12 shrink-0" />
                            <span class="text-xs md:text-sm font-semibold text-white truncate max-w-[100px] md:max-w-none">
                                {{ $finalMatch->awayTeam->name }}
                            </span>
                        </div>
                    </div>

                    {{-- Goal scorers --}}
                    @if($finalGoalEvents->isNotEmpty())
                    <div class="flex justify-center gap-4 md:gap-8 text-xs text-amber-100">
                        {{-- Home scorers --}}
                        <div class="text-right flex-1 min-w-0">
                            @foreach($finalGoalEvents->where('team_id', $finalMatch->home_team_id) as $event)
                            <div class="truncate">
                                {{ $event->gamePlayer->player->name }} {{ $event->minute }}'
                                @if($event->event_type === 'own_goal')
                                    <span class="text-amber-300">({{ __('season.own_goal_abbr') }})</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        {{-- Away scorers --}}
                        <div class="text-left flex-1 min-w-0">
                            @foreach($finalGoalEvents->where('team_id', $finalMatch->away_team_id) as $event)
                            <div class="truncate">
                                {{ $event->gamePlayer->player->name }} {{ $event->minute }}'
                                @if($event->event_type === 'own_goal')
                                    <span class="text-amber-300">({{ __('season.own_goal_abbr') }})</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Expand full results toggle --}}
                <div class="mt-8">
                    <button
                        @click="showResults = !showResults"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-amber-100 hover:text-white bg-white/10 hover:bg-white/20 rounded-lg transition min-h-[44px]">
                        <svg class="w-4 h-4 transition-transform" :class="showResults && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        {{ __('season.full_tournament_results') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ============================================= --}}
        {{-- COLLAPSIBLE: Full Tournament Results          --}}
        {{-- ============================================= --}}
        <div x-show="showResults" x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="bg-slate-50 border-b border-slate-200">
            <div class="max-w-5xl mx-auto px-4 py-6 md:py-8">

                {{-- Tab buttons --}}
                @if($groupStandings->isNotEmpty() && $knockoutTies->isNotEmpty())
                <div class="flex justify-center gap-2 mb-6">
                    <button
                        @click="resultsTab = 'groups'"
                        :class="resultsTab === 'groups' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition min-h-[44px]">
                        {{ __('season.group_stage') }}
                    </button>
                    <button
                        @click="resultsTab = 'knockout'"
                        :class="resultsTab === 'knockout' ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-100'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition min-h-[44px]">
                        {{ __('season.knockout_phase') }}
                    </button>
                </div>
                @endif

                {{-- Group Stage Tables --}}
                @if($groupStandings->isNotEmpty())
                <div x-show="resultsTab === 'groups'">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                        @foreach($groupStandings as $groupLabel => $standings)
                        <div class="bg-white rounded-lg border border-slate-200 p-4">
                            <h3 class="text-xs font-semibold text-slate-500 uppercase mb-2">
                                {{ __('season.group_label', ['group' => $groupLabel]) }}
                            </h3>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-[10px] text-slate-400 uppercase">
                                            <th class="text-left py-1 pr-2 w-6"></th>
                                            <th class="text-left py-1"></th>
                                            <th class="text-center py-1 w-6">{{ __('season.played_abbr') }}</th>
                                            <th class="text-center py-1 w-6">{{ __('season.won') }}</th>
                                            <th class="text-center py-1 w-6">{{ __('season.drawn') }}</th>
                                            <th class="text-center py-1 w-6">{{ __('season.lost') }}</th>
                                            <th class="text-center py-1 w-8 hidden md:table-cell">{{ __('season.goals_for') }}</th>
                                            <th class="text-center py-1 w-8 hidden md:table-cell">{{ __('season.goals_against') }}</th>
                                            <th class="text-center py-1 w-8 font-bold">{{ __('season.pts_abbr') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($standings as $standing)
                                        <tr class="{{ $standing->team_id === $game->team_id ? 'bg-amber-50 font-semibold' : '' }} {{ $standing->position <= 2 ? 'border-l-2 border-l-emerald-400' : '' }}">
                                            <td class="py-1.5 pr-1 text-center text-xs text-slate-400">{{ $standing->position }}</td>
                                            <td class="py-1.5">
                                                <div class="flex items-center gap-1.5">
                                                    <x-team-crest :team="$standing->team" class="w-4 h-4 shrink-0" />
                                                    <span class="text-xs truncate">{{ $standing->team->name }}</span>
                                                </div>
                                            </td>
                                            <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->played }}</td>
                                            <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->won }}</td>
                                            <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->drawn }}</td>
                                            <td class="text-center py-1.5 text-xs text-slate-500">{{ $standing->lost }}</td>
                                            <td class="text-center py-1.5 text-xs text-slate-500 hidden md:table-cell">{{ $standing->goals_for }}</td>
                                            <td class="text-center py-1.5 text-xs text-slate-500 hidden md:table-cell">{{ $standing->goals_against }}</td>
                                            <td class="text-center py-1.5 text-xs font-bold text-slate-900">{{ $standing->points }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Knockout Bracket --}}
                @if($knockoutTies->isNotEmpty())
                <div x-show="resultsTab === 'knockout'">
                    <div class="space-y-6">
                        @foreach($knockoutTies as $roundNumber => $ties)
                        @php
                            $roundName = $ties->first()->firstLegMatch?->round_name ? __($ties->first()->firstLegMatch->round_name) : __('cup.round_n', ['round' => $roundNumber]);
                        @endphp
                        <div>
                            <h3 class="text-xs font-semibold text-slate-500 uppercase mb-3">{{ $roundName }}</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($ties as $tie)
                                @php
                                    $match = $tie->firstLegMatch;
                                    $homeScore = $match?->home_score ?? 0;
                                    $awayScore = $match?->away_score ?? 0;
                                    $involvesPlayer = $tie->home_team_id === $game->team_id || $tie->away_team_id === $game->team_id;
                                    $isHomeWinner = $tie->winner_id === $tie->home_team_id;
                                    $isAwayWinner = $tie->winner_id === $tie->away_team_id;
                                @endphp
                                <div class="bg-white border rounded-lg p-3 {{ $involvesPlayer ? 'border-amber-300 bg-amber-50/50' : 'border-slate-200' }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 flex-1 min-w-0 {{ $isHomeWinner ? 'font-semibold' : '' }}">
                                            <x-team-crest :team="$tie->homeTeam" class="w-5 h-5 shrink-0" />
                                            <span class="text-sm truncate {{ $isHomeWinner ? 'text-slate-900' : 'text-slate-500' }}">{{ $tie->homeTeam->name }}</span>
                                        </div>
                                        <div class="shrink-0 text-center">
                                            <span class="text-sm font-bold text-slate-900">{{ $homeScore }} - {{ $awayScore }}</span>
                                            @if($match?->is_extra_time)
                                            <div class="text-[10px] text-slate-400">
                                                @if($match->home_score_penalties !== null)
                                                    {{ __('season.pens_abbr') }} {{ $match->home_score_penalties }}-{{ $match->away_score_penalties }}
                                                @else
                                                    {{ __('season.aet_abbr') }}
                                                @endif
                                            </div>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 flex-1 min-w-0 justify-end {{ $isAwayWinner ? 'font-semibold' : '' }}">
                                            <span class="text-sm truncate text-right {{ $isAwayWinner ? 'text-slate-900' : 'text-slate-500' }}">{{ $tie->awayTeam->name }}</span>
                                            <x-team-crest :team="$tie->awayTeam" class="w-5 h-5 shrink-0" />
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>
        </div>

        {{-- ============================================= --}}
        {{-- MAIN CONTENT: Stats + Your Team               --}}
        {{-- ============================================= --}}
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">

                {{-- ========================================= --}}
                {{-- LEFT COLUMN: Tournament Awards            --}}
                {{-- ========================================= --}}
                <div class="space-y-6">
                    <div class="text-center text-slate-400 font-semibold text-xs uppercase tracking-wide">
                        <span>&#9733;</span> {{ __('season.tournament_awards') }} <span>&#9733;</span>
                    </div>

                    {{-- Golden Boot (Top 5 Scorers) --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-lg">&#129351;</span>
                            <h3 class="text-xs font-semibold text-amber-600 uppercase tracking-wide">{{ __('season.golden_boot') }}</h3>
                        </div>
                        @if($topScorers->isNotEmpty())
                        <div class="space-y-1.5">
                            @foreach($topScorers as $scorer)
                            <div class="flex items-center gap-3 py-1.5 {{ $loop->first ? 'bg-amber-50 -mx-2 px-2 rounded-lg' : '' }} {{ $scorer->team_id === $game->team_id && !$loop->first ? 'bg-sky-50 -mx-2 px-2 rounded-lg' : '' }}">
                                <span class="w-5 text-center text-xs font-bold {{ $loop->first ? 'text-amber-600' : 'text-slate-400' }}">{{ $loop->iteration }}</span>
                                <x-team-crest :team="$scorer->team" class="w-5 h-5 shrink-0" />
                                <span class="flex-1 text-sm text-slate-900 truncate {{ $loop->first ? 'font-semibold' : '' }}">{{ $scorer->player->name }}</span>
                                <span class="text-sm font-bold {{ $loop->first ? 'text-amber-600' : 'text-slate-700' }}">{{ $scorer->goals }}</span>
                                <span class="text-xs text-slate-400 w-8">{{ $scorer->assists }}a</span>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-slate-400 text-sm text-center py-4">{{ __('season.no_goals_scored') }}</div>
                        @endif
                    </div>

                    {{-- Golden Glove (Top 5 Goalkeepers) --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-lg">&#129351;</span>
                            <h3 class="text-xs font-semibold text-sky-600 uppercase tracking-wide">{{ __('season.golden_glove') }}</h3>
                        </div>
                        @if($topGoalkeepers->isNotEmpty())
                        <div class="space-y-1.5">
                            @foreach($topGoalkeepers as $gk)
                            <div class="flex items-center gap-3 py-1.5 {{ $loop->first ? 'bg-sky-50 -mx-2 px-2 rounded-lg' : '' }} {{ $gk->team_id === $game->team_id && !$loop->first ? 'bg-sky-50/50 -mx-2 px-2 rounded-lg' : '' }}">
                                <span class="w-5 text-center text-xs font-bold {{ $loop->first ? 'text-sky-600' : 'text-slate-400' }}">{{ $loop->iteration }}</span>
                                <x-team-crest :team="$gk->team" class="w-5 h-5 shrink-0" />
                                <span class="flex-1 text-sm text-slate-900 truncate {{ $loop->first ? 'font-semibold' : '' }}">{{ $gk->player->name }}</span>
                                <span class="text-sm font-bold {{ $loop->first ? 'text-sky-600' : 'text-slate-700' }}">{{ $gk->clean_sheets }}</span>
                                <span class="text-[10px] text-slate-400 w-12 text-right">{{ number_format($gk->goals_conceded / max(1, $gk->appearances), 2) }}/m</span>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-slate-400 text-sm text-center py-4">{{ __('season.not_enough_data') }}</div>
                        @endif
                    </div>

                    {{-- Best Playmaker (Top 5 Assisters) --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-lg">&#129351;</span>
                            <h3 class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">{{ __('season.best_playmaker') }}</h3>
                        </div>
                        @if($topAssisters->isNotEmpty())
                        <div class="space-y-1.5">
                            @foreach($topAssisters as $assister)
                            <div class="flex items-center gap-3 py-1.5 {{ $loop->first ? 'bg-emerald-50 -mx-2 px-2 rounded-lg' : '' }} {{ $assister->team_id === $game->team_id && !$loop->first ? 'bg-sky-50 -mx-2 px-2 rounded-lg' : '' }}">
                                <span class="w-5 text-center text-xs font-bold {{ $loop->first ? 'text-emerald-600' : 'text-slate-400' }}">{{ $loop->iteration }}</span>
                                <x-team-crest :team="$assister->team" class="w-5 h-5 shrink-0" />
                                <span class="flex-1 text-sm text-slate-900 truncate {{ $loop->first ? 'font-semibold' : '' }}">{{ $assister->player->name }}</span>
                                <span class="text-sm font-bold {{ $loop->first ? 'text-emerald-600' : 'text-slate-700' }}">{{ $assister->assists }}</span>
                                <span class="text-xs text-slate-400 w-8">{{ $assister->goals }}g</span>
                            </div>
                            @endforeach
                        </div>
                        @else
                        <div class="text-slate-400 text-sm text-center py-4">{{ __('season.no_assists_recorded') }}</div>
                        @endif
                    </div>
                </div>

                {{-- ========================================= --}}
                {{-- RIGHT COLUMN: Your Team's Performance     --}}
                {{-- ========================================= --}}
                <div class="space-y-6">
                    <div class="text-center text-slate-400 font-semibold text-xs uppercase tracking-wide">
                        {{ __('season.your_performance') }}
                    </div>

                    {{-- Finish Badge + Record --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="flex flex-col items-center mb-5">
                            <x-team-crest :team="$game->team" class="w-14 h-14 md:w-16 md:h-16 mb-3" />
                            <div class="font-bold text-slate-900 text-lg mb-2">{{ $game->team->name }}</div>
                            @php
                                $badgeColors = match($finishLabel) {
                                    'season.finish_champion' => 'bg-amber-100 text-amber-800 border-amber-300',
                                    'season.finish_finalist' => 'bg-slate-100 text-slate-700 border-slate-300',
                                    'season.finish_semi_finalist' => 'bg-orange-50 text-orange-700 border-orange-200',
                                    default => 'bg-slate-50 text-slate-600 border-slate-200',
                                };
                            @endphp
                            <span class="inline-block px-4 py-1.5 rounded-full text-sm font-bold border {{ $badgeColors }}">
                                {{ __($finishLabel) }}
                            </span>
                        </div>

                        {{-- Stats Grid --}}
                        <div class="grid grid-cols-3 gap-3 text-center border-t border-slate-100 pt-4">
                            <div>
                                <div class="text-xl md:text-2xl font-bold text-slate-900">{{ $yourRecord['played'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.played_abbr') }}</div>
                            </div>
                            <div>
                                <div class="text-xl md:text-2xl font-bold text-green-600">{{ $yourRecord['won'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.won') }}</div>
                            </div>
                            <div>
                                <div class="text-xl md:text-2xl font-bold text-slate-400">{{ $yourRecord['drawn'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.drawn') }}</div>
                            </div>
                            <div>
                                <div class="text-xl md:text-2xl font-bold text-red-500">{{ $yourRecord['lost'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.lost') }}</div>
                            </div>
                            <div>
                                <div class="text-xl md:text-2xl font-bold text-slate-900">{{ $yourRecord['goalsFor'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.goals_for') }}</div>
                            </div>
                            <div>
                                <div class="text-xl md:text-2xl font-bold text-slate-900">{{ $yourRecord['goalsAgainst'] }}</div>
                                <div class="text-[10px] text-slate-400 uppercase">{{ __('season.goals_against') }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Match Journey --}}
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-4 text-center">{{ __('season.match_journey') }}</h3>
                        <div class="space-y-1.5">
                            @foreach($yourMatches as $match)
                            @php
                                $isHome = $match->home_team_id === $game->team_id;
                                $opponent = $isHome ? $match->awayTeam : $match->homeTeam;
                                $scored = $isHome ? $match->home_score : $match->away_score;
                                $conceded = $isHome ? $match->away_score : $match->home_score;
                                $resultClass = $scored > $conceded ? 'bg-green-500' : ($scored < $conceded ? 'bg-red-500' : 'bg-slate-400');
                                $resultLetter = $scored > $conceded ? 'W' : ($scored < $conceded ? 'L' : 'D');
                            @endphp
                            <div class="flex items-center gap-2.5 py-2 px-2 rounded-lg {{ $loop->even ? 'bg-slate-50' : '' }}">
                                <span class="shrink-0 w-6 h-6 rounded text-[10px] font-bold flex items-center justify-center text-white {{ $resultClass }}">
                                    {{ $resultLetter }}
                                </span>
                                <span class="hidden md:inline text-[10px] text-slate-400 w-14 shrink-0 truncate">
                                    {{ $match->round_name ? __($match->round_name) : __('game.matchday_n', ['number' => $match->round_number]) }}
                                </span>
                                <div class="flex items-center gap-1.5 flex-1 min-w-0">
                                    <x-team-crest :team="$opponent" class="w-4 h-4 shrink-0" />
                                    <span class="text-sm text-slate-900 truncate">
                                        {{ $isHome ? '' : '@ ' }}{{ $opponent->name }}
                                    </span>
                                </div>
                                <div class="shrink-0 text-sm font-bold text-slate-900 tabular-nums">
                                    {{ $scored }}-{{ $conceded }}
                                </div>
                                @if($match->is_extra_time)
                                <span class="shrink-0 text-[10px] text-slate-400 font-medium w-6">
                                    {{ $match->home_score_penalties !== null ? __('season.pens_abbr') : __('season.aet_abbr') }}
                                </span>
                                @else
                                <span class="w-6 shrink-0"></span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Collapsible Squad Stats --}}
                    @if($yourAppearances->isNotEmpty())
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
                        <button
                            @click="showSquadStats = !showSquadStats"
                            class="w-full flex items-center justify-between p-5 text-left min-h-[44px]">
                            <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wide">{{ __('season.squad_stats') }}</h3>
                            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="showSquadStats && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div x-show="showSquadStats" x-cloak
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="px-5 pb-5">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-[10px] text-slate-400 uppercase border-b border-slate-100">
                                            <th class="text-left py-2 w-6"></th>
                                            <th class="text-left py-2"></th>
                                            <th class="text-center py-2 w-8">{{ __('squad.appearances') }}</th>
                                            <th class="text-center py-2 w-8">{{ __('squad.goals') }}</th>
                                            <th class="text-center py-2 w-8">{{ __('squad.assists') }}</th>
                                            <th class="text-center py-2 w-8 hidden md:table-cell">{{ __('squad.yellow_cards') }}</th>
                                            <th class="text-center py-2 w-8 hidden md:table-cell">{{ __('squad.red_cards') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($yourAppearances as $gp)
                                        <tr class="{{ $loop->even ? 'bg-slate-50' : '' }}">
                                            <td class="py-1.5 pr-1"><x-position-badge :position="$gp->position" size="sm" /></td>
                                            <td class="py-1.5 font-medium text-slate-900 truncate max-w-[120px]">{{ $gp->player->name }}</td>
                                            <td class="text-center py-1.5 font-semibold text-slate-700">{{ $gp->appearances }}</td>
                                            <td class="text-center py-1.5 {{ $gp->goals > 0 ? 'font-semibold text-slate-700' : 'text-slate-300' }}">{{ $gp->goals }}</td>
                                            <td class="text-center py-1.5 {{ $gp->assists > 0 ? 'font-semibold text-slate-700' : 'text-slate-300' }}">{{ $gp->assists }}</td>
                                            <td class="text-center py-1.5 hidden md:table-cell {{ $gp->yellow_cards > 0 ? 'text-amber-600 font-medium' : 'text-slate-300' }}">{{ $gp->yellow_cards }}</td>
                                            <td class="text-center py-1.5 hidden md:table-cell {{ $gp->red_cards > 0 ? 'text-red-600 font-medium' : 'text-slate-300' }}">{{ $gp->red_cards }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

            </div>

            {{-- ============================================= --}}
            {{-- BOTTOM: Copy Summary + Start New Tournament   --}}
            {{-- ============================================= --}}
            <div class="flex flex-col items-center gap-4 pt-10 pb-8">
                {{-- Copy to clipboard --}}
                <button
                    @click="copyToClipboard()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-slate-600 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition min-h-[44px]">
                    <template x-if="!copied">
                        <span class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            {{ __('season.copy_summary') }}
                        </span>
                    </template>
                    <template x-if="copied">
                        <span class="inline-flex items-center gap-2 text-green-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ __('season.copied') }}
                        </span>
                    </template>
                </button>

                {{-- Start New Tournament CTA --}}
                <a href="{{ route('select-team') }}"
                   class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-700 hover:to-red-600 text-white rounded-lg text-lg font-bold shadow-lg transition-all transform hover:scale-105 min-h-[44px]">
                    {{ __('season.start_new_tournament') }}
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
