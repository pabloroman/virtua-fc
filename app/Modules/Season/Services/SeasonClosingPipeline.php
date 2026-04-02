<?php

namespace App\Modules\Season\Services;

use App\Modules\Manager\Processors\TrophyRecordingProcessor;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\AIFreeAgentSigningProcessor;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\ContractRenewalProcessor;
use App\Modules\Season\Processors\LeaderboardStatsProcessor;
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
use Illuminate\Support\Facades\DB;
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
        LoanReturnProcessor $loanReturn,
        TrophyRecordingProcessor $trophyRecording,
        LeaderboardStatsProcessor $leaderboardStats,
        ContractExpirationProcessor $contractExpiration,
        SeasonArchiveProcessor $seasonArchive,
        PreContractTransferProcessor $preContractTransfer,
        ContractRenewalProcessor $contractRenewal,
        PlayerRetirementProcessor $playerRetirement,
        SquadReplenishmentProcessor $squadReplenishment,
        AIFreeAgentSigningProcessor $aiFreeAgentSigning,
        PlayerDevelopmentProcessor $playerDevelopment,
        SeasonSettlementProcessor $seasonSettlement,
        StatsResetProcessor $statsReset,
        TransferMarketResetProcessor $transferMarketReset,
        SeasonSimulationProcessor $seasonSimulation,
        SupercupQualificationProcessor $supercupQualification,
        PromotionRelegationProcessor $promotionRelegation,
        ReputationUpdateProcessor $reputationUpdate,
        YouthAcademyClosingProcessor $youthAcademyClosing,
        UefaQualificationProcessor $uefaQualification,
    ) {
        $this->processors = [
            $loanReturn,
            $trophyRecording,
            $leaderboardStats,
            $contractExpiration,
            $seasonArchive,
            $preContractTransfer,
            $contractRenewal,
            $playerRetirement,
            $squadReplenishment,
            $aiFreeAgentSigning,
            $playerDevelopment,
            $seasonSettlement,
            $statsReset,
            $transferMarketReset,
            $seasonSimulation,
            $supercupQualification,
            $promotionRelegation,
            $reputationUpdate,
            $youthAcademyClosing,
            $uefaQualification,
        ];

        usort($this->processors, fn ($a, $b) => $a->priority() <=> $b->priority());
    }

    /**
     * Close the old season and return transition data for setup.
     *
     * @param  int  $startFromStep  Global step index to resume from (skip steps <= this value)
     * @param  SeasonTransitionData|null  $existingData  Restored DTO from a previous checkpoint
     */
    public function run(Game $game, int $startFromStep = -1, ?SeasonTransitionData $existingData = null): SeasonTransitionData
    {
        $oldSeason = $game->season;
        $newSeason = $this->incrementSeason($oldSeason);

        $data = $existingData ?? new SeasonTransitionData(
            oldSeason: $oldSeason,
            newSeason: $newSeason,
            competitionId: $game->competition_id,
        );

        foreach ($this->processors as $index => $processor) {
            if ($index <= $startFromStep) {
                continue;
            }

            $processorName = class_basename($processor);
            $start = microtime(true);

            try {
                $data = DB::transaction(fn () => $processor->process($game, $data));
            } catch (\Throwable $e) {
                Log::error('Season closing processor failed', [
                    'processor' => get_class($processor),
                    'step' => $index,
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            $elapsed = round((microtime(true) - $start) * 1000);
            Log::info("[SeasonClosing] {$processorName} (priority {$processor->priority()}) completed in {$elapsed}ms");

            // Checkpoint: persist completed step and DTO for crash recovery
            $game->updateQuietly([
                'season_transition_step' => $index,
                'season_transition_data' => $data,
            ]);
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
