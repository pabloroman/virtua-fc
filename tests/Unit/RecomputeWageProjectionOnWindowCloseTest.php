<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Finance\Listeners\RecomputeWageProjectionOnWindowClose;
use App\Modules\Match\Events\GameDateAdvanced;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The season wage projection is frozen at its pre-season squad. This listener
 * restates it once when a transfer window closes so the finances page reflects
 * the window's net ins and outs (#1191 / #689). It mirrors
 * ProcessTransferWindowClose's boundary detection: fire only when current_date
 * crosses OUT of a window.
 */
class RecomputeWageProjectionOnWindowCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_recomputes_the_wage_projection_when_the_winter_window_closes(): void
    {
        [$game, $finances] = $this->buildGameWithFrozenProjection(
            projectedWages: 50_000_000_00,
            projectedSurplus: 10_000_000_00,
            currentSquadWage: 30_000_000_00,
        );

        // Jan 31 (winter window open) → Feb 1 (closed): the close boundary.
        $this->handle($game, Carbon::create(2026, 1, 31), Carbon::create(2026, 2, 1));

        $finances->refresh();
        $this->assertSame(30_000_000_00, $finances->projected_wages);
        $this->assertSame(10_000_000_00 + 20_000_000_00, $finances->projected_surplus);
    }

    public function test_does_not_recompute_when_a_window_opens(): void
    {
        [$game, $finances] = $this->buildGameWithFrozenProjection(
            projectedWages: 50_000_000_00,
            projectedSurplus: 10_000_000_00,
            currentSquadWage: 30_000_000_00,
        );

        // Jun 30 (closed) → Jul 1 (summer window open): an OPEN boundary, not a
        // close — the projection must stay frozen.
        $this->handle($game, Carbon::create(2026, 6, 30), Carbon::create(2026, 7, 1));

        $finances->refresh();
        $this->assertSame(50_000_000_00, $finances->projected_wages);
        $this->assertSame(10_000_000_00, $finances->projected_surplus);
    }

    public function test_does_not_recompute_while_the_window_stays_open(): void
    {
        [$game, $finances] = $this->buildGameWithFrozenProjection(
            projectedWages: 50_000_000_00,
            projectedSurplus: 10_000_000_00,
            currentSquadWage: 30_000_000_00,
        );

        // Jan 10 → Jan 20: both inside the winter window, no boundary crossed.
        $this->handle($game, Carbon::create(2026, 1, 10), Carbon::create(2026, 1, 20));

        $finances->refresh();
        $this->assertSame(50_000_000_00, $finances->projected_wages);
        $this->assertSame(10_000_000_00, $finances->projected_surplus);
    }

    private function handle(Game $game, Carbon $previousDate, Carbon $newDate): void
    {
        // Resolve through the container so the real BudgetProjectionService is
        // wired; recomputeWageProjection only touches the squad + finances row,
        // so its other (mocked-elsewhere) collaborators are never called.
        app(RecomputeWageProjectionOnWindowClose::class)
            ->handle(new GameDateAdvanced($game, $previousDate, $newDate));
    }

    /**
     * @return array{0: Game, 1: GameFinances}
     */
    private function buildGameWithFrozenProjection(int $projectedWages, int $projectedSurplus, int $currentSquadWage): array
    {
        $team = Team::factory()->create();
        $competition = Competition::factory()->league()->create();
        $game = Game::factory()
            ->forTeam($team)
            ->inCompetition($competition->id)
            ->create(['season' => 2026]);

        $finances = GameFinances::create([
            'game_id' => $game->id,
            'season' => (int) $game->season,
            'projected_wages' => $projectedWages,
            'projected_surplus' => $projectedSurplus,
        ]);

        // A single current-squad player carrying the whole live wage bill.
        GamePlayer::factory()->forGame($game)->forTeam($team)->create(['annual_wage' => $currentSquadWage]);

        return [$game, $finances];
    }
}
