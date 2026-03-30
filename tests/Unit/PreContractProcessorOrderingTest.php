<?php

namespace Tests\Unit;

use App\Modules\Season\Processors\ContractExpirationProcessor;
use App\Modules\Season\Processors\PreContractTransferProcessor;
use App\Modules\Season\Services\SeasonClosingPipeline;
use Tests\TestCase;

class PreContractProcessorOrderingTest extends TestCase
{
    public function test_contract_expiration_runs_before_pre_contract_transfers(): void
    {
        $pipeline = app(SeasonClosingPipeline::class);
        $processors = $pipeline->getProcessors();

        $expirationIndex = null;
        $preContractIndex = null;

        foreach ($processors as $index => $processor) {
            if ($processor instanceof ContractExpirationProcessor) {
                $expirationIndex = $index;
            }
            if ($processor instanceof PreContractTransferProcessor) {
                $preContractIndex = $index;
            }
        }

        $this->assertNotNull($expirationIndex, 'ContractExpirationProcessor not found in pipeline');
        $this->assertNotNull($preContractIndex, 'PreContractTransferProcessor not found in pipeline');
        $this->assertLessThan(
            $preContractIndex,
            $expirationIndex,
            'ContractExpirationProcessor must run before PreContractTransferProcessor so expired contracts free squad slots first'
        );
    }

    public function test_contract_expiration_has_lower_priority_than_pre_contract_transfers(): void
    {
        $expiration = app(ContractExpirationProcessor::class);
        $preContract = app(PreContractTransferProcessor::class);

        $this->assertLessThan(
            $preContract->priority(),
            $expiration->priority(),
            'ContractExpirationProcessor priority must be lower (runs earlier) than PreContractTransferProcessor'
        );
    }
}
