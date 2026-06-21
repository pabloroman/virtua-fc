<?php

namespace Tests\Unit;

use App\Modules\Season\Services\GamePlayerTemplateService;
use ReflectionMethod;
use Tests\TestCase;

class GamePlayerTemplateMarketValueFloorTest extends TestCase
{
    private ReflectionMethod $prepareTemplateRow;

    private GamePlayerTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GamePlayerTemplateService::class);
        $this->prepareTemplateRow = new ReflectionMethod(GamePlayerTemplateService::class, 'prepareTemplateRow');
    }

    /**
     * @param  array<string, mixed>  $playerData
     * @return array<string, mixed>|null
     */
    private function prepare(array $playerData, ?string $clubCountry = null): ?array
    {
        return $this->prepareTemplateRow->invoke(
            $this->service,
            '2025',          // season
            'team-uuid',     // teamId
            $clubCountry,    // clubCountry
            $playerData,     // playerData
            0,               // minimumWage
        );
    }

    private function basePlayer(array $overrides = []): array
    {
        return array_merge([
            'id' => '999999',
            'name' => 'Test Player',
            'dateOfBirth' => '2000-01-01',
            'position' => 'Central Midfield',
        ], $overrides);
    }

    public function test_missing_market_value_is_floored_to_100k(): void
    {
        // No 'marketValue' key at all → parseMarketValue(null) === 0.
        $row = $this->prepare($this->basePlayer());

        $this->assertSame(10_000_000, $row['market_value_cents']);
        $this->assertSame(1, $row['tier']);
    }

    public function test_dash_market_value_is_floored_to_100k(): void
    {
        $row = $this->prepare($this->basePlayer(['marketValue' => '-']));

        $this->assertSame(10_000_000, $row['market_value_cents']);
        $this->assertSame(1, $row['tier']);
    }

    public function test_floored_value_yields_a_release_clause_for_es_clubs(): void
    {
        // With the old bug (0 cents) an ES club's clause was null; flooring to
        // €100K must produce a non-zero mandatory clause.
        $row = $this->prepare($this->basePlayer(), clubCountry: 'ES');

        $this->assertNotNull($row['release_clause']);
        $this->assertGreaterThan(0, $row['release_clause']);
    }

    public function test_quoted_market_value_is_preserved(): void
    {
        $row = $this->prepare($this->basePlayer(['marketValue' => '€5.00m']));

        $this->assertSame(5_000_000_00, $row['market_value_cents']);
    }
}
