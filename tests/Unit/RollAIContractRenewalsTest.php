<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Transfer\Listeners\RollAIContractRenewals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollAIContractRenewalsTest extends TestCase
{
    use RefreshDatabase;

    private RollAIContractRenewals $listener;
    private Team $userTeam;
    private Team $aiTeam;
    private Game $game;

    /** Contract end matching the new-season cutoff used in the test game. */
    private const EXPIRING_CONTRACT = '2027-06-30';
    /** Renewed contracts get extended by 3 seasons (anchored to game season). */
    private const RENEWED_CONTRACT = '2029-06-30';

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = app(RollAIContractRenewals::class);

        $this->userTeam = Team::factory()->create();
        $this->aiTeam = Team::factory()->create();

        $this->game = Game::factory()->forTeam($this->userTeam)->create([
            'season' => '2026',
            'current_date' => '2026-08-15',
        ]);
    }

    public function test_top_importance_player_is_renewed_after_enough_ticks(): void
    {
        // Cumulative test: ~20 ticks (Aug→Dec) is enough for a top-importance
        // player to cross the renewal line in a meaningful share of runs.
        // Per-tick rate 35‰ → 1 - 0.965^20 ≈ 51% chance the top player is
        // renewed by tick 20; loose bound to absorb noise.
        $renewedRuns = 0;
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $players = $this->makeAiSquadOfFiveExpiring();
            $top = $players->first();

            for ($tick = 0; $tick < 20; $tick++) {
                $this->listener->roll($this->game);
            }

            if ($top->refresh()->contract_until?->toDateString() === self::RENEWED_CONTRACT) {
                $renewedRuns++;
            }

            $this->wipePlayers();
        }

        $this->assertGreaterThanOrEqual(
            (int) ($iterations * 0.30),
            $renewedRuns,
            "Top-importance player should be renewed in a meaningful share of runs by tick 20 (got {$renewedRuns}/{$iterations})",
        );
    }

    public function test_low_importance_player_is_rarely_renewed_after_few_ticks(): void
    {
        // Low bucket rate is 2‰; even at tick 20 cumulative ≈ 4%. Loose
        // upper bound asserts the floor doesn't accidentally drift up.
        $renewedRuns = 0;
        $iterations = 50;

        for ($i = 0; $i < $iterations; $i++) {
            $players = $this->makeAiSquadOfFiveExpiring();
            $bottom = $players->last();

            for ($tick = 0; $tick < 20; $tick++) {
                $this->listener->roll($this->game);
            }

            if ($bottom->refresh()->contract_until?->toDateString() === self::RENEWED_CONTRACT) {
                $renewedRuns++;
            }

            $this->wipePlayers();
        }

        $this->assertLessThanOrEqual(
            (int) ($iterations * 0.30),
            $renewedRuns,
            "Bottom-importance player should rarely be renewed after 20 ticks (got {$renewedRuns}/{$iterations})",
        );
    }

    public function test_does_not_touch_user_team_players(): void
    {
        $userPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->userTeam)
            ->create([
                'date_of_birth' => '1998-01-01',
                'overall_score' => 90,
                'contract_until' => self::EXPIRING_CONTRACT,
            ]);

        for ($i = 0; $i < 100; $i++) {
            $this->listener->roll($this->game);
        }

        $this->assertSame(
            self::EXPIRING_CONTRACT,
            $userPlayer->refresh()->contract_until->toDateString(),
            'User-team contracts must never be auto-extended by the AI roll',
        );
    }

    public function test_does_not_touch_veterans(): void
    {
        // Surround the veteran with non-veteran teammates so importance
        // ranks are well-defined.
        for ($i = 0; $i < 4; $i++) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($this->aiTeam)
                ->create([
                    'date_of_birth' => '1998-01-01',
                    'overall_score' => 70,
                    'contract_until' => '2029-06-30',
                ]);
        }

        $veteranBirth = Carbon::parse($this->game->current_date)->subYears(36)->toDateString();
        $veteran = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->aiTeam)
            ->create([
                'date_of_birth' => $veteranBirth,
                'overall_score' => 90,
                'contract_until' => self::EXPIRING_CONTRACT,
            ]);

        for ($i = 0; $i < 100; $i++) {
            $this->listener->roll($this->game);
            $this->assertSame(
                self::EXPIRING_CONTRACT,
                $veteran->refresh()->contract_until->toDateString(),
                'Veterans must not be touched (they keep the season-end coin flip)',
            );
        }
    }

    public function test_does_not_touch_loaned_out_players(): void
    {
        $loanedPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->aiTeam)
            ->create([
                'date_of_birth' => '1998-01-01',
                'overall_score' => 90,
                'contract_until' => self::EXPIRING_CONTRACT,
            ]);

        $borrowingTeam = Team::factory()->create();
        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $loanedPlayer->id,
            'parent_team_id' => $this->aiTeam->id,
            'loan_team_id' => $borrowingTeam->id,
            'started_at' => '2026-08-01',
            'return_at' => '2027-06-30',
            'status' => Loan::STATUS_ACTIVE,
        ]);

        for ($i = 0; $i < 100; $i++) {
            $this->listener->roll($this->game);
            $this->assertSame(
                self::EXPIRING_CONTRACT,
                $loanedPlayer->refresh()->contract_until->toDateString(),
                'On-loan players must not have their contracts extended here',
            );
        }
    }

    public function test_does_not_rug_pull_players_with_pending_pre_contract(): void
    {
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->aiTeam)
            ->create([
                'date_of_birth' => '1998-01-01',
                'overall_score' => 90,
                'contract_until' => self::EXPIRING_CONTRACT,
            ]);

        TransferOffer::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'offering_team_id' => $this->userTeam->id,
            'selling_team_id' => $this->aiTeam->id,
            'offer_type' => TransferOffer::TYPE_PRE_CONTRACT,
            'direction' => TransferOffer::DIRECTION_INCOMING,
            'transfer_fee' => 0,
            'status' => TransferOffer::STATUS_PENDING,
            'game_date' => $this->game->current_date,
            'expires_at' => $this->game->current_date->copy()->addDays(14),
        ]);

        for ($i = 0; $i < 100; $i++) {
            $this->listener->roll($this->game);
            $this->assertSame(
                self::EXPIRING_CONTRACT,
                $player->refresh()->contract_until->toDateString(),
                'A player with a pending pre-contract offer must not be renewed mid-negotiation',
            );
        }
    }

    public function test_handle_invokes_roll(): void
    {
        $this->makeAiSquadOfFiveExpiring();
        $previousDate = $this->game->current_date->copy()->subDay();

        // Smoke test the event-handler wiring: dispatching through handle()
        // must produce the same effect as calling roll() directly. Run many
        // ticks so the dice land at least once.
        for ($i = 0; $i < 200; $i++) {
            $this->listener->handle(new GameDateAdvanced(
                $this->game,
                $previousDate,
                $this->game->current_date,
            ));
        }

        $renewedCount = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->where('contract_until', self::RENEWED_CONTRACT)
            ->count();

        $this->assertGreaterThan(
            0,
            $renewedCount,
            'handle() must drive the same renewal pass roll() does',
        );
    }

    /**
     * Five-player AI squad sorted high-to-low by overall_score so the first
     * lands in the top importance bucket and the last in the bottom bucket.
     */
    private function makeAiSquadOfFiveExpiring(): \Illuminate\Support\Collection
    {
        $scores = [90, 80, 70, 60, 50];
        $players = collect();
        foreach ($scores as $score) {
            $players->push(
                GamePlayer::factory()
                    ->forGame($this->game)
                    ->forTeam($this->aiTeam)
                    ->create([
                        'date_of_birth' => '1998-01-01',
                        'overall_score' => $score,
                        'contract_until' => self::EXPIRING_CONTRACT,
                    ]),
            );
        }
        return $players;
    }

    private function wipePlayers(): void
    {
        GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->aiTeam->id)
            ->delete();
    }
}
