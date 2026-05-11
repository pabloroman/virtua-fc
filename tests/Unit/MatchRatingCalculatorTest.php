<?php

namespace Tests\Unit;

use App\Modules\Match\Services\MatchRatingCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MatchRatingCalculatorTest extends TestCase
{
    private MatchRatingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new MatchRatingCalculator();
    }

    private function player(string $id, string $group): object
    {
        return (object) ['id' => $id, 'position_group' => $group];
    }

    private function event(string $type, string $playerId, string $teamId, int $minute, ?array $metadata = null): array
    {
        return [
            'event_type' => $type,
            'game_player_id' => $playerId,
            'team_id' => $teamId,
            'minute' => $minute,
            'metadata' => $metadata,
        ];
    }

    public function test_neutral_midfielder_at_baseline_performance_scores_in_the_middle(): void
    {
        $homeTeamId = 'home-id';
        $awayTeamId = 'away-id';

        $matchResult = [
            'homeTeamId' => $homeTeamId,
            'awayTeamId' => $awayTeamId,
            'homeScore' => 0,
            'awayScore' => 0,
            'performances' => ['mid-1' => 1.0],
            'events' => [],
        ];

        $home = new Collection([$this->player('mid-1', 'Midfielder')]);
        $away = new Collection();

        $ratings = $this->calculator->calculate($matchResult, $home, $away);

        // score = (1.0 - 0.70) / 0.60 = 0.5; no bonuses (0-0 draw, no events).
        // rating = 0.5 * 4 + 5 = 7.0
        $this->assertEquals(7.0, $ratings['mid-1']['rating']);
        $this->assertEquals(1.0, $ratings['mid-1']['performance_modifier']);
    }

    public function test_forward_scoring_a_goal_gets_a_significant_boost(): void
    {
        $homeTeamId = 'home-id';
        $awayTeamId = 'away-id';

        $matchResult = [
            'homeTeamId' => $homeTeamId,
            'awayTeamId' => $awayTeamId,
            'homeScore' => 1,
            'awayScore' => 0,
            'performances' => ['fwd-1' => 1.0],
            'events' => [$this->event('goal', 'fwd-1', $homeTeamId, 50)],
        ];

        $home = new Collection([$this->player('fwd-1', 'Forward')]);
        $away = new Collection();

        $ratings = $this->calculator->calculate($matchResult, $home, $away);

        // score = 0.5 + 0.30 (forward goal) + 0.08 (winner) = 0.88
        // rating = 0.88 * 4 + 5 = 8.52 → 8.5
        $this->assertEquals(8.5, $ratings['fwd-1']['rating']);
    }

    public function test_goalkeeper_clean_sheet_in_a_win_outranks_a_neutral_midfielder(): void
    {
        $homeTeamId = 'home-id';
        $awayTeamId = 'away-id';

        $matchResult = [
            'homeTeamId' => $homeTeamId,
            'awayTeamId' => $awayTeamId,
            'homeScore' => 1,
            'awayScore' => 0,
            'performances' => ['gk-1' => 1.0, 'mid-1' => 1.0],
            'events' => [],
        ];

        $home = new Collection([
            $this->player('gk-1', 'Goalkeeper'),
            $this->player('mid-1', 'Midfielder'),
        ]);
        $away = new Collection();

        $ratings = $this->calculator->calculate($matchResult, $home, $away);

        // GK: 0.5 + 0.20 (clean sheet) + 0.08 (winner) = 0.78 → 8.12 → 8.1
        // MID: 0.5 + 0.08 (winner) = 0.58 → 7.32 → 7.3
        $this->assertEquals(8.1, $ratings['gk-1']['rating']);
        $this->assertEquals(7.3, $ratings['mid-1']['rating']);
        $this->assertGreaterThan($ratings['mid-1']['rating'], $ratings['gk-1']['rating']);
    }

    public function test_red_card_on_a_losing_team_drives_rating_down_to_floor(): void
    {
        $homeTeamId = 'home-id';
        $awayTeamId = 'away-id';

        $matchResult = [
            'homeTeamId' => $homeTeamId,
            'awayTeamId' => $awayTeamId,
            'homeScore' => 0,
            'awayScore' => 5,
            'performances' => ['def-1' => 0.70],
            'events' => [$this->event('red_card', 'def-1', $homeTeamId, 30)],
        ];

        $home = new Collection([$this->player('def-1', 'Defender')]);
        $away = new Collection();

        $ratings = $this->calculator->calculate($matchResult, $home, $away);

        // (0.70 - 0.70)/0.60 = 0; -0.30 red; -min(5*0.04, 0.20) = -0.20 → score = -0.50
        // -0.50 * 4 + 5 = 3.0 (above the floor)
        $this->assertEquals(3.0, $ratings['def-1']['rating']);
    }

    public function test_rating_is_clamped_to_one_and_ten(): void
    {
        $homeTeamId = 'home-id';
        $awayTeamId = 'away-id';

        $matchResult = [
            'homeTeamId' => $homeTeamId,
            'awayTeamId' => $awayTeamId,
            'homeScore' => 5,
            'awayScore' => 0,
            'performances' => ['gk-superb' => 1.30, 'gk-awful' => 0.70],
            'events' => [
                $this->event('goal', 'gk-superb', $homeTeamId, 10),
                $this->event('goal', 'gk-superb', $homeTeamId, 20),
                $this->event('goal', 'gk-superb', $homeTeamId, 30),
            ],
        ];

        $home = new Collection([$this->player('gk-superb', 'Goalkeeper')]);
        $away = new Collection([$this->player('gk-awful', 'Goalkeeper')]);

        $ratings = $this->calculator->calculate($matchResult, $home, $away);

        $this->assertLessThanOrEqual(10.0, $ratings['gk-superb']['rating']);
        $this->assertGreaterThanOrEqual(1.0, $ratings['gk-awful']['rating']);
        $this->assertEquals(10.0, $ratings['gk-superb']['rating']);
    }

    public function test_players_without_a_performance_are_omitted(): void
    {
        $matchResult = [
            'homeTeamId' => 'home-id',
            'awayTeamId' => 'away-id',
            'homeScore' => 0,
            'awayScore' => 0,
            'performances' => ['played' => 1.0],
            'events' => [],
        ];

        $home = new Collection([
            $this->player('played', 'Midfielder'),
            $this->player('benched', 'Midfielder'),
        ]);

        $ratings = $this->calculator->calculate($matchResult, $home, new Collection());

        $this->assertArrayHasKey('played', $ratings);
        $this->assertArrayNotHasKey('benched', $ratings);
    }

    public function test_empty_performances_returns_empty_array(): void
    {
        $matchResult = [
            'homeTeamId' => 'home-id',
            'awayTeamId' => 'away-id',
            'homeScore' => 0,
            'awayScore' => 0,
            'performances' => [],
            'events' => [],
        ];

        $this->assertSame([], $this->calculator->calculate($matchResult, new Collection(), new Collection()));
    }

    public function test_orphaned_player_with_no_team_is_skipped(): void
    {
        // A performance entry for a player not on either roster — defensive
        // skip so we don't score them against unknown team context (winner /
        // loser / clean sheet flags would all be wrong).
        $matchResult = [
            'homeTeamId' => 'home-id',
            'awayTeamId' => 'away-id',
            'homeScore' => 0,
            'awayScore' => 0,
            'performances' => ['ghost' => 1.0],
            'events' => [],
        ];

        $ratings = $this->calculator->calculate($matchResult, new Collection(), new Collection());

        $this->assertArrayNotHasKey('ghost', $ratings);
    }
}
