<?php

namespace Tests\Unit;

use App\Modules\Player\Services\PlayerValuationService;
use App\Support\Money;
use Tests\TestCase;

/**
 * Pure-math coverage proving overallScoreToMarketValue() always returns a
 * "nice" round number. The value is snapped via Money::roundPrice() at the
 * source so every generated/recomputed market value stays tidy in the UI.
 */
class PlayerValuationRoundingTest extends TestCase
{
    private PlayerValuationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PlayerValuationService();
    }

    public function test_market_value_is_always_a_round_price(): void
    {
        // Sweep across the whole ability/age range and both position families;
        // every result must already be idempotent under Money::roundPrice().
        foreach (range(40, 95) as $overall) {
            foreach ([17, 21, 24, 28, 32, 36] as $age) {
                foreach (['ST', 'GK'] as $position) {
                    $value = $this->service->overallScoreToMarketValue($overall, $age, null, $position);

                    $this->assertSame(
                        Money::roundPrice($value),
                        $value,
                        "Market value for overall {$overall}, age {$age}, {$position} must be a round price.",
                    );
                }
            }
        }
    }
}
