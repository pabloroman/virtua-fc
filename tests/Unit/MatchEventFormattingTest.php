<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Modules\Match\Services\MatchResimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchEventFormattingTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private GameMatch $match;

    protected function setUp(): void
    {
        parent::setUp();
        $this->game = Game::factory()->create(['current_date' => '2025-10-01']);
        $this->match = GameMatch::factory()->forGame($this->game)->create();
    }

    private function createMatchEvent(array $attributes): MatchEvent
    {
        return MatchEvent::create(array_merge([
            'game_id' => $this->game->id,
            'game_match_id' => $this->match->id,
        ], $attributes));
    }

    public function test_assist_pairs_with_goal_from_same_team(): void
    {
        $teamA = Team::factory()->create();
        $scorer = GamePlayer::factory()->forGame($this->game)->forTeam($teamA)->create();
        $assister = GamePlayer::factory()->forGame($this->game)->forTeam($teamA)->create();

        $goal = $this->createMatchEvent([
            'game_player_id' => $scorer->id,
            'team_id' => $teamA->id,
            'minute' => 10,
            'event_type' => 'goal',
        ]);

        $assist = $this->createMatchEvent([
            'game_player_id' => $assister->id,
            'team_id' => $teamA->id,
            'minute' => 10,
            'event_type' => 'assist',
        ]);

        $events = MatchEvent::with('gamePlayer.player')->whereIn('id', [$goal->id, $assist->id])->get();
        $result = MatchResimulationService::formatMatchEvents($events);

        $goalEvent = collect($result)->firstWhere('type', 'goal');

        $this->assertEquals($assister->player->name, $goalEvent['assistPlayerName']);
    }

    public function test_assist_not_paired_with_goal_from_different_team(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $scorerA = GamePlayer::factory()->forGame($this->game)->forTeam($teamA)->create();
        $scorerB = GamePlayer::factory()->forGame($this->game)->forTeam($teamB)->create();
        $assisterA = GamePlayer::factory()->forGame($this->game)->forTeam($teamA)->create();

        $goalA = $this->createMatchEvent([
            'game_player_id' => $scorerA->id,
            'team_id' => $teamA->id,
            'minute' => 10,
            'event_type' => 'goal',
        ]);

        $goalB = $this->createMatchEvent([
            'game_player_id' => $scorerB->id,
            'team_id' => $teamB->id,
            'minute' => 10,
            'event_type' => 'goal',
        ]);

        $assistA = $this->createMatchEvent([
            'game_player_id' => $assisterA->id,
            'team_id' => $teamA->id,
            'minute' => 10,
            'event_type' => 'assist',
        ]);

        $events = MatchEvent::with('gamePlayer.player')
            ->whereIn('id', [$goalA->id, $goalB->id, $assistA->id])
            ->get();
        $result = MatchResimulationService::formatMatchEvents($events);

        $goals = collect($result)->where('type', 'goal');
        $teamAGoal = $goals->firstWhere('teamId', $teamA->id);
        $teamBGoal = $goals->firstWhere('teamId', $teamB->id);

        $this->assertEquals($assisterA->player->name, $teamAGoal['assistPlayerName']);
        $this->assertArrayNotHasKey('assistPlayerName', $teamBGoal);
    }

    public function test_own_goal_does_not_get_assist(): void
    {
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();
        $ownGoalScorer = GamePlayer::factory()->forGame($this->game)->forTeam($teamB)->create();
        $assisterA = GamePlayer::factory()->forGame($this->game)->forTeam($teamA)->create();

        $ownGoal = $this->createMatchEvent([
            'game_player_id' => $ownGoalScorer->id,
            'team_id' => $teamB->id,
            'minute' => 15,
            'event_type' => 'own_goal',
        ]);

        // An assist from a different team at the same minute should not pair with the own goal
        $assist = $this->createMatchEvent([
            'game_player_id' => $assisterA->id,
            'team_id' => $teamA->id,
            'minute' => 15,
            'event_type' => 'assist',
        ]);

        $events = MatchEvent::with('gamePlayer.player')
            ->whereIn('id', [$ownGoal->id, $assist->id])
            ->get();
        $result = MatchResimulationService::formatMatchEvents($events);

        $ownGoalEvent = collect($result)->firstWhere('type', 'own_goal');

        $this->assertArrayNotHasKey('assistPlayerName', $ownGoalEvent);
    }

    public function test_goal_without_assist_has_no_assist_player_name(): void
    {
        $team = Team::factory()->create();
        $scorer = GamePlayer::factory()->forGame($this->game)->forTeam($team)->create();

        $goal = $this->createMatchEvent([
            'game_player_id' => $scorer->id,
            'team_id' => $team->id,
            'minute' => 25,
            'event_type' => 'goal',
        ]);

        $events = MatchEvent::with('gamePlayer.player')->whereIn('id', [$goal->id])->get();
        $result = MatchResimulationService::formatMatchEvents($events);

        $goalEvent = collect($result)->firstWhere('type', 'goal');

        $this->assertArrayNotHasKey('assistPlayerName', $goalEvent);
    }
}
