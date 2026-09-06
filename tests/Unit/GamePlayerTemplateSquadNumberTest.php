<?php

namespace Tests\Unit;

use App\Modules\Season\Services\GamePlayerTemplateService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * game_player_templates carries a partial unique index on
 * (season, team_id, number) WHERE number IS NOT NULL. A squad file states an
 * unknown shirt as "" (or omits the key), so a blank must reach the row as
 * NULL — casting it to 0 puts two shirtless team-mates on the same "number"
 * and SetupNewGame's insertOrIgnore silently drops one of them.
 */
class GamePlayerTemplateSquadNumberTest extends TestCase
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
    private function prepare(array $playerData): ?array
    {
        return $this->prepareTemplateRow->invoke(
            $this->service,
            '2025',          // season
            'team-uuid',     // teamId
            null,            // clubCountry
            $playerData,     // playerData
            0,               // minimumWage
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function basePlayer(array $overrides = []): array
    {
        return array_merge([
            'id' => '999999',
            'name' => 'Test Player',
            'dateOfBirth' => '2000-01-01',
            'position' => 'Central Midfield',
        ], $overrides);
    }

    public function test_empty_string_number_becomes_null(): void
    {
        $row = $this->prepare($this->basePlayer(['number' => '']));

        $this->assertNull($row['number']);
    }

    public function test_missing_number_becomes_null(): void
    {
        $row = $this->prepare($this->basePlayer());

        $this->assertNull($row['number']);
    }

    public function test_null_number_becomes_null(): void
    {
        $row = $this->prepare($this->basePlayer(['number' => null]));

        $this->assertNull($row['number']);
    }

    /**
     * Two blanks in one club is the case the index used to reject: both rows
     * must be NULL, which the partial index excludes.
     */
    public function test_two_blank_shirts_in_one_club_do_not_collide(): void
    {
        $first = $this->prepare($this->basePlayer(['id' => '1', 'number' => '']));
        $second = $this->prepare($this->basePlayer(['id' => '2', 'number' => '']));

        $this->assertNull($first['number']);
        $this->assertNull($second['number']);
    }

    public function test_real_numbers_are_still_cast_to_int(): void
    {
        $this->assertSame(9, $this->prepare($this->basePlayer(['number' => '9']))['number']);
        $this->assertSame(7, $this->prepare($this->basePlayer(['number' => '07']))['number']);
        $this->assertSame(23, $this->prepare($this->basePlayer(['number' => 23]))['number']);
    }
}
