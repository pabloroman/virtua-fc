<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ReputationUpdateProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReputationUpdateProcessorTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;
    private ReputationUpdateProcessor $processor;
    private Competition $league;
    private Team $userTeam;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationService = Mockery::mock(NotificationService::class);
        // Default: no notifications expected; individual tests override.
        $this->notificationService->shouldReceive('create')->byDefault();

        $this->processor = new ReputationUpdateProcessor($this->notificationService);

        $this->league = Competition::factory()->league()->create(['tier' => 1]);
        $this->userTeam = Team::factory()->create();
        $this->game = Game::factory()
            ->forTeam($this->userTeam)
            ->inCompetition($this->league->id)
            ->create();
    }

    public function test_title_winner_gains_points(): void
    {
        // Established team gets +40 for 1st place, minus 5 gravity = +35 net.
        $this->enterLeague($this->userTeam);
        $reputation = $this->seedReputation(
            $this->userTeam,
            level: ClubProfile::REPUTATION_ESTABLISHED,
            points: 250,
        );
        $this->placeStanding($this->userTeam, position: 1);

        $this->processor->process($this->game, $this->transitionData());

        $this->assertSame(285, $reputation->fresh()->reputation_points);
    }

    public function test_mid_table_team_declines_from_gravity(): void
    {
        // Elite team at mid-table: 0 points gained, 25 lost to gravity.
        $this->enterLeague($this->userTeam);
        $reputation = $this->seedReputation(
            $this->userTeam,
            level: ClubProfile::REPUTATION_ELITE,
            points: 450,
        );
        $this->placeStanding($this->userTeam, position: 12);

        $this->processor->process($this->game, $this->transitionData());

        $this->assertSame(425, $reputation->fresh()->reputation_points);
    }

    public function test_points_never_go_below_zero(): void
    {
        $this->enterLeague($this->userTeam);
        $reputation = $this->seedReputation(
            $this->userTeam,
            level: ClubProfile::REPUTATION_LOCAL,
            points: 5,
        );
        $this->placeStanding($this->userTeam, position: 20); // 18+ = -15

        $this->processor->process($this->game, $this->transitionData());

        $this->assertSame(0, $reputation->fresh()->reputation_points);
    }

    public function test_tier_recalculates_when_points_cross_threshold(): void
    {
        // 195 + 40 (1st) - 5 (gravity) = 230 → crosses into ESTABLISHED (>= 200).
        $this->enterLeague($this->userTeam);
        $reputation = $this->seedReputation(
            $this->userTeam,
            level: ClubProfile::REPUTATION_MODEST,
            baseLevel: ClubProfile::REPUTATION_MODEST,
            points: 195,
        );
        $this->placeStanding($this->userTeam, position: 1);

        $this->processor->process($this->game, $this->transitionData());

        $reputation->refresh();
        $this->assertSame(ClubProfile::REPUTATION_ESTABLISHED, $reputation->reputation_level);
    }

    public function test_sends_notification_when_user_tier_changes(): void
    {
        $this->enterLeague($this->userTeam);
        $this->seedReputation(
            $this->userTeam,
            level: ClubProfile::REPUTATION_MODEST,
            baseLevel: ClubProfile::REPUTATION_MODEST,
            points: 195,
        );
        $this->placeStanding($this->userTeam, position: 1);

        $this->notificationService->shouldReceive('create')->once();

        $this->processor->process($this->game, $this->transitionData());
    }

    public function test_does_not_notify_when_tier_is_unchanged(): void
    {
        $this->enterLeague($this->userTeam);
        $this->seedReputation(
            $this->userTeam,
            level: ClubProfile::REPUTATION_ESTABLISHED,
            points: 250,
        );
        $this->placeStanding($this->userTeam, position: 12); // 0 delta - 5 gravity = 245, still ESTABLISHED

        $this->notificationService->shouldNotReceive('create');

        $this->processor->process($this->game, $this->transitionData());
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function transitionData(): SeasonTransitionData
    {
        return new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->league->id,
        );
    }

    private function enterLeague(Team $team): void
    {
        CompetitionEntry::create([
            'game_id' => $this->game->id,
            'competition_id' => $this->league->id,
            'team_id' => $team->id,
        ]);
    }

    private function seedReputation(
        Team $team,
        string $level,
        int $points,
        ?string $baseLevel = null,
    ): TeamReputation {
        return TeamReputation::create([
            'game_id' => $this->game->id,
            'team_id' => $team->id,
            'reputation_level' => $level,
            'base_reputation_level' => $baseLevel ?? $level,
            'reputation_points' => $points,
            'base_loyalty' => 70,
            'loyalty_points' => 70,
        ]);
    }

    private function placeStanding(Team $team, int $position): void
    {
        GameStanding::create([
            'game_id' => $this->game->id,
            'competition_id' => $this->league->id,
            'team_id' => $team->id,
            'position' => $position,
            'played' => 38, // Must be > 0 to be picked up by the processor.
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
