<?php

namespace App\Game\Services;

use App\Game\Contracts\SeasonEndProcessor;
use App\Game\DTO\SeasonTransitionData;
use App\Game\Processors\ContractRenewalProcessor;
use App\Game\Processors\FinancialProcessor;
use App\Game\Processors\FinancialResetProcessor;
use App\Game\Processors\FixtureGenerationProcessor;
use App\Game\Processors\PlayerDevelopmentProcessor;
use App\Game\Processors\PreContractTransferProcessor;
use App\Game\Processors\PromotionRelegationProcessor;
use App\Game\Processors\SeasonArchiveProcessor;
use App\Game\Processors\StandingsResetProcessor;
use App\Game\Processors\StatsResetProcessor;
use App\Game\Processors\SupercopaQualificationProcessor;
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
        PreContractTransferProcessor $preContractTransfer,
        ContractRenewalProcessor $contractRenewal,
        PlayerDevelopmentProcessor $playerDevelopment,
        FinancialProcessor $financial,
        StatsResetProcessor $statsReset,
        SupercopaQualificationProcessor $supercopaQualification,
        PromotionRelegationProcessor $promotionRelegation,
        FixtureGenerationProcessor $fixtureGeneration,
        StandingsResetProcessor $standingsReset,
        FinancialResetProcessor $financialReset,
    ) {
        $this->processors = [
            $seasonArchive,
            $preContractTransfer,
            $contractRenewal,
            $playerDevelopment,
            $financial,
            $statsReset,
            $supercopaQualification,
            $promotionRelegation,
            $fixtureGeneration,
            $standingsReset,
            $financialReset,
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
            return $startYear . '-' . str_pad($endYear, 2, '0', STR_PAD_LEFT);
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
