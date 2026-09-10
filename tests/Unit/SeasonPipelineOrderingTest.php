<?php

namespace Tests\Unit;

use App\Modules\Season\Processors\AgreedTransferCompletionProcessor;
use App\Modules\Season\Processors\AIFreeAgentSigningProcessor;
use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\LoanReturnProcessor;
use App\Modules\Season\Processors\PlayerRetirementProcessor;
use App\Modules\Season\Processors\PreContractTransferProcessor;
use App\Modules\Season\Processors\ReserveOveragePromotionProcessor;
use App\Modules\Season\Processors\SquadReplenishmentProcessor;
use App\Modules\Season\Processors\TransferMarketResetProcessor;
use App\Modules\Season\Services\SeasonClosingPipeline;
use App\Modules\Season\Services\SeasonSetupPipeline;
use Tests\TestCase;

/**
 * Pins what docs/game-systems/season-lifecycle.md states about the pipelines,
 * so the doc and the code cannot drift apart silently again.
 *
 * The ordering chain is the one the transfer domain depends on: loans return
 * before contracts expire, expiry leaves locked players in place, the two
 * completion processors move them, replenishment only trims once every deal
 * has settled, and the market is cleared last.
 */
class SeasonPipelineOrderingTest extends TestCase
{
    /** @var list<class-string> in the order they must run */
    private const TRANSFER_CHAIN = [
        ReserveOveragePromotionProcessor::class,
        LoanReturnProcessor::class,
        ContractExpirationProcessor::class,
        PreContractTransferProcessor::class,
        AgreedTransferCompletionProcessor::class,
        PlayerRetirementProcessor::class,
        SquadReplenishmentProcessor::class,
        AIFreeAgentSigningProcessor::class,
        TransferMarketResetProcessor::class,
    ];

    public function test_the_closing_pipeline_runs_the_transfer_chain_in_order(): void
    {
        $order = array_map(fn ($p) => $p::class, app(SeasonClosingPipeline::class)->getProcessors());

        $positions = array_map(function (string $class) use ($order) {
            $index = array_search($class, $order, true);
            $this->assertNotFalse($index, "{$class} is not wired into SeasonClosingPipeline.");

            return $index;
        }, self::TRANSFER_CHAIN);

        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'The transfer chain must run in the documented order: ' . implode(' → ', array_map(
            fn ($c) => class_basename($c),
            self::TRANSFER_CHAIN,
        )));
    }

    public function test_pipeline_sizes_match_the_documentation(): void
    {
        // If either count changes, update docs/game-systems/season-lifecycle.md
        // in the same commit — the tables there are the source of truth.
        $this->assertCount(29, app(SeasonClosingPipeline::class)->getProcessors());
        $this->assertCount(15, app(SeasonSetupPipeline::class)->getProcessors());
    }
}
