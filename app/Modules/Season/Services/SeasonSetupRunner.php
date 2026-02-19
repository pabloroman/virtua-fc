<?php

namespace App\Modules\Season\Services;

use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Processors\ContinentalAndCupInitProcessor;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Models\Game;

/**
 * Runs the 4 shared "setup" processors used by both initial game creation
 * (SetupNewGame) and season transitions (SeasonEndPipeline).
 *
 * Processors execute in priority order:
 *   LeagueFixtureProcessor (30) → StandingsResetProcessor (40) →
 *   BudgetProjectionProcessor (50) → ContinentalAndCupInitProcessor (106)
 */
class SeasonSetupRunner
{
    public function __construct(
        private readonly LeagueFixtureProcessor $fixtureProcessor,
        private readonly StandingsResetProcessor $standingsProcessor,
        private readonly BudgetProjectionProcessor $budgetProcessor,
        private readonly ContinentalAndCupInitProcessor $cupInitProcessor,
    ) {}

    public function run(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $data = $this->fixtureProcessor->process($game, $data);
        $data = $this->standingsProcessor->process($game, $data);
        $data = $this->budgetProcessor->process($game, $data);
        $data = $this->cupInitProcessor->process($game, $data);

        return $data;
    }
}
