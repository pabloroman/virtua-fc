<?php

namespace Tests\Unit;

use App\Models\GamePlayer;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class PenaltyShootoutTest extends TestCase
{
    private MatchSimulator $simulator;

    private ReflectionMethod $hasPenaltyShootoutWinner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->simulator = new MatchSimulator;
        $this->hasPenaltyShootoutWinner = new ReflectionMethod(MatchSimulator::class, 'hasPenaltyShootoutWinner');
    }

    public function test_shootout_can_end_before_last_away_kick_when_result_is_decided(): void
    {
        $resolved = $this->hasPenaltyShootoutWinner->invoke(
            $this->simulator,
            4,
            2,
            5,
            4,
            5,
        );

        $this->assertTrue($resolved);
    }

    public function test_shootout_continues_when_trailing_team_can_still_equalise(): void
    {
        $resolved = $this->hasPenaltyShootoutWinner->invoke(
            $this->simulator,
            3,
            2,
            4,
            4,
            5,
        );

        $this->assertFalse($resolved);
    }

    public function test_shootout_can_end_immediately_after_home_kick_in_round_five(): void
    {
        $resolved = $this->hasPenaltyShootoutWinner->invoke(
            $this->simulator,
            5,
            3,
            5,
            4,
            5,
        );

        $this->assertTrue($resolved);
    }

    public function test_shootout_never_returns_a_tied_score(): void
    {
        $home = $this->buildTeamPlayers('H');
        $away = $this->buildTeamPlayers('A');

        for ($i = 0; $i < 50; $i++) {
            $result = $this->simulator->simulatePenaltyShootout($home, $away);

            $this->assertNotSame(
                $result['homeScore'],
                $result['awayScore'],
                'Shootout returned a tied score: '.json_encode($result),
            );
        }
    }

    public function test_first_kicker_is_decided_by_coin_flip(): void
    {
        $home = $this->buildTeamPlayers('H');
        $away = $this->buildTeamPlayers('A');

        $homeFirstCount = 0;
        $awayFirstCount = 0;

        for ($i = 0; $i < 200; $i++) {
            $result = $this->simulator->simulatePenaltyShootout($home, $away);
            $firstKick = $result['kicks'][0] ?? null;

            $this->assertNotNull($firstKick);

            if ($firstKick['side'] === 'home') {
                $homeFirstCount++;
            } else {
                $awayFirstCount++;
            }
        }

        // Both sides should appear as first kicker. With 200 trials at p=0.5,
        // P(one side gets 0) = 2 * 0.5^200 — practically impossible.
        $this->assertGreaterThan(0, $homeFirstCount, 'Home never kicked first across 200 shootouts');
        $this->assertGreaterThan(0, $awayFirstCount, 'Away never kicked first across 200 shootouts');
    }

    public function test_sudden_death_can_extend_shootout_past_round_five(): void
    {
        $home = $this->buildTeamPlayers('H');
        $away = $this->buildTeamPlayers('A');

        $sawSuddenDeath = false;

        // With 75% conversion, P(tied after 5 rounds) is ~22%, so 100 trials
        // should virtually always include at least one sudden-death shootout.
        for ($i = 0; $i < 100; $i++) {
            $result = $this->simulator->simulatePenaltyShootout($home, $away);

            $maxRound = 0;
            foreach ($result['kicks'] as $kick) {
                if ($kick['round'] > $maxRound) {
                    $maxRound = $kick['round'];
                }
            }

            if ($maxRound > 5) {
                $sawSuddenDeath = true;
                break;
            }
        }

        $this->assertTrue($sawSuddenDeath, 'Expected sudden death to occur in at least one of 100 shootouts');
    }

    public function test_sudden_death_recycles_kickers_only_after_all_have_kicked(): void
    {
        // 11 distinct players — once everyone has kicked, the same order
        // restarts. We verify recycling kicks in at kick 12 (not earlier).
        $home = $this->buildTeamPlayers('H');
        $away = $this->buildTeamPlayers('A');

        $sawRecycle = false;

        for ($i = 0; $i < 200 && ! $sawRecycle; $i++) {
            $result = $this->simulator->simulatePenaltyShootout($home, $away);

            $homeIdsByOrder = collect($result['kicks'])
                ->where('side', 'home')
                ->pluck('playerId')
                ->values();

            if ($homeIdsByOrder->count() >= 12) {
                $this->assertSame(
                    $homeIdsByOrder->take(11)->unique()->count(),
                    $homeIdsByOrder->take(11)->count(),
                    'The first 11 home kickers must all be distinct',
                );
                $this->assertSame(
                    $homeIdsByOrder->get(0),
                    $homeIdsByOrder->get(11),
                    'Kicker #12 must be the same as kicker #1 (recycle)',
                );
                $sawRecycle = true;
            }
        }

        $this->assertTrue($sawRecycle, 'Expected at least one shootout to reach the 12th kick to validate recycling');
    }

    /**
     * Build a balanced squad of 11 players (10 outfield + 1 GK).
     */
    private function buildTeamPlayers(string $prefix): Collection
    {
        $players = collect();

        $players->push($this->makePlayer($prefix.'-gk', 'Goalkeeper', 70));

        for ($i = 1; $i <= 10; $i++) {
            $players->push($this->makePlayer($prefix.'-out-'.$i, 'Forward', 70));
        }

        return $players;
    }

    private function makePlayer(string $id, string $position, int $overall): GamePlayer
    {
        $player = new GamePlayer;
        $player->forceFill([
            'id' => $id,
            'name' => $id,
            'position' => $position,
            'overall_score' => $overall,
        ]);
        $player->morale = 70;

        return $player;
    }
}
