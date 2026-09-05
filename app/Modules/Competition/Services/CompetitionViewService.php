<?php

namespace App\Modules\Competition\Services;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\GameStanding;
use App\Models\MatchEvent;
use Illuminate\Support\Collection;

class CompetitionViewService
{
    public function __construct(
        private readonly CountryConfig $countryConfig,
    ) {}

    public function getStandings(Game $game, Competition $competition): Collection
    {
        return GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('group_label')
            ->orderBy('position')
            ->get();
    }

    /**
     * Abridged standings for any competition the player participates in.
     * Returns the player's group when standings are grouped (e.g.
     * group-stage cups); otherwise top 3 + a 5-team window centered on the
     * player. When the player isn't in this competition, the window centers
     * on position 1.
     */
    public function getAbridgedStandings(Game $game, Competition $competition): Collection
    {
        $playerGroupLabel = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->where('team_id', $game->team_id)
            ->value('group_label');

        $query = GameStanding::with('team')
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id);

        if ($playerGroupLabel) {
            $query->where('group_label', $playerGroupLabel);
        }

        $standings = $query->orderBy('position')->get();

        if ($standings->isEmpty()) {
            return collect();
        }

        // Grouped standings (e.g. World Cup group, or any future grouped
        // format): show the player's full group rather than abridging.
        if ($playerGroupLabel) {
            return $standings;
        }

        $playerPosition = $standings->firstWhere('team_id', $game->team_id)?->position ?? 1;
        $windowStart = max(1, $playerPosition - 2);
        $windowEnd = min($standings->count(), $playerPosition + 2);

        $topIds = $standings->where('position', '<=', 3)->pluck('team_id');
        $windowIds = $standings->whereBetween('position', [$windowStart, $windowEnd])->pluck('team_id');
        $visibleIds = $topIds->merge($windowIds)->unique();

        return $standings->filter(fn ($s) => $visibleIds->contains($s->team_id))->values();
    }

    public function getTeamForms(Collection $standings): array
    {
        $this->backfillMissingForms($standings);

        return $standings->mapWithKeys(fn ($s) => [
            $s->team_id => $s->form ? str_split($s->form) : [],
        ])->all();
    }

    private function backfillMissingForms(Collection $standings): void
    {
        $needsBackfill = $standings->filter(fn ($s) => $s->played > 0 && strlen($s->form ?? '') < min($s->played, 5));

        if ($needsBackfill->isEmpty()) {
            return;
        }

        $first = $needsBackfill->first();
        $matches = GameMatch::where('game_id', $first->game_id)
            ->where('competition_id', $first->competition_id)
            ->where('played', true)
            ->whereNull('cup_tie_id')
            ->orderBy('scheduled_date')
            ->get();

        $matchesByTeam = [];
        foreach ($matches as $match) {
            $matchesByTeam[$match->home_team_id][] = $match;
            $matchesByTeam[$match->away_team_id][] = $match;
        }

        foreach ($needsBackfill as $standing) {
            $teamMatches = $matchesByTeam[$standing->team_id] ?? [];
            $form = '';

            foreach ($teamMatches as $match) {
                $isHome = $match->home_team_id === $standing->team_id;
                $teamScore = $isHome ? $match->home_score : $match->away_score;
                $oppScore = $isHome ? $match->away_score : $match->home_score;

                $form .= $teamScore > $oppScore ? 'W' : ($teamScore < $oppScore ? 'L' : 'D');
            }

            $standing->form = $form !== '' ? substr($form, -5) : null;
            $standing->save();
        }
    }

    public function getTopScorers(string $gameId, string $competitionId): Collection
    {
        // Sum goals per player across the whole competition, regardless of which
        // club they scored each goal for. A mid-season transfer keeps a single
        // cumulative tally rather than splitting into one row per former club.
        $scorerRows = MatchEvent::where('match_events.game_id', $gameId)
            ->where('match_events.event_type', MatchEvent::TYPE_GOAL)
            ->join('game_matches', 'game_matches.id', '=', 'match_events.game_match_id')
            ->where('game_matches.competition_id', $competitionId)
            ->selectRaw('match_events.game_player_id, COUNT(*) as goals')
            ->groupBy('match_events.game_player_id')
            ->orderByDesc('goals')
            ->limit(10)
            ->get();

        if ($scorerRows->isEmpty()) {
            return collect();
        }

        $players = GamePlayer::with(['matchState', 'team'])
            ->whereIn('id', $scorerRows->pluck('game_player_id')->unique())
            ->get()
            ->keyBy('id');

        return $scorerRows->map(function ($row) use ($players) {
            $player = $players[$row->game_player_id] ?? null;
            if (!$player) {
                return null;
            }
            $player = clone $player;
            $player->goals = $row->goals;
            // Attribute to the player's CURRENT club — not the club they scored
            // for — so a signed top scorer shows under your crest (mirrors
            // getBestGoalkeepers and the real Pichichi ranking).
            $player->scorer_team = $player->team;

            return $player;
        })->filter()->sortByDesc('goals')->values();
    }

    /**
     * Best goalkeepers (Zamora-style) for a competition: ranked by goals
     * conceded per appearance, ascending. Per-competition stats are derived
     * from match lineups because game_player_match_state totals are
     * season-wide across all competitions.
     *
     * A minimum-appearances threshold (proportional to the real Zamora
     * trophy's 28/38 La Liga rule) filters out keepers with too few games
     * to be meaningful.
     */
    public function getBestGoalkeepers(string $gameId, string $competitionId): Collection
    {
        $matches = GameMatch::where('game_id', $gameId)
            ->where('competition_id', $competitionId)
            ->where('played', true)
            ->get(['id', 'round_number', 'cup_tie_id', 'home_lineup', 'away_lineup', 'home_score', 'away_score']);

        if ($matches->isEmpty()) {
            return collect();
        }

        $allLineupIds = collect();
        foreach ($matches as $match) {
            if (is_array($match->home_lineup)) {
                $allLineupIds = $allLineupIds->merge($match->home_lineup);
            }
            if (is_array($match->away_lineup)) {
                $allLineupIds = $allLineupIds->merge($match->away_lineup);
            }
        }
        $allLineupIds = $allLineupIds->unique()->values();

        if ($allLineupIds->isEmpty()) {
            return collect();
        }

        $goalkeepers = GamePlayer::with('team')
            ->whereIn('id', $allLineupIds)
            ->where('position', 'Goalkeeper')
            ->get()
            ->keyBy('id');

        if ($goalkeepers->isEmpty()) {
            return collect();
        }

        $stats = [];
        foreach ($matches as $match) {
            $homeLineup = is_array($match->home_lineup) ? $match->home_lineup : [];
            $awayLineup = is_array($match->away_lineup) ? $match->away_lineup : [];

            foreach ($homeLineup as $playerId) {
                if (!$goalkeepers->has($playerId)) {
                    continue;
                }
                $stats[$playerId] ??= ['appearances' => 0, 'goals_conceded' => 0, 'clean_sheets' => 0];
                $stats[$playerId]['appearances']++;
                $stats[$playerId]['goals_conceded'] += (int) $match->away_score;
                if ((int) $match->away_score === 0) {
                    $stats[$playerId]['clean_sheets']++;
                }
            }

            foreach ($awayLineup as $playerId) {
                if (!$goalkeepers->has($playerId)) {
                    continue;
                }
                $stats[$playerId] ??= ['appearances' => 0, 'goals_conceded' => 0, 'clean_sheets' => 0];
                $stats[$playerId]['appearances']++;
                $stats[$playerId]['goals_conceded'] += (int) $match->home_score;
                if ((int) $match->home_score === 0) {
                    $stats[$playerId]['clean_sheets']++;
                }
            }
        }

        // Real Zamora trophy: 28 appearances of a 38-matchday La Liga season.
        // Scale proportionally to matchdays played so far so the rule applies
        // sensibly mid-season and to non-La-Liga competitions (Swiss, groups).
        $playedMatchdays = $matches->whereNull('cup_tie_id')->max('round_number')
            ?? $matches->max('round_number')
            ?? 0;
        $minAppearances = max(1, (int) floor($playedMatchdays * 28 / 38));

        return collect($stats)
            ->filter(fn ($s) => $s['appearances'] >= $minAppearances)
            ->map(function ($s, $gkId) use ($goalkeepers) {
                $gk = clone $goalkeepers[$gkId];
                $gk->appearances_in_competition = $s['appearances'];
                $gk->goals_conceded_in_competition = $s['goals_conceded'];
                $gk->clean_sheets_in_competition = $s['clean_sheets'];
                $gk->goals_per_match = number_format($s['goals_conceded'] / $s['appearances'], 2);
                $gk->scorer_team = $gk->team;

                return $gk;
            })
            ->sort(function ($a, $b) {
                $ratioA = $a->goals_conceded_in_competition / $a->appearances_in_competition;
                $ratioB = $b->goals_conceded_in_competition / $b->appearances_in_competition;
                if ($ratioA !== $ratioB) {
                    return $ratioA <=> $ratioB;
                }
                if ($a->clean_sheets_in_competition !== $b->clean_sheets_in_competition) {
                    return $b->clean_sheets_in_competition <=> $a->clean_sheets_in_competition;
                }

                return $b->appearances_in_competition <=> $a->appearances_in_competition;
            })
            ->take(10)
            ->values();
    }

    public function getKnockoutRounds(Competition $competition, Game $game): Collection
    {
        return collect(LeagueFixtureGenerator::loadKnockoutRounds(
            $competition->id,
            $game->base_season,
            $game->season,
        ));
    }

    public function getKnockoutTies(Game $game, Competition $competition): Collection
    {
        return CupTie::with(['homeTeam', 'awayTeam', 'winner', 'firstLegMatch', 'secondLegMatch', 'competition', 'game'])
            ->where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->orderBy('bracket_position')
            ->orderBy('id')
            ->get()
            ->groupBy('round_number');
    }

    public function isLeaguePhaseComplete(Game $game, Competition $competition, Collection $standings): bool
    {
        return !GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->whereNull('cup_tie_id')
            ->where('played', false)
            ->exists() && $standings->first()?->played > 0;
    }

    public function findPlayerTie(Collection $rounds, Collection $tiesByRound, string $teamId): ?CupTie
    {
        foreach ($rounds->reverse() as $round) {
            $ties = $tiesByRound->get($round->round, collect());
            $playerTie = $ties->first(fn ($tie) => $tie->involvesTeam($teamId));
            if ($playerTie) {
                return $playerTie;
            }
        }

        return null;
    }

    public function resolveCupStatus(?CupTie $playerTie, string $teamId, int $maxRound): string
    {
        if (!$playerTie) {
            return 'not_entered';
        }

        if (!$playerTie->completed) {
            return 'active';
        }

        if ($playerTie->winner_id === $teamId) {
            return $playerTie->round_number === $maxRound ? 'champion' : 'advanced';
        }

        return 'eliminated';
    }

    /**
     * Tournament mode wants the full table (or the player's full group when
     * grouped); career mode dashboards get the abridged window.
     */
    private function getDashboardStandings(Game $game, Competition $competition): Collection
    {
        if (!$game->isTournamentMode()) {
            return $this->getAbridgedStandings($game, $competition);
        }

        $standings = $this->getStandings($game, $competition);
        $playerGroup = $standings->firstWhere('team_id', $game->team_id)?->group_label;

        return $playerGroup
            ? $standings->where('group_label', $playerGroup)->values()
            : $standings;
    }

    /**
     * Decide what the dashboard's standings/path card should render based on
     * the next match the user will play. Driving off the next match (rather
     * than the primary competition) lets the dashboard switch to the Swiss
     * table on a Champions League matchday or to a cup-path view when the
     * next fixture is a knockout tie.
     *
     * @return array{
     *   mode: 'league'|'knockout'|'none',
     *   competition: ?Competition,
     *   standings: ?Collection,
     *   playerTie: ?CupTie,
     *   roundsRemaining: ?Collection,
     *   finalVenue: ?string,
     * }
     */
    public function resolveDashboardContext(Game $game, ?GameMatch $nextMatch): array
    {
        $empty = [
            'mode' => 'none',
            'competition' => null,
            'standings' => null,
            'title' => null,
            'playerTie' => null,
            'roundsRemaining' => null,
            'finalVenue' => null,
        ];

        if (!$nextMatch) {
            return $empty;
        }

        $competition = $nextMatch->relationLoaded('competition')
            ? $nextMatch->competition
            : Competition::find($nextMatch->competition_id);

        if (!$competition) {
            return $empty;
        }

        if ($nextMatch->cup_tie_id === null) {
            $standings = $this->getDashboardStandings($game, $competition);
            $playerGroup = $standings->firstWhere('team_id', $game->team_id)?->group_label;

            $title = ($game->isTournamentMode() && $playerGroup)
                ? __('game.group') . ' ' . $playerGroup
                : $competition->name;

            return [
                'mode' => 'league',
                'competition' => $competition,
                'standings' => $standings,
                'title' => $title,
                'playerTie' => null,
                'roundsRemaining' => null,
                'finalVenue' => null,
            ];
        }

        return $this->resolveKnockoutContext($game, $competition);
    }

    /**
     * @return array{
     *   mode: 'knockout',
     *   competition: Competition,
     *   standings: null,
     *   playerTie: ?CupTie,
     *   roundsRemaining: Collection,
     *   finalVenue: ?string,
     * }
     */
    private function resolveKnockoutContext(Game $game, Competition $competition): array
    {
        $rounds = $this->getKnockoutRounds($competition, $game);
        $ties = $this->getKnockoutTies($game, $competition);
        $playerTie = $this->findPlayerTie($rounds, $ties, $game->team_id);

        $currentRound = $playerTie?->round_number;
        $finalRound = $rounds->max('round');

        $roundsRemaining = $rounds
            ->filter(fn ($round) => $currentRound !== null && $round->round >= $currentRound)
            ->map(fn ($round) => [
                'round' => $round->round,
                'label' => __($round->name),
                'isCurrent' => $round->round === $currentRound,
                'isFinal' => $round->round === $finalRound,
            ])
            ->values();

        return [
            'mode' => 'knockout',
            'competition' => $competition,
            'standings' => null,
            'title' => $competition->name,
            'playerTie' => $playerTie,
            'roundsRemaining' => $roundsRemaining,
            'finalVenue' => $this->resolveFinalVenue($competition, $ties, $finalRound),
        ];
    }

    /**
     * Domestic cups declare a fixed final venue in config (La Cartuja,
     * Wembley); for European competitions the venue is only known once the
     * final tie has been drawn and its GameMatch carries a
     * `neutral_venue_name`. Otherwise return null and let the view render
     * "TBD".
     */
    private function resolveFinalVenue(Competition $competition, Collection $tiesByRound, ?int $finalRound): ?string
    {
        $declared = $this->countryConfig->neutralVenue($competition->id, 'cup.final');
        if ($declared) {
            return $declared['name'];
        }

        if ($finalRound === null) {
            return null;
        }

        return $tiesByRound->get($finalRound, collect())->first()?->firstLegMatch?->neutral_venue_name;
    }
}
