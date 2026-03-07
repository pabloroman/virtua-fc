<?php

namespace App\Modules\Season\Services;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Processors\ContinentalAndCupInitProcessor;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\OnboardingResetProcessor;
use App\Modules\Season\Processors\PreSeasonFixtureProcessor;
use App\Modules\Season\Processors\SquadCapEnforcementProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Modules\Season\Processors\YouthAcademySetupProcessor;
use App\Models\Game;
use Illuminate\Support\Facades\Log;

/**
 * Sets up the new season: fixtures, standings, budgets, competitions,
 * academy evaluation, and onboarding. Used by both new game creation
 * and season transitions.
 */
class SeasonSetupPipeline
{
    /** @var SeasonProcessor[] */
    private array $processors = [];

    public function __construct(
        LeagueFixtureProcessor $fixtureGeneration,
        StandingsResetProcessor $standingsReset,
        BudgetProjectionProcessor $budgetProjection,
        YouthAcademySetupProcessor $youthAcademySetup,
        ContinentalAndCupInitProcessor $competitionInitialization,
        SquadCapEnforcementProcessor $squadCapEnforcement,
        PreSeasonFixtureProcessor $preSeasonFixture,
        OnboardingResetProcessor $onboardingReset,
    ) {
        $this->processors = [
            $fixtureGeneration,
            $standingsReset,
            $budgetProjection,
            $youthAcademySetup,
            $competitionInitialization,
            $squadCapEnforcement,
            $preSeasonFixture,
            $onboardingReset,
        ];

        usort($this->processors, fn ($a, $b) => $a->priority() <=> $b->priority());
    }

    /**
     * Set up the new season using pre-built transition data.
     */
    public function run(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        foreach ($this->processors as $processor) {
            try {
                $data = $processor->process($game, $data);
            } catch (\Throwable $e) {
                Log::error('Season setup processor failed', [
                    'processor' => get_class($processor),
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $data;
    }

    /**
     * @return SeasonProcessor[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
}
