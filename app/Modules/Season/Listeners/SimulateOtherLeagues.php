<?php

namespace App\Modules\Season\Listeners;

use App\Events\SeasonCompleted;
use App\Models\Competition;
use App\Models\GameStanding;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Finance\Services\SeasonSimulationService;

/**
 * When the user's league finishes, simulate the matching sibling/other-division
 * league so the season-end view can show "what would have happened" data.
 *
 * Scopes to whichever league in the user's country is paired with their
 * current competition by a promotion/relegation rule — that's the league
 * whose standings drive what gets promoted into / relegated from the user's
 * league.
 */
class SimulateOtherLeagues
{
    public function __construct(
        private readonly CountryConfig $countryConfig,
        private readonly SeasonSimulationService $simulationService,
    ) {}

    public function handle(SeasonCompleted $event): void
    {
        $game = $event->game;

        $otherCompetitionId = $this->pickOtherCompetition($game->country, $game->competition_id);
        if ($otherCompetitionId === null) {
            return;
        }

        $hasRealStandings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $otherCompetitionId)
            ->exists();

        if ($hasRealStandings) {
            return;
        }

        $otherCompetition = Competition::find($otherCompetitionId);
        if (!$otherCompetition) {
            return;
        }

        $this->simulationService->simulateLeague($game, $otherCompetition);
    }

    /**
     * Find the league that's the other end of a promotion/relegation rule
     * the user's league participates in. For ESP1, that's ESP2; for ESP2,
     * the top of the pair is ESP1 (preferred — what gets promoted into the
     * user's view) but ESP3A/B also share a rule.
     */
    private function pickOtherCompetition(string $country, string $competitionId): ?string
    {
        foreach ($this->countryConfig->promotions($country) as $rule) {
            $top = $rule['top_division'];
            $bottom = $rule['bottom_division'];
            $sources = $rule['playoff_source_divisions'] ?? [$bottom];

            if ($top === $competitionId) {
                return $sources[0];
            }
            if (in_array($competitionId, $sources, true)) {
                return $top;
            }
        }
        return null;
    }
}
