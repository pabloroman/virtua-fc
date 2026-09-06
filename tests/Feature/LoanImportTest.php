<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerTemplate;
use App\Models\Loan;
use App\Models\Team;
use App\Models\User;
use App\Modules\Season\Jobs\SetupNewGame;
use App\Modules\Transfer\Services\LoanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers importing real-world loans from the squad-template data: SetupNewGame
 * turns a template's loan_from_transfermarkt_id into a per-game loan that
 * returns the player to the owning club at season end — or, when that club
 * isn't part of the game, leaves no parent so the player is freed.
 *
 * A club counts as part of the game if it fields a squad OR is entered in one
 * of its competitions; the two are independent, so both are covered here.
 */
class LoanImportTest extends TestCase
{
    use RefreshDatabase;

    private const SEASON = '2024';

    private User $user;
    private Team $userTeam;       // the manager's club
    private Team $borrowingTeam;  // where loaned players currently play
    private Team $ownerInGame;    // an owning club that fields a squad in the game
    private Team $ownerCupOnly;   // an owning club entered in a cup, with no squad
    private Team $ownerOutOfGame; // an owning club in neither
    private Game $game;
    private int $nextSquadNumber = 7; // keep squad numbers unique per borrowing team

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create(['name' => 'User Team']);
        $this->borrowingTeam = Team::factory()->create(['name' => 'Borrowing Team']);
        $this->ownerInGame = Team::factory()->create(['name' => 'Owner In Game']);
        $this->ownerCupOnly = Team::factory()->create(['name' => 'Owner Cup Only']);
        $this->ownerOutOfGame = Team::factory()->create(['name' => 'Owner Out Of Game']);
        Competition::factory()->league()->create(['id' => 'ESP1']);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => self::SEASON,
            'current_date' => '2024-08-15',
        ]);

        // One owner is present by fielding a squad...
        GamePlayer::factory()->forGame($this->game)->forTeam($this->ownerInGame)->create();

        // ...and one only by being entered in a cup. A club can enter a
        // domestic cup without being playable, so it has no squad in this game
        // and appears nowhere in game_players.
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => 'ESPCUP',
            'team_id' => $this->ownerCupOnly->id,
            'entry_round' => 1,
        ]);
    }

    public function test_setup_materialises_loans_with_correct_parents(): void
    {
        // Player A: on loan at the borrowing club, owner IS in the game.
        $playerA = $this->createGamePlayerOnBorrowingTeam();
        $this->createTemplate($playerA->player_id, loanFrom: $this->ownerInGame->transfermarkt_id);

        // Player B: on loan at the borrowing club, owner is NOT in the game.
        $playerB = $this->createGamePlayerOnBorrowingTeam();
        $this->createTemplate($playerB->player_id, loanFrom: $this->ownerOutOfGame->transfermarkt_id);

        // Player C: a normal squad member, no loan info.
        $playerC = $this->createGamePlayerOnBorrowingTeam();
        $this->createTemplate($playerC->player_id, loanFrom: null);

        // Player D: owner is entered in a cup only, so it fields no squad here.
        $playerD = $this->createGamePlayerOnBorrowingTeam();
        $this->createTemplate($playerD->player_id, loanFrom: $this->ownerCupOnly->transfermarkt_id);

        $this->invokeLoanInit();

        // A → returns to the owning club at season end.
        $loanA = Loan::where('game_player_id', $playerA->id)->first();
        $this->assertNotNull($loanA, 'A loan should be created for an in-game owner');
        $this->assertSame($this->ownerInGame->id, $loanA->parent_team_id);
        $this->assertSame($this->borrowingTeam->id, $loanA->loan_team_id);
        $this->assertSame(Loan::STATUS_ACTIVE, $loanA->status);
        $this->assertSame('2025-06-30', $loanA->return_at->toDateString(),
            'Imported loans are season-long: they return at the first June 30');
        $this->assertSame('2024-08-15', $loanA->started_at->toDateString());

        // B → no parent, so the player will be freed when the loan ends.
        $loanB = Loan::where('game_player_id', $playerB->id)->first();
        $this->assertNotNull($loanB, 'A loan should still be created when the owner is out of game');
        $this->assertNull($loanB->parent_team_id,
            'An owner outside the game leaves the loan parentless (free agent on return)');
        $this->assertSame($this->borrowingTeam->id, $loanB->loan_team_id);

        // C → never on loan, so no loan record.
        $this->assertFalse(Loan::where('game_player_id', $playerC->id)->exists(),
            'Players with no loan info must not get a loan record');

        // D → a cup entry is enough to be an owner, even with no squad in the
        // game. Without this the player would be freed instead of going home.
        $loanD = Loan::where('game_player_id', $playerD->id)->first();
        $this->assertNotNull($loanD, 'A loan should be created for a cup-only owner');
        $this->assertSame($this->ownerCupOnly->id, $loanD->parent_team_id,
            'A club entered only in a cup still owns its loaned-out players');
    }

    public function test_initialize_loans_is_idempotent(): void
    {
        $player = $this->createGamePlayerOnBorrowingTeam();
        $this->createTemplate($player->player_id, loanFrom: $this->ownerInGame->transfermarkt_id);

        $this->invokeLoanInit();
        $this->invokeLoanInit();

        $this->assertSame(1, Loan::where('game_player_id', $player->id)->count(),
            'Re-running setup must not duplicate loans');
    }

    public function test_returning_a_parentless_loan_frees_the_player(): void
    {
        $player = $this->createGamePlayerOnBorrowingTeam();
        $loan = Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => null,
            'loan_team_id' => $this->borrowingTeam->id,
            'started_at' => $this->game->current_date,
            'return_at' => $this->game->getSeasonEndDateFor($this->game->current_date),
            'status' => Loan::STATUS_ACTIVE,
        ]);

        app(LoanService::class)->returnLoan($loan);

        $player->refresh();
        $this->assertNull($player->team_id, 'A parentless loan frees the player (team_id = null)');
        $this->assertNull($player->number);
        $this->assertSame(Loan::STATUS_COMPLETED, $loan->refresh()->status);
    }

    public function test_return_all_loans_handles_parentless_loan_without_error(): void
    {
        $player = $this->createGamePlayerOnBorrowingTeam();
        Loan::create([
            'game_id' => $this->game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => null,
            'loan_team_id' => $this->borrowingTeam->id,
            'started_at' => $this->game->current_date,
            'return_at' => $this->game->getSeasonEndDateFor($this->game->current_date),
            'status' => Loan::STATUS_ACTIVE,
        ]);

        // Eager-loads parentTeam (null here) then returns each loan — must not NPE.
        $returned = app(LoanService::class)->returnAllLoans($this->game);

        $this->assertCount(1, $returned);
        $this->assertNull($player->refresh()->team_id);
    }

    // =====================================================
    // Helpers
    // =====================================================

    private function createGamePlayerOnBorrowingTeam(): GamePlayer
    {
        return GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->borrowingTeam)
            ->create(['number' => $this->nextSquadNumber++]);
    }

    private function createTemplate(string $playerId, ?string $loanFrom): GamePlayerTemplate
    {
        return GamePlayerTemplate::create([
            'season' => self::SEASON,
            'player_id' => $playerId,
            'team_id' => $this->borrowingTeam->id,
            'position' => 'Central Midfield',
            'loan_from_transfermarkt_id' => $loanFrom === null ? null : (string) $loanFrom,
        ]);
    }

    /**
     * Invoke SetupNewGame::initializeLoansFromTemplates (private) with the
     * job's currentDate primed, mirroring how handle() calls it after the
     * game_players have been materialised.
     */
    private function invokeLoanInit(): void
    {
        $job = new SetupNewGame(
            $this->game->id,
            $this->userTeam->id,
            'ESP1',
            self::SEASON,
            Game::MODE_CAREER,
        );

        $currentDate = new \ReflectionProperty(SetupNewGame::class, 'currentDate');
        $currentDate->setAccessible(true);
        $currentDate->setValue($job, $this->game->current_date);

        $method = new \ReflectionMethod(SetupNewGame::class, 'initializeLoansFromTemplates');
        $method->setAccessible(true);
        $method->invoke($job, $this->game);
    }
}
