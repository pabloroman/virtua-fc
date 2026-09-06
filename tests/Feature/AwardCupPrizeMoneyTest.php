<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Events\CupTieResolved;
use App\Modules\Match\Listeners\AwardCupPrizeMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prize money is keyed on how many rounds remain after the tie just won, not
 * on the round's own number — a number that means nothing on its own, since
 * round 5 is the Copa del Rey's quarter-final and the Champions League final.
 */
class AwardCupPrizeMoneyTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private Team $userTeam;
    private Team $opponent;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);

        $this->userTeam = Team::factory()->create(['country' => 'ES']);
        $this->opponent = Team::factory()->create(['country' => 'ES']);

        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            'base_season' => '2025',
        ]);
    }

    public function test_winning_the_copa_del_rey_pays_the_final_prize_not_the_first_round_one(): void
    {
        // The Copa's final is round 7. Keyed on the raw round number against a
        // six-entry table, it fell through to the opening round's €100K.
        $this->assertSame(200_000_000, $this->awardFor('ESPCUP', roundNumber: 7));
    }

    public function test_copa_rounds_are_paid_by_their_distance_from_the_final(): void
    {
        $this->assertSame(100_000_000, $this->awardFor('ESPCUP', roundNumber: 6)); // semi-final
        $this->assertSame(50_000_000, $this->awardFor('ESPCUP', roundNumber: 5));  // quarter-final
        $this->assertSame(30_000_000, $this->awardFor('ESPCUP', roundNumber: 4));  // round of 16
        $this->assertSame(20_000_000, $this->awardFor('ESPCUP', roundNumber: 3));  // round of 32
    }

    public function test_rounds_earlier_than_the_table_pay_the_opening_round_rate(): void
    {
        // The Copa opens with two rounds beyond the table's five entries.
        $this->assertSame(10_000_000, $this->awardFor('ESPCUP', roundNumber: 2));
        $this->assertSame(10_000_000, $this->awardFor('ESPCUP', roundNumber: 1));
    }

    public function test_champions_league_payouts_are_unchanged(): void
    {
        // Regression guard: the UCL's table already ended on its final round,
        // so counting from the end must leave every figure where it was.
        $this->assertSame(2_500_000_000, $this->awardFor('UCL', roundNumber: 5)); // final
        $this->assertSame(1_850_000_000, $this->awardFor('UCL', roundNumber: 4)); // semi-final
        $this->assertSame(1_100_000_000, $this->awardFor('UCL', roundNumber: 1)); // knockout playoff
    }

    public function test_the_efl_cup_pays_less_than_the_fa_cup_at_every_stage(): void
    {
        // Both English cups run in the 2026 data.
        $this->game->update(['base_season' => '2026']);

        // Finals: the FA Cup is worth twice the EFL Cup.
        $this->assertSame(200_000_000, $this->awardFor('ENGCUP', roundNumber: 6));
        $this->assertSame(100_000_000, $this->awardFor('ENGLC', roundNumber: 5));

        // Semi-finals, likewise.
        $this->assertSame(100_000_000, $this->awardFor('ENGCUP', roundNumber: 5));
        $this->assertSame(50_000_000, $this->awardFor('ENGLC', roundNumber: 4));

        // And the shorter bracket must not make the EFL Cup's opening round
        // worth more than the FA Cup's, which sharing one table would.
        $this->assertSame(10_000_000, $this->awardFor('ENGCUP', roundNumber: 1));
        $this->assertSame(10_000_000, $this->awardFor('ENGLC', roundNumber: 1));
    }

    public function test_nothing_is_paid_when_the_user_is_not_the_winner(): void
    {
        $this->assertSame(0, $this->awardFor('ESPCUP', roundNumber: 7, winner: $this->opponent));
    }

    /**
     * Resolve one cup tie and return what the user's club was paid, in cents.
     */
    private function awardFor(string $competitionId, int $roundNumber, ?Team $winner = null): int
    {
        $winner ??= $this->userTeam;

        $competition = Competition::firstOrCreate(
            ['id' => $competitionId],
            [
                'name' => $competitionId,
                'country' => 'ES',
                'type' => 'cup',
                'role' => Competition::ROLE_DOMESTIC_CUP,
                'handler_type' => 'knockout_cup',
                'season' => $this->game->base_season,
                'tier' => 0,
            ],
        );

        $tie = CupTie::create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'round_number' => $roundNumber,
            'home_team_id' => $winner->id,
            'away_team_id' => $this->opponent->id,
            'winner_id' => $winner->id,
            'completed' => true,
        ]);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $competitionId,
            'home_team_id' => $winner->id,
            'away_team_id' => $this->opponent->id,
            'round_number' => $roundNumber,
        ]);

        FinancialTransaction::where('game_id', $this->game->id)->delete();

        (new AwardCupPrizeMoney())->handle(
            new CupTieResolved($tie, $winner->id, $match, $this->game, $competition),
        );

        return (int) FinancialTransaction::where('game_id', $this->game->id)
            ->where('category', FinancialTransaction::CATEGORY_CUP_BONUS)
            ->sum('amount');
    }
}
