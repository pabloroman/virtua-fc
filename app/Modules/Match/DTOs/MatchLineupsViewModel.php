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
 *   subInPlayers — players who came on. Each entry:
 *     { id, name, positionAbbr, positionGroup, performance?, teamId }
 *     teamId is set explicitly so per-side rating bonuses apply correctly.
 *
 *   events — MatchResimulationService::formatMatchEvents() output, including
 *     paired assists on goal entries.
 *
 *   homeFormation / awayFormation — formation labels ('4-3-3') for the
 *     PHP-level formation header in the partial.
 *
 *   homeTeamId / awayTeamId, homeScore / awayScore — fed straight into the
 *     ratings glue.
 */
readonly class MatchLineupsViewModel
{
    /**
     * @param array<int, array<string, mixed>> $homeRoster
     * @param array<int, array<string, mixed>> $awayRoster
     * @param array<int, array<string, mixed>> $subInPlayers
     * @param array<int, array<string, mixed>> $events
     */
    public function __construct(
        public array $homeRoster,
        public array $awayRoster,
        public array $subInPlayers,
        public array $events,
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
