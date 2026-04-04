<?php

namespace Tests\Unit;

use App\Modules\Transfer\Services\WageNegotiationEvaluator;
use PHPUnit\Framework\TestCase;

class WageNegotiationEvaluatorTest extends TestCase
{
    private WageNegotiationEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new WageNegotiationEvaluator();
    }

    public function test_high_offer_is_accepted(): void
    {
        $result = $this->evaluator->evaluate(
            offerWage: 50_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.50,
            round: 1,
            maxRounds: 3,
        );

        $this->assertEquals('accepted', $result['result']);
        $this->assertNull($result['counterWage']);
    }

    public function test_very_low_offer_is_rejected(): void
    {
        $result = $this->evaluator->evaluate(
            offerWage: 10_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.50,
            round: 1,
            maxRounds: 3,
        );

        $this->assertEquals('rejected', $result['result']);
        $this->assertNull($result['counterWage']);
    }

    public function test_moderate_offer_triggers_counter(): void
    {
        $result = $this->evaluator->evaluate(
            offerWage: 38_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.50,
            round: 1,
            maxRounds: 3,
        );

        $this->assertEquals('countered', $result['result']);
        $this->assertNotNull($result['counterWage']);
    }

    public function test_counter_never_exceeds_player_demand(): void
    {
        // Low disposition = high minimumAcceptable, pushing the counter closer to demand
        $result = $this->evaluator->evaluate(
            offerWage: 48_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.10,
            round: 1,
            maxRounds: 3,
        );

        if ($result['result'] === 'countered') {
            $this->assertLessThanOrEqual(
                50_000_000,
                $result['counterWage'],
                'Counter-offer must not exceed the player demand'
            );
        }
    }

    public function test_counter_never_exceeds_demand_with_salary_floor_above_demand(): void
    {
        // Edge case: salary floor is higher than player demand
        // (shouldn't happen normally for renewals, but the evaluator should be safe)
        $result = $this->evaluator->evaluate(
            offerWage: 55_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.10,
            round: 1,
            maxRounds: 3,
            salaryFloor: 60_000_000,
        );

        if ($result['result'] === 'countered') {
            $this->assertLessThanOrEqual(
                50_000_000,
                $result['counterWage'],
                'Counter-offer must not exceed the player demand even with high salary floor'
            );
        }
    }

    public function test_counter_does_not_increase_across_rounds(): void
    {
        // Round 1: get a counter with moderate disposition
        $round1 = $this->evaluator->evaluate(
            offerWage: 37_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.50,
            round: 1,
            maxRounds: 3,
        );

        $this->assertEquals('countered', $round1['result']);
        $counter1 = $round1['counterWage'];

        // Round 2: worse disposition (round penalty), pass previous counter
        $round2 = $this->evaluator->evaluate(
            offerWage: 37_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.45,
            round: 2,
            maxRounds: 3,
            previousCounter: $counter1,
        );

        if ($round2['result'] === 'countered') {
            $this->assertLessThanOrEqual(
                $counter1,
                $round2['counterWage'],
                'Counter-offer must not increase across negotiation rounds'
            );
        }
    }

    public function test_previous_counter_caps_new_counter(): void
    {
        $lowPreviousCounter = 42_000_000;

        $result = $this->evaluator->evaluate(
            offerWage: 40_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.30,
            round: 2,
            maxRounds: 3,
            previousCounter: $lowPreviousCounter,
        );

        if ($result['result'] === 'countered') {
            $this->assertLessThanOrEqual(
                $lowPreviousCounter,
                $result['counterWage'],
                'Counter-offer must be capped by previous counter'
            );
        }
    }

    public function test_offer_at_max_rounds_is_not_countered(): void
    {
        $result = $this->evaluator->evaluate(
            offerWage: 45_000_000,
            offeredYears: 3,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.50,
            round: 3,
            maxRounds: 3,
        );

        $this->assertNotEquals('countered', $result['result']);
    }

    public function test_longer_contract_years_boost_acceptance(): void
    {
        // Offer below demand but with extra years should be accepted
        $result = $this->evaluator->evaluate(
            offerWage: 45_000_000,
            offeredYears: 5,
            playerDemand: 50_000_000,
            preferredYears: 3,
            disposition: 0.50,
            round: 1,
            maxRounds: 3,
        );

        $this->assertEquals('accepted', $result['result']);
    }
}
