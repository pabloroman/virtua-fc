<?php

namespace App\Modules\Match\DTOs;

/**
 * Lineups payload for the match-summary lineups/ratings tab.
 *
 * Shape mirrors what the `liveMatch` Alpine factory provides to the shared
 * `partials/live-match/lineups-roster.blade.php` markup, so the same partial
 * renders post-match without modification. The
 * `resources/js/match-summary-lineups.js` factory consumes this directly.
 *
 *   homeRoster, awayRoster — starting XI in display order. Each entry:
 *     { id, name, positionAbbr, positionGroup, performance? }
 *     (from LiveMatchLineupPresenter::displayRoster)
 *
 *   subInPlayers — players who came on. Each entry includes its own teamId
 *     so the shared ratings glue can apply per-side bonuses (clean sheet,
 *     winning-team edge) correctly without a userTeamId/opponent split.
 *
 *   events / extraTimeEvents — MatchResimulationService::formatMatchEvents()
 *     output, split the same way ShowLiveMatch splits them: minute ≤93 in
 *     `events`, minute >93 in `extraTimeEvents`. Required because the shared
 *     ratings-glue module unions them but reads `homeScore`/`awayScore` as
 *     90-min only — matching this contract keeps post-match ratings identical
 *     to the live-match view for the same fixture.
 *
 *   homeFormation / awayFormation — formation labels ('4-3-3') for the
 *     PHP-level formation header in the partial.
 *
 *   homeScore / awayScore — 90-minute scores (no ET), passed to the ratings
 *     glue as `finalHomeScore` / `finalAwayScore`.
 */
readonly class MatchLineupsViewModel
{
    /**
     * @param array<int, array<string, mixed>> $homeRoster
     * @param array<int, array<string, mixed>> $awayRoster
     * @param array<int, array<string, mixed>> $subInPlayers
     * @param array<int, array<string, mixed>> $events
     * @param array<int, array<string, mixed>> $extraTimeEvents
     */
    public function __construct(
        public array $homeRoster,
        public array $awayRoster,
        public array $subInPlayers,
        public array $events,
        public array $extraTimeEvents,
        public ?string $homeFormation,
        public ?string $awayFormation,
        public string $homeTeamId,
        public string $awayTeamId,
        public int $homeScore,
        public int $awayScore,
    ) {}

    public function hasAny(): bool
    {
        return count($this->homeRoster) > 0 || count($this->awayRoster) > 0;
    }
}
