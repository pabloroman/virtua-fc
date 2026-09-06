<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\MatchEvent;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\DTOs\MatchEventData;
use App\Modules\Match\Services\AIMatchResolver;
use App\Modules\Match\Services\MatchEventRepository;
use App\Modules\Match\Services\MatchSimulator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesLineups;

/**
 * A ghost — a cup entrant seeded with no squad — can score, and its goals are
 * unattributed: ordinary goal events for its own team whose scorer is
 * `MatchEvent::UNATTRIBUTED_PLAYER_ID`, an id that references no player row.
 *
 * That is only possible because `match_events` has no foreign key on
 * `game_player_id` any more, and only a test that actually writes the row
 * proves it. The rest of the model is covered by EmptySquadSimulationTest,
 * which never touches the database.
 */
class SquadlessCupEntrantGoalsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesLineups;

    private Game $game;
    private Team $leagueTeam;
    private Team $ghost;
    private Competition $competition;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->leagueTeam = Team::factory()->create(['name' => 'League Club']);
        $this->ghost = Team::factory()->create(['name' => 'Village Rovers']);

        $this->competition = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $this->leagueTeam->id,
            'competition_id' => $this->competition->id,
            'season' => '2024',
            'current_date' => '2024-08-15',
        ]);
    }

    public function test_a_ghosts_goal_persists_without_a_player_row(): void
    {
        $match = $this->createMatch();

        $repository = new MatchEventRepository;
        $repository->bulkInsert(
            collect([MatchEventData::unattributedGoal($this->ghost->id, 34)]),
            $this->game->id,
            $match->id,
        );

        $event = MatchEvent::where('game_match_id', $match->id)->sole();

        $this->assertSame(MatchEvent::TYPE_GOAL, $event->event_type);
        $this->assertSame($this->ghost->id, $event->team_id);
        $this->assertTrue($event->isUnattributed());
        $this->assertNull($event->gamePlayer, 'the sentinel references no player row');
        $this->assertSame('Village Rovers', $event->player_name, 'it reads as the club that scored it');
    }

    public function test_a_squadless_side_is_never_left_with_a_goal_no_event_explains(): void
    {
        $players = $this->createLineup($this->game, $this->leagueTeam, 11, 75);
        $simulator = new MatchSimulator;
        $match = $this->createMatch();
        $repository = new MatchEventRepository;

        // Enough runs that the ghost scores in some of them.
        for ($i = 0; $i < 40; $i++) {
            $output = $simulator->simulate($this->leagueTeam, $this->ghost, $players, collect());

            MatchEvent::where('game_match_id', $match->id)->delete();
            $repository->bulkInsert($output->result->events, $this->game->id, $match->id);

            $match->update([
                'home_score' => $output->result->homeScore,
                'away_score' => $output->result->awayScore,
            ]);

            $this->assertScoreMatchesEvents($match->fresh());
        }
    }

    public function test_the_ai_resolver_backs_a_ghosts_scoreline_with_events(): void
    {
        $this->createLineup($this->game, $this->leagueTeam, 11, 75);

        $allPlayers = GamePlayer::where('game_id', $this->game->id)->get()->groupBy('team_id');
        $resolver = new AIMatchResolver;

        $scoredAtLeastOnce = false;

        for ($i = 0; $i < 40; $i++) {
            $match = $this->createMatch();
            $result = $resolver->resolveMatches(collect([$match]), $allPlayers, $this->game)[0];

            $ghostGoals = collect($result['events'])->filter(
                fn (array $e) => $e['event_type'] === 'goal' && $e['team_id'] === $this->ghost->id,
            );

            $this->assertCount($result['awayScore'], $ghostGoals, "iteration {$i}");
            $this->assertTrue(
                $ghostGoals->every(fn (array $e) => $e['game_player_id'] === MatchEvent::UNATTRIBUTED_PLAYER_ID),
                "a squad-less side has no scorer to credit (iteration {$i})",
            );
            $this->assertCount(
                0,
                collect($result['events'])->filter(fn (array $e) => $e['event_type'] === 'own_goal'),
                "no own goal is invented to explain a ghost's goals (iteration {$i})",
            );

            $scoredAtLeastOnce = $scoredAtLeastOnce || $result['awayScore'] > 0;
        }

        $this->assertTrue($scoredAtLeastOnce, 'a ghost should score at least once in 40 AI-resolved ties');
    }

    private function createMatch(): GameMatch
    {
        return GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->competition->id,
            'round_number' => 1,
            'home_team_id' => $this->leagueTeam->id,
            'away_team_id' => $this->ghost->id,
            'scheduled_date' => Carbon::parse('2024-08-16'),
            'played' => true,
            'home_score' => 0,
            'away_score' => 0,
            'first_half_stoppage' => 1,
            'second_half_stoppage' => 3,
        ]);
    }

    /**
     * The invariant everything here exists to protect: every goal on the
     * scoreboard has an event, on the right side.
     */
    private function assertScoreMatchesEvents(GameMatch $match): void
    {
        $tally = ['home' => 0, 'away' => 0];

        $events = MatchEvent::where('game_match_id', $match->id)
            ->whereIn('event_type', [MatchEvent::TYPE_GOAL, MatchEvent::TYPE_OWN_GOAL])
            ->get();

        foreach ($events as $event) {
            $forHome = $event->event_type === MatchEvent::TYPE_GOAL
                ? $event->team_id === $match->home_team_id
                : $event->team_id !== $match->home_team_id;

            $tally[$forHome ? 'home' : 'away']++;
        }

        $this->assertSame((int) $match->home_score, $tally['home'], 'home score is not backed by events');
        $this->assertSame((int) $match->away_score, $tally['away'], 'away score is not backed by events');
    }
}
