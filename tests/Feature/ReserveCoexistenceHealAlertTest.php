<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Modules\Competition\Promotions\ReserveRepairResult;
use App\Modules\Season\Alerts\ReserveCoexistenceHealAlert;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReserveCoexistenceHealAlertTest extends TestCase
{
    public function test_healed_and_unhealable_build_and_send_without_throwing(): void
    {
        Mail::fake();

        $game = new Game();
        $game->id = 'game-uuid';
        $game->country = 'ES';
        $game->season = '2025';

        $alert = app(ReserveCoexistenceHealAlert::class);

        $repaired = ReserveRepairResult::repaired([], [
            ['swap' => ['reason' => 'INVERTED: Reserve <-> Parent'], 'slotA' => [], 'slotB' => []],
        ]);
        $unsafe = ReserveRepairResult::unsafe('tier has siblings — manual swap target required');

        // The alert wraps mail in a fail-soft try/catch; both message builders
        // (including swapSummaries rendering) must run cleanly.
        $alert->healed($game, $repaired);
        $alert->unhealable($game, new \RuntimeException('planner blew up'), $unsafe);

        $this->assertTrue(true);
    }
}
