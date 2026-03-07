<?php

namespace App\Modules\Season\Services;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\ContractRenewalProcessor;
use App\Modules\Season\Processors\LoanReturnProcessor;
use App\Modules\Season\Processors\PlayerDevelopmentProcessor;
use App\Modules\Season\Processors\PlayerRetirementProcessor;
use App\Modules\Season\Processors\PreContractTransferProcessor;
use App\Modules\Season\Processors\PromotionRelegationProcessor;
use App\Modules\Season\Processors\ReputationUpdateProcessor;
use App\Modules\Season\Processors\SeasonArchiveProcessor;
use App\Modules\Season\Processors\SeasonSettlementProcessor;
use App\Modules\Season\Processors\SeasonSimulationProcessor;
use App\Modules\Season\Processors\SquadReplenishmentProcessor;
use App\Modules\Season\Processors\StatsResetProcessor;
use App\Modules\Season\Processors\SupercupQualificationProcessor;
use App\Modules\Season\Processors\TransferMarketResetProcessor;
use App\Modules\Season\Processors\UefaQualificationProcessor;
use App\Modules\Season\Processors\YouthAcademyClosingProcessor;
use App\Models\Game;
use Illuminate\Support\Facades\Log;

/**
 * Closes the old season: archiving, settlements, contracts, retirements,
 * promotions/relegations, and qualification for next season's competitions.
 */
class SeasonClosingPipeline
{
    /** @var SeasonProcessor[] */
    private array $processors = [];

    public function __construct(
        SeasonArchiveProcessor $seasonArchive,
        LoanReturnProcessor $loanReturn,
        PreContractTransferProcessor $preContractTransfer,
        ContractExpirationProcessor $contractExpiration,
        ContractRenewalProcessor $contractRenewal,
        PlayerRetirementProcessor $playerRetirement,
        SquadReplenishmentProcessor $squadReplenishment,
        PlayerDevelopmentProcessor $playerDevelopment,
        SeasonSettlementProcessor $seasonSettlement,
        StatsResetProcessor $statsReset,
        TransferMarketResetProcessor $transferMarketReset,
        SeasonSimulationProcessor $seasonSimulation,
        SupercupQualificationProcessor $supercupQualification,
        PromotionRelegationProcessor $promotionRelegation,
        ReputationUpdateProcessor $reputationUpdate,
        UefaQualificationProcessor $uefaQualification,
        YouthAcademyClosingProcessor $youthAcademyClosing,
    ) {
        $this->processors = [
            $seasonArchive,
            $loanReturn,
            $preContractTransfer,
            $contractExpiration,
            $contractRenewal,
            $playerRetirement,
            $squadReplenishment,
            $playerDevelopment,
            $seasonSettlement,
            $statsReset,
            $transferMarketReset,
            $seasonSimulation,
            $supercupQualification,
            $promotionRelegation,
            $reputationUpdate,
            $uefaQualification,
            $youthAcademyClosing,
        ];

        usort($this->processors, fn ($a, $b) => $a->priority() <=> $b->priority());
    }

    /**
     * Close the old season and return transition data for setup.
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
            try {
                $data = $processor->process($game, $data);
            } catch (\Throwable $e) {
                Log::error('Season closing processor failed', [
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
     * Increment the season year.
     */
    private function incrementSeason(string $season): string
    {
        if (str_contains($season, '-')) {
            $parts = explode('-', $season);
            $startYear = (int) $parts[0] + 1;
            $endYear = (int) $parts[1] + 1;

            return $startYear.'-'.str_pad((string) $endYear, 2, '0', STR_PAD_LEFT);
        }

        return (string) ((int) $season + 1);
    }

    /**
     * @return SeasonProcessor[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }
}
