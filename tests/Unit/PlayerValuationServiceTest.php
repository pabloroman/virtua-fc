<?php

namespace Tests\Unit;

use App\Modules\Player\Services\PlayerValuationService;
use PHPUnit\Framework\TestCase;

class PlayerValuationServiceTest extends TestCase
{
    private PlayerValuationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlayerValuationService();
    }

    public function test_wage_base_value_strips_the_youth_premium(): void
    {
        // overallScoreToMarketValue applies a ×1.3 youth premium for an 18yo;
        // wageBaseValue caps the age multiplier at 1.0, so the wage anchor is
        // lower than the (potential-inflated) market value.
        $marketValue = $this->service->overallScoreToMarketValue(70, age: 18);
        $wageValue = $this->service->wageBaseValue(70, age: 18);

        $this->assertLessThan(
            $marketValue,
            $wageValue,
            'A youngster should be priced for current ability, not the youth market premium',
        );

        // Capped at 1.0 → the same as a prime player of equal ability.
        $this->assertSame(
            $this->service->wageBaseValue(70, age: 28),
            $wageValue,
            'Youth and prime players of equal ability should anchor to the same wage value',
        );
    }

    public function test_wage_base_value_preserves_the_veteran_decline(): void
    {
        $prime = $this->service->wageBaseValue(70, age: 28);
        $veteran = $this->service->wageBaseValue(70, age: 35);

        $this->assertLessThan(
            $prime,
            $veteran,
            'The veteran value decline must be preserved (the veteran wage modifier is calibrated against it)',
        );
    }

    public function test_wage_base_value_rises_with_current_ability(): void
    {
        $this->assertGreaterThan(
            $this->service->wageBaseValue(60, age: 28),
            $this->service->wageBaseValue(80, age: 28),
        );
    }
}
