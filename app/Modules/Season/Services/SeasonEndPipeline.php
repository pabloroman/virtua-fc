<?php

namespace App\Modules\Season\Services;

use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\BudgetProjectionProcessor;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\ContractRenewalProcessor;
use App\Modules\Season\Processors\LeagueFixtureProcessor;
use App\Modules\Season\Processors\LoanReturnProcessor;
use App\Modules\Season\Processors\PlayerDevelopmentProcessor;
use App\Modules\Season\Processors\PlayerRetirementProcessor;
use App\Modules\Season\Processors\PreContractTransferProcessor;
use App\Modules\Season\Processors\PromotionRelegationProcessor;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use App\Modules\Season\Processors\SeasonSettlementProcessor;
use App\Modules\Season\Processors\SeasonSimulationProcessor;
use App\Modules\Season\Processors\StandingsResetProcessor;
use App\Modules\Season\Processors\StatsResetProcessor;
use App\Modules\Season\Processors\SupercupQualificationProcessor;
use App\Modules\Season\Processors\UefaQualificationProcessor;
use App\Modules\Season\Processors\ContinentalAndCupInitProcessor;
use App\Modules\Season\Processors\OnboardingResetProcessor;
use App\Modules\Season\Processors\YouthAcademyProcessor;
use App\Models\Game;

/**
 * Orchestrates season end processors in priority order.
 */
class SeasonEndPipeline
{
    /** @var SeasonEndProcessor[] */
    private array $processors = [];

    public function __construct(
        SeasonArchiveProcessor $seasonArchive,
        LoanReturnProcessor $loanReturn,
        PreContractTransferProcessor $preContractTransfer,
        ContractExpirationProcessor $contractExpiration,
        ContractRenewalProcessor $contractRenewal,
        PlayerRetirementProcessor $playerRetirement,
        PlayerDevelopmentProcessor $playerDevelopment,
        SeasonSettlementProcessor $seasonSettlement,
        StatsResetProcessor $statsReset,
        SupercupQualificationProcessor $supercupQualification,
        UefaQualificationProcessor $uefaQualification,
        SeasonSimulationProcessor $seasonSimulation,
        PromotionRelegationProcessor $promotionRelegation,
        LeagueFixtureProcessor $fixtureGeneration,
        StandingsResetProcessor $standingsReset,
        BudgetProjectionProcessor $budgetProjection,
        YouthAcademyProcessor $youthAcademy,
        ContinentalAndCupInitProcessor $competitionInitialization,
        OnboardingResetProcessor $onboardingReset,
    ) {
        $this->processors = [
            $seasonArchive,
            $loanReturn,
            $preContractTransfer,
            $contractExpiration,
            $contractRenewal,
            $playerRetirement,
            $playerDevelopment,
            $seasonSettlement,
            $statsReset,
            $supercupQualification,
            $uefaQualification,
            $seasonSimulation,
            $promotionRelegation,
            $fixtureGeneration,
            $standingsReset,
            $budgetProjection,
            $youthAcademy,
            $competitionInitialization,
            $onboardingReset,
        ];

        // Sort by priority (lower numbers first)
        usort($this->processors, fn ($a, $b) => $a->priority() <=> $b->priority());
    }

    /**
     * Run all processors for the season transition.
     */
    public function run(Game $game): SeasonTransitionData
    {
        $oldSeason = $game->season;
        $newSeason = $this->incrementSeason($oldSeason);

        $data = new SeasonTransitionData(
            oldSeason: $oldSeason,
            newSeason: $newSeason,
            competitionId: $game->competition_id,
        );

        foreach ($this->processors as $processor) {
            $data = $processor->process($game, $data);
        }

        return $data;
    }

    /**
     * Increment the season year.
     */
    private function incrementSeason(string $season): string
    {
        // Handle formats like "2024" or "2024-25"
        if (str_contains($season, '-')) {
            $parts = explode('-', $season);
            $startYear = (int) $parts[0] + 1;
            $endYear = (int) $parts[1] + 1;

            return $startYear.'-'.str_pad((string) $endYear, 2, '0', STR_PAD_LEFT);
        }

        return (string) ((int) $season + 1);
    }

    /**
     * Get all registered processors.
     *
     * @return SeasonEndProcessor[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
}
