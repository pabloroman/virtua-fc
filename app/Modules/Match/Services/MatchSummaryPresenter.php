<?php

namespace App\Modules\Match\Services;

use App\Models\GameMatch;
use App\Models\MatchEvent;
use App\Modules\Match\DTOs\MatchLineupsViewModel;
use App\Modules\Match\DTOs\MatchSummaryViewModel;
use App\Support\LiveMatchLineupPresenter;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the prepared view-model for the shared match-summary partial.
 *
 * Two modes:
 *   - 'compact': scoreline + scorers only (used by the matchday results list)
 *   - 'full':    + MVP + lineups/ratings (fast-mode focal card and the
 *                dedicated match-summary page)
 */
class MatchSummaryPresenter
{
    public const MODE_COMPACT = 'compact';

    public const MODE_FULL = 'full';

    public function present(GameMatch $match, string $mode = self::MODE_COMPACT): MatchSummaryViewModel
    {
        // ET-inclusive score: 90-min score + ET goals (stored separately on
        // home_score_et / away_score_et).
        $homeTotal = (int) $match->home_score + (int) ($match->home_score_et ?? 0);
        $awayTotal = (int) $match->away_score + (int) ($match->away_score_et ?? 0);
        $hasPenalties = $match->home_score_penalties !== null;

        [$homeScorers, $awayScorers] = $this->buildScorerLists($match);

        if ($mode !== self::MODE_FULL) {
            return new MatchSummaryViewModel(
                homeTotal: $homeTotal,
                awayTotal: $awayTotal,
                hasPenalties: $hasPenalties,
                homeScorers: $homeScorers,
                awayScorers: $awayScorers,
            );
        }

        return new MatchSummaryViewModel(
            homeTotal: $homeTotal,
            awayTotal: $awayTotal,
            hasPenalties: $hasPenalties,
            homeScorers: $homeScorers,
            awayScorers: $awayScorers,
            mvp: $this->buildMvp($match),
            lineups: $this->buildLineups($match),
        );
    }

    /**
     * Group goal events by player and join their minutes ("23', 67'"), with
     * an "(og)" suffix on own-goal minutes.
     *
     * Own goals are stored under the conceding team (the side whose player
     * kicked it in) but display under the team that benefits.
     *
     * @return array{0:array<int,array{name:string,minutes:string}>,1:array<int,array{name:string,minutes:string}>}
     */
    private function buildScorerLists(GameMatch $match): array
    {
        $goalEvents = $match->events->filter(
            fn (MatchEvent $e) => in_array($e->event_type, [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_OWN_GOAL], true)
        );

        // Scorer names come from the eager-loaded events.gamePlayer relation —
        // every caller (ShowMatchSummary, FastModeService, CalendarService) loads it.
        $beneficiaryTeamId = function (MatchEvent $event) use ($match): string {
            if ($event->event_type === MatchEvent::TYPE_OWN_GOAL) {
                return $event->team_id === $match->home_team_id
                    ? $match->away_team_id
                    : $match->home_team_id;
            }

            return $event->team_id;
        };

        $format = fn ($events) => $events
            ->groupBy(fn (MatchEvent $e) => $e->gamePlayer?->name ?? '—')
            ->map(function ($playerEvents, $name) {
                $minutes = $playerEvents
                    ->map(function (MatchEvent $e) {
                        $label = $e->minute . "'";
                        if ($e->event_type === MatchEvent::TYPE_OWN_GOAL) {
                            $label .= ' ' . __('game.og');
                        }
                        return $label;
                    })
                    ->implode(', ');

                return ['name' => $name, 'minutes' => $minutes];
            })
            ->values()
            ->all();

        return [
            $format($goalEvents->filter(fn (MatchEvent $e) => $beneficiaryTeamId($e) === $match->home_team_id)),
            $format($goalEvents->filter(fn (MatchEvent $e) => $beneficiaryTeamId($e) === $match->away_team_id)),
        ];
    }

    /**
     * @return array{name:string, side:string}|null
     */
    private function buildMvp(GameMatch $match): ?array
    {
        $mvp = $match->mvpPlayer;
        if (! $mvp) {
            return null;
        }

        return [
            'name' => $mvp->name,
            'side' => $mvp->team_id === $match->home_team_id ? 'home' : 'away',
        ];
    }

    /**
     * Build the lineups/ratings payload consumed by `matchSummaryLineups` —
     * the post-match twin of the live-match factory. Roster shape comes from
     * `LiveMatchLineupPresenter::displayRoster()` and events go through
     * `MatchResimulationService::formatMatchEvents()`, so the data lines up
     * with what `partials/live-match/lineups-roster.blade.php` expects when
     * mounted under either factory.
     */
    private function buildLineups(GameMatch $match): MatchLineupsViewModel
    {
        $performances = Cache::get("match_performances:{$match->id}", []);

        // Sub-ins feed the rating calc only; team_id from the persisted
        // substitutions JSON gets reattached so per-team bonuses apply.
        $subInIds = [];
        $subInTeamMap = [];
        foreach ($match->substitutions ?? [] as $sub) {
            $playerInId = $sub['player_in_id'] ?? null;
            if (! $playerInId) {
                continue;
            }
            $subInIds[] = $playerInId;
            $subInTeamMap[$playerInId] = $sub['team_id'] ?? null;
        }

        // Single round trip for home XI + away XI + sub-ins.
        $rosters = LiveMatchLineupPresenter::displayRosters([
            'home' => $match->home_lineup ?? [],
            'away' => $match->away_lineup ?? [],
            'subIns' => $subInIds,
        ], $performances);

        $subInPlayers = array_map(
            fn (array $entry) => $entry + ['teamId' => $subInTeamMap[$entry['id']] ?? null],
            $rosters['subIns'],
        );

        // Mirror the live-match split: regular events (≤93') vs ET (>93').
        // The shared ratings-glue module unions them but reads finalHomeScore/
        // finalAwayScore as 90-min only, matching what ShowLiveMatch passes.
        $regularEvents = $match->events->filter(fn (MatchEvent $e) => $e->minute <= 93);
        $etEvents = $match->events->filter(fn (MatchEvent $e) => $e->minute > 93);

        return new MatchLineupsViewModel(
            homeRoster: $rosters['home'],
            awayRoster: $rosters['away'],
            subInPlayers: $subInPlayers,
            events: MatchResimulationService::formatMatchEvents($regularEvents),
            extraTimeEvents: MatchResimulationService::formatMatchEvents($etEvents),
            homeFormation: $match->home_formation,
            awayFormation: $match->away_formation,
            homeTeamId: $match->home_team_id,
            awayTeamId: $match->away_team_id,
            homeScore: (int) $match->home_score,
            awayScore: (int) $match->away_score,
        );
    }
}
