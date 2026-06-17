<?php

namespace App\Modules\Season\Services;

use App\Modules\Manager\Processors\ApplyPendingTeamSwitchProcessor;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Processors\ContinentalAndCupInitProcessor;
use App\Modules\Season\Processors\DefaultInvestmentProcessor;
use App\Modules\Season\Processors\GenerateNamingRightsOffersProcessor;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\NewSeasonResetProcessor;
use App\Modules\Season\Processors\PreSeasonFixtureProcessor;
use App\Modules\Season\Processors\SeasonTicketDefaultsProcessor;
use App\Modules\Season\Processors\SeedInitialNamingDealProcessor;
use App\Modules\Season\Processors\SquadRegistrationEnforcementProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Modules\Season\Processors\TransferMarketSeedProcessor;
use App\Modules\Season\Processors\UefaSuperCupQualificationProcessor;
use App\Modules\Season\Processors\YouthAcademyPromotionProcessor;
use App\Models\Game;
use App\Support\QueryProfiler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sets up the new season: fixtures, standings, budgets, competitions,
 * and new-season setup. Used by both new game creation and season transitions.
 */
class SeasonSetupPipeline
{
    /** @var SeasonProcessor[] */
    private array $processors = [];

    public function __construct(
        ApplyPendingTeamSwitchProcessor $applyPendingTeamSwitch,
        YouthAcademyPromotionProcessor $academyPromotion,
        LeagueFixtureProcessor $fixtureGeneration,
        StandingsResetProcessor $standingsReset,
        BudgetProjectionProcessor $budgetProjection,
        DefaultInvestmentProcessor $defaultInvestment,
        ContinentalAndCupInitProcessor $competitionInitialization,
        UefaSuperCupQualificationProcessor $uefaSuperCup,
        SquadRegistrationEnforcementProcessor $squadRegistration,
        PreSeasonFixtureProcessor $preSeasonFixture,
        NewSeasonResetProcessor $newSeasonReset,
        TransferMarketSeedProcessor $transferMarketSeed,
        SeasonTicketDefaultsProcessor $seasonTicketDefaults,
        SeedInitialNamingDealProcessor $seedInitialNamingDeal,
        GenerateNamingRightsOffersProcessor $namingRightsOffers,
    ) {
        $this->processors = [
            $applyPendingTeamSwitch,
            $academyPromotion,
            $fixtureGeneration,
            $standingsReset,
            $budgetProjection,
            $defaultInvestment,
            $competitionInitialization,
            $uefaSuperCup,
            $squadRegistration,
            $preSeasonFixture,
            $newSeasonReset,
            $transferMarketSeed,
            $seasonTicketDefaults,
            $seedInitialNamingDeal,
            $namingRightsOffers,
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

            $processorName = class_basename($processor);
            $profile = QueryProfiler::start();

            try {
                $data = DB::transaction(fn () => $processor->process($game, $data));
            } catch (\Throwable $e) {
                Log::error('Season setup processor failed', [
                    'processor' => get_class($processor),
                    'step' => $globalStep,
                    'game_id' => $game->id,
                    'user_id' => $game->user_id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $stats = $profile->snapshot();
            Log::info(
                "[SeasonSetup {$game->id}] {$processorName} (priority {$processor->priority()}) completed in {$stats['wall_ms']}ms",
                $stats,
            );

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
