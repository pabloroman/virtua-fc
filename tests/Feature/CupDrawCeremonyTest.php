<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ceremony gate in ShowGame and the dismiss round-trip.
 *
 * The load-bearing case is test_a_pending_live_match_wins: the draw for a round
 * that starts weeks later must never interrupt "go play the match you just
 * advanced into", and the marker has to survive that detour intact.
 */
class CupDrawCeremonyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Game $game;
    private Team $userTeam;

    protected function setUp(): void
    {
        parent::setUp();

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga', 'tier' => 1]);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'name' => 'Copa del Rey', 'season' => '2025']);

        $this->user = User::factory()->create();
        $this->userTeam = Team::factory()->create();

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
            // Past the welcome / setup gates, which run before the ceremony gate.
            'needs_welcome' => false,
            'needs_new_season_setup' => false,
            'setup_completed_at' => Carbon::parse('2025-07-01'),
        ]);
    }

    private function queueDraw(int $round = 3): void
    {
        foreach ([$this->userTeam, Team::factory()->create()] as $home) {
            CupTie::factory()->forGame($this->game)->inRound($round)
                ->between($home, Team::factory()->create())
                ->create(['competition_id' => 'ESPCUP']);
        }

        $this->game->update(['pending_draw_reveal' => [['competition_id' => 'ESPCUP', 'round' => $round]]]);
    }

    public function test_dashboard_redirects_to_the_ceremony_when_a_draw_is_pending(): void
    {
        $this->queueDraw();

        $this->actingAs($this->user)
            ->get(route('show-game', $this->game->id))
            ->assertRedirect(route('game.cup-draw', $this->game->id));
    }

    public function test_dashboard_renders_normally_with_no_pending_draw(): void
    {
        $this->actingAs($this->user)
            ->get(route('show-game', $this->game->id))
            ->assertDontSee(route('game.cup-draw', $this->game->id));
    }

    public function test_a_pending_live_match_wins_and_leaves_the_marker_intact(): void
    {
        $this->queueDraw();

        $this->game->update(['matchday_advance_result' => [
            'type' => 'live_match',
            'matchId' => '00000000-0000-4000-8000-000000000001',
        ]]);

        $this->actingAs($this->user)
            ->get(route('show-game', $this->game->id))
            ->assertRedirect(route('game.live-match', [
                'gameId' => $this->game->id,
                'matchId' => '00000000-0000-4000-8000-000000000001',
            ]));

        $this->assertNotNull(
            $this->game->fresh()->pending_draw_reveal,
            'The draw must still be waiting once the match is out of the way'
        );
    }

    public function test_dismiss_clears_the_draw_and_returns_to_the_dashboard(): void
    {
        $this->queueDraw();

        $this->actingAs($this->user)
            ->post(route('game.cup-draw.dismiss', $this->game->id))
            ->assertRedirect(route('show-game', $this->game->id));

        $this->assertNull($this->game->fresh()->pending_draw_reveal);
    }

    public function test_a_second_queued_draw_is_shown_after_the_first_is_dismissed(): void
    {
        $this->queueDraw(3);
        $this->queueDraw(4);
        $this->game->update(['pending_draw_reveal' => [
            ['competition_id' => 'ESPCUP', 'round' => 3],
            ['competition_id' => 'ESPCUP', 'round' => 4],
        ]]);

        $this->actingAs($this->user)->post(route('game.cup-draw.dismiss', $this->game->id));

        $this->assertSame(
            [['competition_id' => 'ESPCUP', 'round' => 4]],
            $this->game->fresh()->pending_draw_reveal
        );

        $this->actingAs($this->user)
            ->get(route('show-game', $this->game->id))
            ->assertRedirect(route('game.cup-draw', $this->game->id));
    }

    public function test_the_ceremony_is_behind_game_ownership(): void
    {
        $this->queueDraw();

        $this->actingAs(User::factory()->create())
            ->get(route('game.cup-draw', $this->game->id))
            ->assertStatus(403);
    }
}
