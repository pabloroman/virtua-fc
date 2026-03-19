<?php

namespace App\Modules\Season\Services;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Processors\ContinentalAndCupInitProcessor;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\NewSeasonResetProcessor;
use App\Modules\Season\Processors\PreSeasonFixtureProcessor;
use App\Modules\Season\Processors\SquadCapEnforcementProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Modules\Season\Processors\YouthAcademySetupProcessor;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sets up the new season: fixtures, standings, budgets, competitions,
 * academy evaluation, and new-season setup. Used by both new game creation
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
        NewSeasonResetProcessor $newSeasonReset,
    ) {
        $this->processors = [
            $fixtureGeneration,
            $standingsReset,
            $budgetProjection,
            $youthAcademySetup,
            $competitionInitialization,
            $squadCapEnforcement,
            $preSeasonFixture,
            $newSeasonReset,
        ];

        usort($this->processors, fn ($a, $b) => $a->priority() <=> $b->priority());
    }

    /**
     * Set up the new season using pre-built transition data.
     *
     * @param  int  $stepOffset  Global step offset (closing pipeline processor count)
     * @param  int  $startFromStep  Global step index to resume from (skip steps <= this value)
     */
    public function run(Game $game, SeasonTransitionData $data, int $stepOffset = 0, int $startFromStep = -1): SeasonTransitionData
    {
        foreach ($this->processors as $index => $processor) {
            $globalStep = $stepOffset + $index;

            if ($globalStep <= $startFromStep) {
                continue;
            }

            try {
                $data = DB::transaction(fn () => $processor->process($game, $data));
            } catch (\Throwable $e) {
                Log::error('Season setup processor failed', [
                    'processor' => get_class($processor),
                    'step' => $globalStep,
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Checkpoint: persist completed step and DTO for crash recovery
            $game->updateQuietly([
                'season_transition_step' => $globalStep,
                'season_transition_data' => $data,
            ]);
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
