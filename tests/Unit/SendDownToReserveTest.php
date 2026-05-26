<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Loan;
use App\Models\Team;
use App\Modules\ReserveTeam\Exceptions\FirstTeamSquadMinimumException;
use App\Modules\ReserveTeam\Services\ReserveTeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers ReserveTeamService::sendDownToReserve() — the user-initiated
 * permanent demotion of a U23 first-team player to the reserve squad.
 * Mirrors the reserve→first-team call-up so U23 movement stays symmetric
 * in filial games.
 */
class SendDownToReserveTest extends TestCase
{
    use RefreshDatabase;

    private ReserveTeamService $service;
    private Team $firstTeam;
    private Team $reserveTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReserveTeamService::class);

        $this->firstTeam = Team::factory()->create(['name' => 'Atlético de Madrid']);
        $this->reserveTeam = Team::factory()->create([
            'name' => 'Atlético Madrileño',
            'parent_team_id' => $this->firstTeam->id,
        ]);

        Competition::factory()->league()->create(['id' => 'ESP1']);

        $this->game = Game::factory()->create([
            'team_id' => $this->firstTeam->id,
            'reserve_team_id' => $this->reserveTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'current_date' => '2025-08-15',
        ]);

        // Comfortable baseline: every position group sits well above its
        // floor so a single send-down never trips the guard by default.
        // Min-breach tests rebuild the squad to a tighter shape.
        $this->fillFirstTeam([
            'Goalkeeper'       => 3,
            'Centre-Back'      => 8,
            'Central Midfield' => 8,
            'Centre-Forward'   => 6,
        ]);
    }

    public function test_moves_a_u23_first_team_player_to_the_reserve_squad(): void
    {
        $u23 = $this->u23FirstTeamPlayer(['number' => 14]);

        $this->service->sendDownToReserve($u23, $this->game);

        $u23->refresh();
        $this->assertSame($this->reserveTeam->id, $u23->team_id, 'team_id should flip to the reserve.');
        $this->assertNull($u23->number, 'Squad number must be nulled so a reserve player wearing the same shirt does not collide.');

        $transfer = GameTransfer::where('game_player_id', $u23->id)->first();
        $this->assertNotNull($transfer, 'Demotion must be recorded as a GameTransfer row.');
        $this->assertSame(GameTransfer::TYPE_INTERNAL_DEMOTION, $transfer->type);
        $this->assertSame($this->firstTeam->id, $transfer->from_team_id);
        $this->assertSame($this->reserveTeam->id, $transfer->to_team_id);
        $this->assertSame(0, (int) $transfer->transfer_fee);
        $this->assertSame('2025', $transfer->season);
    }

    public function test_refuses_when_game_is_not_a_filial(): void
    {
        $this->game->update(['reserve_team_id' => null]);
        $u23 = $this->u23FirstTeamPlayer();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('filial');

        $this->service->sendDownToReserve($u23, $this->game);
    }

    public function test_refuses_when_player_is_not_on_the_first_team(): void
    {
        $u23 = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->reserveTeam)
            ->create([
                'date_of_birth' => '2003-06-15',
                'position'      => 'Central Midfield',
            ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not currently registered to the first team');

        $this->service->sendDownToReserve($u23, $this->game);
    }

    public function test_refuses_when_player_has_no_date_of_birth(): void
    {
        $player = $this->u23FirstTeamPlayer(['date_of_birth' => null]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only U23 players');

        $this->service->sendDownToReserve($player, $this->game);
    }

    public function test_refuses_when_player_is_over_the_u23_cutoff(): void
    {
        // Season 2025 cutoff is Jan 1 2002 (FIFA-style U-23 for season Y =
        // born on/after Jan 1 of Y-23). Born 2000-06-15 sits clearly before
        // the cutoff, so the U23 gate must reject the demotion.
        $player = $this->u23FirstTeamPlayer(['date_of_birth' => '2000-06-15']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only U23 players');

        $this->service->sendDownToReserve($player, $this->game);
    }

    public function test_refuses_when_player_is_on_an_active_call_up_loan_from_reserve(): void
    {
        // A reserve player called up to the first team carries an active
        // Loan row (parent=reserve, loan=first). Round-tripping them must
        // go through sendBackToReserve() so the loan closes cleanly —
        // send-down here would orphan the loan.
        $player = $this->u23FirstTeamPlayer();
        Loan::create([
            'game_id'        => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $this->reserveTeam->id,
            'loan_team_id'   => $this->firstTeam->id,
            'started_at'     => $this->game->current_date,
            'return_at'      => $this->game->getSeasonEndDate(),
            'status'         => Loan::STATUS_ACTIVE,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('reserve call-up loan');

        $this->service->sendDownToReserve($player, $this->game);
    }

    public function test_blocks_when_first_team_is_at_the_squad_minimum(): void
    {
        // Rebuild to exactly MIN_SQUAD_SIZE (20) on the first team — every
        // position group sits at or above its per-group floor so the
        // failure must surface as the total-squad guard, not the position
        // guard which is evaluated after it.
        $this->rebuildFirstTeam([
            'Goalkeeper'       => 2,
            'Centre-Back'      => 7,
            'Central Midfield' => 5, // +1 U23 target below = 6 midfielders (at the group floor)
            'Centre-Forward'   => 5,
        ]);
        $player = $this->u23FirstTeamPlayer();

        try {
            $this->service->sendDownToReserve($player, $this->game);
            $this->fail('Expected FirstTeamSquadMinimumException when at the squad minimum.');
        } catch (FirstTeamSquadMinimumException $e) {
            $this->assertSame('too_small', $e->type());
            $this->assertSame(20, $e->min());
        }

        $player->refresh();
        $this->assertSame($this->firstTeam->id, $player->team_id, 'Player must stay on the first team when the guard blocks.');
        $this->assertSame(0, GameTransfer::where('game_player_id', $player->id)->count());
    }

    public function test_blocks_when_send_down_would_breach_a_position_group_minimum(): void
    {
        // Total stays above 20 so the squad-size guard passes, but the
        // target's position group sits exactly at its floor (6 midfielders
        // including the target). Removing them would drop the group to 5,
        // below the Midfielder minimum of 6 — the guard must catch this.
        $this->rebuildFirstTeam([
            'Goalkeeper'       => 3,
            'Centre-Back'      => 8,
            'Central Midfield' => 5, // +1 U23 target below = 6 midfielders (at the group floor)
            'Centre-Forward'   => 6,
        ]);
        $player = $this->u23FirstTeamPlayer();

        try {
            $this->service->sendDownToReserve($player, $this->game);
            $this->fail('Expected FirstTeamSquadMinimumException when a position group sits at its floor.');
        } catch (FirstTeamSquadMinimumException $e) {
            $this->assertSame('position_minimum', $e->type());
            $this->assertSame('Midfielder', $e->group());
            $this->assertSame(6, $e->min());
        }

        $player->refresh();
        $this->assertSame($this->firstTeam->id, $player->team_id, 'Player must stay on the first team when the guard blocks.');
        $this->assertSame(0, GameTransfer::where('game_player_id', $player->id)->count());
    }

    /**
     * Seed first-team filler players in the given position mix. Every filler
     * is aged well above the U23 cutoff so they can't accidentally satisfy
     * the U23 gate if a test grabs one as the target by mistake.
     *
     * @param  array<string, int>  $positions  position-name → count
     */
    private function fillFirstTeam(array $positions): void
    {
        foreach ($positions as $position => $count) {
            for ($i = 0; $i < $count; $i++) {
                GamePlayer::factory()
                    ->forGame($this->game)
                    ->forTeam($this->firstTeam)
                    ->create([
                        'position'      => $position,
                        'date_of_birth' => '1995-06-15',
                    ]);
            }
        }
    }

    /**
     * @param  array<string, int>  $positions  position-name → count
     */
    private function rebuildFirstTeam(array $positions): void
    {
        GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->firstTeam->id)
            ->delete();
        $this->fillFirstTeam($positions);
    }

    /**
     * @param  array<string, mixed>  $attrs  extra GamePlayer attribute overrides
     */
    private function u23FirstTeamPlayer(array $attrs = []): GamePlayer
    {
        return GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->firstTeam)
            ->create(array_merge([
                'date_of_birth' => '2003-06-15', // clearly inside the season-2025 U23 cutoff
                'position'      => 'Central Midfield',
            ], $attrs));
    }
}
