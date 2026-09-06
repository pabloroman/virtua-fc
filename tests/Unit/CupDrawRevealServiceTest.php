<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Competition\Services\CupDrawRevealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The guard matrix for queueing a draw ceremony. Every "should not fire" case
 * here is a moment the user would otherwise be shown a celebration for someone
 * else's draw, or a pairing that was never actually drawn.
 */
class CupDrawRevealServiceTest extends TestCase
{
    use RefreshDatabase;

    private CupDrawRevealService $service;
    private Game $game;
    private Team $userTeam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CupDrawRevealService::class);

        Competition::factory()->league()->create(['id' => 'ESP1', 'name' => 'LaLiga', 'tier' => 1]);
        Competition::factory()->knockoutCup()->create(['id' => 'ESPCUP', 'name' => 'Copa del Rey', 'season' => '2025']);

        $this->userTeam = Team::factory()->create();
        $this->game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'team_id' => $this->userTeam->id,
            'competition_id' => 'ESP1',
            'season' => '2025',
        ]);
    }

    /** Stage $count ties in a round, optionally putting the user's team in the first one. */
    private function stageRound(int $round, int $count, bool $includeUser): void
    {
        for ($i = 0; $i < $count; $i++) {
            $home = ($includeUser && $i === 0) ? $this->userTeam : Team::factory()->create();

            CupTie::factory()
                ->forGame($this->game)
                ->inRound($round)
                ->between($home, Team::factory()->create())
                ->create(['competition_id' => 'ESPCUP']);
        }
    }

    public function test_records_a_draw_the_user_is_part_of(): void
    {
        $this->stageRound(3, 4, includeUser: true);

        $this->service->record($this->game, 'ESPCUP', 3);

        $this->assertSame(
            [['competition_id' => 'ESPCUP', 'round' => 3]],
            $this->game->fresh()->pending_draw_reveal
        );
    }

    public function test_does_not_record_a_draw_the_user_is_not_in(): void
    {
        // Covers both "eliminated" and "has not entered yet" — in either case
        // none of the new ties contains the user's team.
        $this->stageRound(3, 4, includeUser: false);

        $this->service->record($this->game, 'ESPCUP', 3);

        $this->assertNull($this->game->fresh()->pending_draw_reveal);
    }

    public function test_does_not_record_a_single_tie_round(): void
    {
        // A final (and a two-team supercup) is not drawn — the pairing follows
        // from the round before it, so there is nothing to reveal.
        $this->stageRound(7, 1, includeUser: true);

        $this->service->record($this->game, 'ESPCUP', 7);

        $this->assertNull($this->game->fresh()->pending_draw_reveal);
    }

    public function test_does_not_record_in_fast_mode(): void
    {
        $this->game->update(['fast_mode_entered_on' => now()]);
        $this->stageRound(3, 4, includeUser: true);

        $this->service->record($this->game, 'ESPCUP', 3);

        $this->assertNull($this->game->fresh()->pending_draw_reveal);
    }

    public function test_a_second_draw_appends_rather_than_replacing(): void
    {
        Competition::factory()->knockoutCup()->create(['id' => 'UCL', 'name' => 'Champions League', 'season' => '2025']);

        $this->stageRound(3, 4, includeUser: true);
        $this->service->record($this->game, 'ESPCUP', 3);

        foreach ([$this->userTeam, Team::factory()->create()] as $home) {
            CupTie::factory()->forGame($this->game)->inRound(4)
                ->between($home, Team::factory()->create())
                ->create(['competition_id' => 'UCL']);
        }
        $this->service->record($this->game->fresh(), 'UCL', 4);

        $this->assertSame([
            ['competition_id' => 'ESPCUP', 'round' => 3],
            ['competition_id' => 'UCL', 'round' => 4],
        ], $this->game->fresh()->pending_draw_reveal);
    }

    public function test_recording_the_same_round_twice_is_idempotent(): void
    {
        $this->stageRound(3, 4, includeUser: true);

        $this->service->record($this->game, 'ESPCUP', 3);
        $this->service->record($this->game->fresh(), 'ESPCUP', 3);

        $this->assertCount(1, $this->game->fresh()->pending_draw_reveal);
    }

    public function test_dismiss_pops_only_the_head(): void
    {
        $this->game->update(['pending_draw_reveal' => [
            ['competition_id' => 'ESPCUP', 'round' => 3],
            ['competition_id' => 'UCL', 'round' => 4],
        ]]);

        $this->service->dismiss($this->game);

        $this->assertSame(
            [['competition_id' => 'UCL', 'round' => 4]],
            $this->game->fresh()->pending_draw_reveal
        );
    }

    public function test_dismissing_the_last_entry_clears_the_column(): void
    {
        $this->game->update(['pending_draw_reveal' => [['competition_id' => 'ESPCUP', 'round' => 3]]]);

        $this->service->dismiss($this->game);

        $this->assertNull($this->game->fresh()->pending_draw_reveal);
    }

    public function test_build_consumes_a_stale_entry_and_returns_null(): void
    {
        // Nothing was ever drawn for this round, so the ceremony has nothing to
        // show. It must consume the entry rather than leave the gate looping.
        $this->game->update(['pending_draw_reveal' => [['competition_id' => 'ESPCUP', 'round' => 3]]]);

        $this->assertNull($this->service->build($this->game));
        $this->assertNull($this->game->fresh()->pending_draw_reveal);
    }
}
