<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use App\Modules\Lineup\Services\SubstitutionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the "Skip to end" flow that re-simulates the remainder of a user
 * match with AI substitutions enabled for the user's team. This is the only
 * path that auto-subs the user team — normal minute-by-minute play still
 * leaves the user in full control.
 */
class SkipMatchToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;
    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->playerTeam = Team::factory()->create(['name' => 'Player Team']);
        $this->opponentTeam = Team::factory()->create(['name' => 'Opponent Team']);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->playerTeam->competitions()->attach($this->competition->id, ['season' => '2024']);
        $this->opponentTeam->competitions()->attach($this->competition->id, ['season' => '2024']);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->playerTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2024-08-15',
        ]);

        // 18-player squads guarantee a 7-player bench after the starting 11.
        $this->createSquad($this->playerTeam, 18);
        $this->createSquad($this->opponentTeam, 18);

        $homeLineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $awayLineup = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->opponentTeam->id)
            ->limit(11)
            ->pluck('id')
            ->toArray();

        $this->match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->playerTeam->id,
            'away_team_id' => $this->opponentTeam->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
            'played' => true,
            'home_score' => 1,
            'away_score' => 0,
            'home_lineup' => $homeLineup,
            'away_lineup' => $awayLineup,
            'home_possession' => 55,
            'away_possession' => 45,
            'substitutions' => [],
        ]);

        $this->game->update(['pending_finalization_match_id' => $this->match->id]);
    }

    public function test_skip_to_end_generates_user_team_substitutions(): void
    {
        $this->actingAs($this->user);

        $userTeamSubsBefore = MatchEvent::where('game_match_id', $this->match->id)
            ->where('event_type', 'substitution')
            ->where('team_id', $this->playerTeam->id)
            ->count();
        $this->assertSame(0, $userTeamSubsBefore);

        $response = $this->postJson(
            route('game.match.skip-to-end', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 20,
                'previousSubstitutions' => [],
            ],
        );

        $response->assertOk();
        $response->assertJson(['autoSubsApplied' => true]);

        $userTeamSubsAfter = MatchEvent::where('game_match_id', $this->match->id)
            ->where('event_type', 'substitution')
            ->where('team_id', $this->playerTeam->id)
            ->get();

        $this->assertGreaterThan(
            0,
            $userTeamSubsAfter->count(),
            'Skip-to-end must generate at least one user-team substitution event',
        );
        $this->assertLessThanOrEqual(
            SubstitutionService::MAX_SUBSTITUTIONS,
            $userTeamSubsAfter->count(),
            'User-team sub count must stay within the 5-sub limit',
        );

        // Sub minutes should fall in the remainder [skip minute + 1, 93] and
        // typically within the AI window [min_minute, max_minute].
        foreach ($userTeamSubsAfter as $sub) {
            $this->assertGreaterThan(
                20,
                $sub->minute,
                'Auto-sub minute must be after the skip minute',
            );
            $this->assertLessThanOrEqual(
                93,
                $sub->minute,
                'Auto-sub minute must not exceed end of regular time',
            );
        }

        // The substitutions JSON on the match row must be rebuilt to contain
        // the new user-team entries (so any subsequent bookkeeping stays
        // consistent).
        $this->match->refresh();
        $userSubsInJson = collect($this->match->substitutions ?? [])
            ->filter(fn ($s) => ($s['team_id'] ?? null) === $this->playerTeam->id);
        $this->assertCount(
            $userTeamSubsAfter->count(),
            $userSubsInJson,
            'Rebuilt substitutions JSON must reflect the newly-generated user auto-subs',
        );
    }

    public function test_skip_to_end_is_noop_when_user_has_used_all_subs(): void
    {
        $this->actingAs($this->user);

        $lineupIds = $this->match->home_lineup;
        $benchIds = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->whereNotIn('id', $lineupIds)
            ->limit(5)
            ->pluck('id')
            ->toArray();

        $previousSubs = [];
        foreach ($benchIds as $i => $inId) {
            $previousSubs[] = [
                'playerOutId' => $lineupIds[$i],
                'playerInId' => $inId,
                'minute' => 55 + $i,
            ];
        }

        $response = $this->postJson(
            route('game.match.skip-to-end', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 70,
                'previousSubstitutions' => $previousSubs,
            ],
        );

        $response->assertOk();
        $response->assertJson([
            'autoSubsApplied' => false,
            'newEvents' => [],
        ]);

        // No new user-team sub events should have been generated.
        $userTeamSubs = MatchEvent::where('game_match_id', $this->match->id)
            ->where('event_type', 'substitution')
            ->where('team_id', $this->playerTeam->id)
            ->count();

        $this->assertSame(
            0,
            $userTeamSubs,
            'No auto-subs should be generated when the user has already used all 5 subs',
        );
    }

    public function test_skip_to_end_rejects_request_when_match_is_not_in_progress(): void
    {
        $this->actingAs($this->user);

        $this->game->update(['pending_finalization_match_id' => null]);

        $response = $this->postJson(
            route('game.match.skip-to-end', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 20,
                'previousSubstitutions' => [],
            ],
        );

        $response->assertStatus(403);
    }

    public function test_skip_to_end_is_noop_after_minute_89(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(
            route('game.match.skip-to-end', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 90,
                'previousSubstitutions' => [],
            ],
        );

        $response->assertOk();
        $response->assertJson(['autoSubsApplied' => false]);

        $userTeamSubs = MatchEvent::where('game_match_id', $this->match->id)
            ->where('event_type', 'substitution')
            ->where('team_id', $this->playerTeam->id)
            ->count();
        $this->assertSame(0, $userTeamSubs);
    }

    /**
     * Seed a squad of $size players with a realistic position distribution so
     * the bench has a mix of positions for the AI substitution logic.
     */
    private function createSquad(Team $team, int $size): void
    {
        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->goalkeeper()
            ->create();

        foreach (['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back'] as $position) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $position]);
        }

        GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($team)
            ->count(4)
            ->create(['position' => 'Central Midfield']);

        foreach (['Centre-Forward', 'Centre-Forward'] as $position) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $position]);
        }

        // Bench-only positions to round out to $size players.
        $positionsCycle = ['Centre-Back', 'Left-Back', 'Right-Back', 'Central Midfield', 'Centre-Forward', 'Goalkeeper', 'Right Winger'];
        $bench = $size - 11;
        for ($i = 0; $i < $bench; $i++) {
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->create(['position' => $positionsCycle[$i % count($positionsCycle)]]);
        }
    }
}
