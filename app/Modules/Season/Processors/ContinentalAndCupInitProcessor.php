<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Season\Services\SeasonInitializationService;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameStanding;

/**
 * Initializes Swiss format competitions (UCL) and conducts cup draws
 * after qualification processors have determined the new season's participants.
 *
 * Also finalizes current_date to the earliest fixture across all competitions
 * (league fixtures are already created by LeagueFixtureProcessor at priority 30).
 *
 * Priority: 106 (runs after UefaQualificationProcessor at 105)
 */
class ContinentalAndCupInitProcessor implements SeasonEndProcessor
{
    public function __construct(
        private SeasonInitializationService $service,
        private CountryConfig $countryConfig,
    ) {}

    public function priority(): int
    {
        return 106;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $countryCode = $game->country ?? 'ES';

        // Initialize Swiss format competitions (UCL)
        $this->initializeSwissCompetitions($game, $data, $countryCode);

        // Conduct cup draws (ESPCUP round 1, ESPSUP semifinal)
        $this->service->conductCupDraws($game->id, $countryCode);

        // Set current_date to earliest fixture across all competitions
        $this->finalizeCurrentDate($game);

        return $data;
    }

    private function initializeSwissCompetitions(Game $game, SeasonTransitionData $data, string $countryCode): void
    {
        $swissIds = $this->countryConfig->swissFormatCompetitionIds($countryCode);
        $swissPotData = $data->getMetadata('swissPotData', []);

        foreach ($swissIds as $competitionId) {
            // Delete stale standings from previous season (teams may have changed)
            // On initial season the table is empty so this is a no-op
            GameStanding::where('game_id', $game->id)
                ->where('competition_id', $competitionId)
                ->delete();

            // Use explicit pot data when available (initial season from JSON),
            // otherwise null triggers auto-assignment by market value
            $teamsWithPots = $swissPotData[$competitionId] ?? null;

            // Initialize Swiss fixtures + standings (skips if team doesn't participate)
            $this->service->initializeSwissCompetition(
                $game->id,
                $game->team_id,
                $competitionId,
                $data->newSeason,
                $teamsWithPots,
            );
        }
    }

    /**
     * Set the game's current_date to the earliest fixture across all competitions.
     * This runs after all fixture generation (league at priority 30, Swiss/cup here at 106).
     */
    private function finalizeCurrentDate(Game $game): void
    {
        $earliestMatch = GameMatch::where('game_id', $game->id)
            ->orderBy('scheduled_date')
            ->first();

        if ($earliestMatch) {
            $game->update([
                'current_date' => $earliestMatch->scheduled_date->toDateString(),
            ]);
        }
    }
}
