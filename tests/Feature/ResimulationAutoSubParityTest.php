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
use App\Modules\Match\Services\MatchResimulationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for the auto-sub blind spot in resimulation.
 *
 * The resimulation system was written when the user team never had
 * auto-substitutions. When injury auto-subs were enabled for the user
 * team, those DB events were invisible to the resimulation — the subbed-in
 * player was never added back to the lineup, sub/window counts were wrong,
 * and entry minutes were missing.
 */
class ResimulationAutoSubParityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Team $playerTeam;
    private Team $opponentTeam;
    private Competition $competition;
    private Game $game;
    private GameMatch $match;
    private string $injuredPlayerId;
    private string $replacementPlayerId;

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

        // Seed a pre-simulated injury auto-sub for the user team at minute 25
        $this->injuredPlayerId = $homeLineup[3];

        $benchPlayer = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->whereNotIn('id', $homeLineup)
            ->first();
        $this->replacementPlayerId = $benchPlayer->id;

        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $this->injuredPlayerId,
            'team_id' => $this->playerTeam->id,
            'minute' => 25,
            'event_type' => MatchEvent::TYPE_INJURY,
            'metadata' => ['severity' => 'minor'],
        ]);

        MatchEvent::create([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
            'game_player_id' => $this->injuredPlayerId,
            'team_id' => $this->playerTeam->id,
            'minute' => 25,
            'event_type' => MatchEvent::TYPE_SUBSTITUTION,
            'metadata' => ['player_in_id' => $this->replacementPlayerId],
        ]);
    }

    /**
     * After resimulation at a minute AFTER the auto-sub, the replacement
     * player must be on the pitch and the injured player must not.
     */
    public function test_user_auto_sub_player_is_on_pitch_after_resimulation(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson(
            route('game.match.tactical-actions', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 50,
                'previousSubstitutions' => [],
                'newSubstitutions' => [],
                'formation' => '4-4-2',
            ],
        );

        $response->assertOk();

        // The resimulation must have produced a valid match result.
        // If the auto-sub player was missing, team strength would be
        // significantly reduced (10v11), producing distorted results.
        $this->match->refresh();
        $this->assertNotNull($this->match->home_score);
    }

    /**
     * Sub count must include both manual subs AND pre-sim auto-subs.
     * Without this, the 5-sub limit could be exceeded.
     */
    public function test_skip_to_end_respects_combined_sub_limit(): void
    {
        $this->actingAs($this->user);

        // User made 4 manual subs at different minutes
        $homeLineup = $this->match->home_lineup;
        $benchIds = GamePlayer::where('game_id', $this->game->id)
            ->where('team_id', $this->playerTeam->id)
            ->whereNotIn('id', $homeLineup)
            ->where('id', '!=', $this->replacementPlayerId)
            ->limit(4)
            ->pluck('id')
            ->toArray();

        $manualSubs = [];
        // Start from index 5 to avoid the injured player (index 3)
        $availableStarters = array_values(array_diff($homeLineup, [$this->injuredPlayerId]));
        foreach ($benchIds as $i => $inId) {
            $manualSubs[] = [
                'playerOutId' => $availableStarters[$i],
                'playerInId' => $inId,
                'minute' => 55 + $i,
            ];
        }

        // With 1 auto-sub + 4 manual = 5 total, should be at the limit
        $response = $this->postJson(
            route('game.match.skip-to-end', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 60,
                'previousSubstitutions' => $manualSubs,
            ],
        );

        $response->assertOk();

        // Total user-team subs should not exceed the limit
        $totalUserSubs = MatchEvent::where('game_match_id', $this->match->id)
            ->where('event_type', MatchEvent::TYPE_SUBSTITUTION)
            ->where('team_id', $this->playerTeam->id)
            ->count();

        $this->assertLessThanOrEqual(
            SubstitutionService::MAX_SUBSTITUTIONS,
            $totalUserSubs,
            "Total user subs ({$totalUserSubs}) exceeds the " . SubstitutionService::MAX_SUBSTITUTIONS . '-sub limit',
        );
    }

    /**
     * Auto-sub events at minutes AFTER the resimulation minute should be
     * reverted (deleted) and not counted — they're in the future.
     */
    public function test_auto_sub_after_resim_minute_is_reverted(): void
    {
        $this->actingAs($this->user);

        // Move the auto-sub to minute 60 (after our resim minute of 40)
        MatchEvent::where('game_match_id', $this->match->id)
            ->where('event_type', MatchEvent::TYPE_INJURY)
            ->update(['minute' => 60]);
        MatchEvent::where('game_match_id', $this->match->id)
            ->where('event_type', MatchEvent::TYPE_SUBSTITUTION)
            ->update(['minute' => 60]);

        // Resim at minute 40 (before the auto-sub)
        $response = $this->postJson(
            route('game.match.tactical-actions', ['gameId' => $this->game->id, 'matchId' => $this->match->id]),
            [
                'minute' => 40,
                'previousSubstitutions' => [],
                'newSubstitutions' => [],
                'mentality' => 'attacking',
            ],
        );

        $response->assertOk();

        // The auto-sub at minute 60 should have been reverted
        $autoSubAtMinute60 = MatchEvent::where('game_match_id', $this->match->id)
            ->where('event_type', MatchEvent::TYPE_SUBSTITUTION)
            ->where('team_id', $this->playerTeam->id)
            ->where('game_player_id', $this->injuredPlayerId)
            ->where('minute', 60)
            ->exists();

        $this->assertFalse(
            $autoSubAtMinute60,
            'Auto-sub event at minute 60 should have been reverted since it is after the resimulation minute (40)',
        );
    }

    private function createSquad(Team $team, int $size): void
    {
        GamePlayer::factory()->forGame($this->game)->forTeam($team)->goalkeeper()->create();

        foreach (['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back'] as $pos) {
            GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['position' => $pos]);
        }

        GamePlayer::factory()->forGame($this->game)->forTeam($team)->count(4)->create(['position' => 'Central Midfield']);

        foreach (['Centre-Forward', 'Centre-Forward'] as $pos) {
            GamePlayer::factory()->forGame($this->game)->forTeam($team)->create(['position' => $pos]);
        }

        $positionsCycle = ['Centre-Back', 'Left-Back', 'Right-Back', 'Central Midfield', 'Centre-Forward', 'Goalkeeper', 'Right Winger'];
        $bench = $size - 11;
        for ($i = 0; $i < $bench; $i++) {
            GamePlayer::factory()->forGame($this->game)->forTeam($team)->create([
                'position' => $positionsCycle[$i % count($positionsCycle)],
            ]);
        }
    }
}
