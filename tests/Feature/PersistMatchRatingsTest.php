<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayerMatchRating;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Match\Services\MatchResultProcessor;
use App\Modules\Match\Services\MatchResimulationService;
use App\Modules\Match\Services\MatchRatingCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PersistMatchRatingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $homeTeam;

    private Team $awayTeam;

    private Competition $league;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->homeTeam = Team::factory()->create();
        $this->awayTeam = Team::factory()->create();

        $this->league = Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
        ]);

        $this->game = Game::factory()->create([
            'user_id' => $this->user->id,
            'team_id' => $this->homeTeam->id,
            'competition_id' => $this->league->id,
            'season' => '2024',
            'current_date' => '2024-09-01',
        ]);
    }

    public function test_processall_persists_ratings_for_matches_with_performances(): void
    {
        $homePlayers = $this->createSquad($this->homeTeam);
        $awayPlayers = $this->createSquad($this->awayTeam);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->league->id,
            'round_number' => 1,
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'scheduled_date' => Carbon::parse('2024-09-01'),
        ]);

        // Performances for the home + away starting XIs (uniform 1.0 so we can
        // assert specific computed ratings).
        $performances = [];
        foreach ($homePlayers->merge($awayPlayers) as $p) {
            $performances[$p->id] = 1.0;
        }

        $matchResult = [
            'matchId' => $match->id,
            'competitionId' => $this->league->id,
            'homeTeamId' => $this->homeTeam->id,
            'awayTeamId' => $this->awayTeam->id,
            'homeScore' => 1,
            'awayScore' => 0,
            'homePossession' => 55,
            'awayPossession' => 45,
            'performances' => $performances,
            'events' => [],
        ];

        $allPlayers = collect([
            $this->homeTeam->id => $homePlayers,
            $this->awayTeam->id => $awayPlayers,
        ]);

        app(MatchResultProcessor::class)->processAll(
            $this->game,
            '2024-09-01',
            [$matchResult],
            allPlayers: $allPlayers,
        );

        $rows = GamePlayerMatchRating::where('game_match_id', $match->id)->get();

        $this->assertCount(
            $homePlayers->count() + $awayPlayers->count(),
            $rows,
            'Every player with a performance modifier should have a persisted rating',
        );

        // Every row should carry the modifier we fed in and a rating in [1, 10].
        foreach ($rows as $row) {
            $this->assertEquals(1.0, (float) $row->performance_modifier);
            $this->assertGreaterThanOrEqual(1.0, (float) $row->rating);
            $this->assertLessThanOrEqual(10.0, (float) $row->rating);
        }

        // Home midfielder on a 1-0 winning team with perf=1.0, no events:
        // (1.0-0.7)/0.6 + 0.08 (winner) = 0.58 → 7.32 → 7.3
        $homeMid = $homePlayers->firstWhere('position', 'Central Midfield');
        $this->assertEquals(
            7.3,
            (float) $rows->firstWhere('game_player_id', $homeMid->id)->rating,
        );

        // Away midfielder on a 0-1 losing team with perf=1.0, no events:
        // (1.0-0.7)/0.6 - min(1*0.04, 0.20) = 0.46 → 6.84 → 6.8
        $awayMid = $awayPlayers->firstWhere('position', 'Central Midfield');
        $this->assertEquals(
            6.8,
            (float) $rows->firstWhere('game_player_id', $awayMid->id)->rating,
        );
    }

    public function test_processall_skips_matches_without_performances(): void
    {
        // AI-fast-path matches (resolved by AIMatchResolver) omit `performances`.
        // We must not insert any rating rows for them in v1.
        $homePlayers = $this->createSquad($this->homeTeam);
        $awayPlayers = $this->createSquad($this->awayTeam);

        $match = GameMatch::factory()->create([
            'game_id' => $this->game->id,
            'competition_id' => $this->league->id,
            'round_number' => 1,
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'scheduled_date' => Carbon::parse('2024-09-01'),
        ]);

        $matchResult = [
            'matchId' => $match->id,
            'competitionId' => $this->league->id,
            'homeTeamId' => $this->homeTeam->id,
            'awayTeamId' => $this->awayTeam->id,
            'homeScore' => 2,
            'awayScore' => 1,
            'homePossession' => 60,
            'awayPossession' => 40,
            'events' => [],
            // 'performances' key intentionally omitted (AI-fast-path shape)
        ];

        app(MatchResultProcessor::class)->processAll(
            $this->game,
            '2024-09-01',
            [$matchResult],
            allPlayers: collect([
                $this->homeTeam->id => $homePlayers,
                $this->awayTeam->id => $awayPlayers,
            ]),
        );

        $this->assertSame(
            0,
            GamePlayerMatchRating::where('game_match_id', $match->id)->count(),
            'AI-fast-path matches without performances should not insert rating rows',
        );
    }

    public function test_calculator_produces_self_consistent_rows_for_resimulation_helper(): void
    {
        // Lightweight regression: the calculator wired into MatchResimulationService
        // must accept the same matchResult shape with a Collection of MatchEvent
        // models (rather than the array shape used in processAll).
        $homePlayers = $this->createSquad($this->homeTeam);
        $awayPlayers = $this->createSquad($this->awayTeam);

        $allPerformances = [];
        foreach ($homePlayers->merge($awayPlayers) as $p) {
            $allPerformances[$p->id] = 1.0;
        }

        $homeScorer = $homePlayers->firstWhere('position', 'Centre-Forward');

        $eventCollection = new Collection([
            (object) [
                'event_type' => 'goal',
                'game_player_id' => $homeScorer->id,
                'team_id' => $this->homeTeam->id,
                'minute' => 30,
                'metadata' => null,
            ],
        ]);

        $ratings = app(MatchRatingCalculator::class)->calculate(
            [
                'performances' => $allPerformances,
                'homeTeamId' => $this->homeTeam->id,
                'awayTeamId' => $this->awayTeam->id,
                'homeScore' => 1,
                'awayScore' => 0,
                'events' => $eventCollection,
            ],
            $homePlayers,
            $awayPlayers,
        );

        // Forward goal: 0.5 + 0.30 + 0.08 = 0.88 → 8.52 → 8.5
        $this->assertEquals(8.5, $ratings[$homeScorer->id]['rating']);
    }

    /**
     * @return Collection<int, GamePlayer>
     */
    private function createSquad(Team $team): Collection
    {
        $players = collect();

        $players->push(
            GamePlayer::factory()
                ->forGame($this->game)
                ->forTeam($team)
                ->goalkeeper()
                ->create()
        );

        foreach (['Centre-Back', 'Centre-Back', 'Left-Back', 'Right-Back'] as $position) {
            $players->push(
                GamePlayer::factory()
                    ->forGame($this->game)
                    ->forTeam($team)
                    ->create(['position' => $position])
            );
        }

        for ($i = 0; $i < 4; $i++) {
            $players->push(
                GamePlayer::factory()
                    ->forGame($this->game)
                    ->forTeam($team)
                    ->create(['position' => 'Central Midfield'])
            );
        }

        foreach (['Centre-Forward', 'Centre-Forward'] as $position) {
            $players->push(
                GamePlayer::factory()
                    ->forGame($this->game)
                    ->forTeam($team)
                    ->create(['position' => $position])
            );
        }

        return $players;
    }
}
