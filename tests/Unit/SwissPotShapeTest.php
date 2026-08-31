<?php

namespace Tests\Unit;

use App\Modules\Competition\Services\SwissDrawService;
use App\Modules\Season\Jobs\SetupNewGame;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Real UEFA seeding pots are optional data: the Transfermarkt scraper cannot
 * read them off any page, so a freshly scraped participant list arrives without
 * them and they are entered by hand afterwards.
 *
 * SetupNewGame must therefore treat an unusable pot set as "no pot data" and
 * publish nothing, letting SeasonInitializationService assign pots by squad
 * market value instead — the same path every season after the first uses. The
 * failure this guards against is the opposite: passing a malformed set through
 * makes SwissDrawService throw inside a queued job, and game creation dies.
 */
class SwissPotShapeTest extends TestCase
{
    private ReflectionMethod $potsAreWellFormed;

    private SetupNewGame $job;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the pot-shape helper is exercised; the constructor args are
        // inert here (SetupNewGame::onQueue is a plain setter).
        $this->job = new SetupNewGame('game-id', 'team-id', 'ESP1', '2025', 'career');
        $this->potsAreWellFormed = new ReflectionMethod(SetupNewGame::class, 'potsAreWellFormed');
    }

    /**
     * Build a draw set from a pot => club-count map.
     *
     * @param  array<int, int>  $distribution
     * @return array<int, array{id: string, pot: int, country: string}>
     */
    private function drawTeams(array $distribution): array
    {
        $teams = [];
        foreach ($distribution as $pot => $count) {
            for ($i = 0; $i < $count; $i++) {
                $teams[] = ['id' => 'team-' . count($teams), 'pot' => $pot, 'country' => 'ES'];
            }
        }

        return $teams;
    }

    /** @param array<int, array{id: string, pot: int, country: string}> $drawTeams */
    private function isWellFormed(array $drawTeams): bool
    {
        return $this->potsAreWellFormed->invoke($this->job, $drawTeams);
    }

    public function test_accepts_four_full_pots(): void
    {
        $this->assertTrue($this->isWellFormed($this->drawTeams([1 => 9, 2 => 9, 3 => 9, 4 => 9])));
    }

    public function test_rejects_a_field_with_no_pots_at_all(): void
    {
        // Every club defaults to pot 0 when the scraped list carries no pots.
        $this->assertFalse($this->isWellFormed($this->drawTeams([0 => 36])));
    }

    public function test_rejects_an_unbalanced_distribution(): void
    {
        $this->assertFalse($this->isWellFormed($this->drawTeams([1 => 10, 2 => 9, 3 => 9, 4 => 8])));
    }

    public function test_rejects_a_short_field(): void
    {
        // A club dropped for want of squad data leaves 35 — the shape the draw
        // cannot satisfy and the reason validation guards squad sources.
        $this->assertFalse($this->isWellFormed($this->drawTeams([1 => 9, 2 => 9, 3 => 9, 4 => 8])));
    }

    public function test_rejects_a_pot_outside_the_valid_range(): void
    {
        $this->assertFalse($this->isWellFormed($this->drawTeams([1 => 9, 2 => 9, 3 => 9, 4 => 9, 5 => 9])));
    }

    public function test_rejects_an_empty_field(): void
    {
        $this->assertFalse($this->isWellFormed([]));
    }

    public function test_pot_shape_matches_what_the_draw_service_demands(): void
    {
        $this->assertSame(4, SwissDrawService::POTS);
        $this->assertSame(9, SwissDrawService::TEAMS_PER_POT);
        $this->assertSame(36, SwissDrawService::LEAGUE_PHASE_TEAMS);
    }
}
