<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Competition\Services\CalendarService;
use App\Modules\Competition\Services\CupDrawService;
use App\Modules\Season\Services\SeasonInitializationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A game reads its schedules from the season it was created with, not from
 * whatever season the shared reference data currently points at.
 *
 * Each test here puts the database in the state a release leaves behind — the
 * competition rows moved on to 2026 while a career is still playing 2025 — and
 * asserts the career still lands on its own 2025 dates.
 */
class GameBaseSeasonTest extends TestCase
{
    use RefreshDatabase;

    /** Round 1 of the Copa del Rey in data/2025/ESPCUP/schedule.json. */
    private const CUP_ROUND_1_DATE = '2025-10-29';

    /** Matchday 1 of La Liga in data/2025/ESP1/schedule.json. */
    private const LEAGUE_ROUND_1_DATE = '2025-08-17';

    public function test_league_fixtures_use_the_games_base_season_not_the_competition_row(): void
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'tier' => 1,
            // Reference data has been refreshed to the new season.
            'season' => '2026',
        ]);

        $game = Game::factory()->inCompetition('ESP1')->create([
            'season' => '2025',
            'base_season' => '2025',
        ]);

        foreach (Team::factory()->count(4)->create() as $team) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        app(SeasonInitializationService::class)
            ->generateLeagueFixtures($game->id, 'ESP1', '2025');

        $firstMatchday = GameMatch::where('game_id', $game->id)
            ->where('competition_id', 'ESP1')
            ->orderBy('scheduled_date')
            ->first();

        $this->assertNotNull($firstMatchday, 'No fixtures were generated');
        $this->assertSame(
            self::LEAGUE_ROUND_1_DATE,
            $firstMatchday->scheduled_date->toDateString(),
        );
    }

    public function test_league_fixtures_still_shift_by_year_as_a_career_progresses(): void
    {
        Competition::factory()->league()->create([
            'id' => 'ESP1',
            'name' => 'LaLiga',
            'tier' => 1,
            'season' => '2026',
        ]);

        // A career two seasons past the data it was seeded from.
        $game = Game::factory()->inCompetition('ESP1')->create([
            'season' => '2027',
            'base_season' => '2025',
        ]);

        foreach (Team::factory()->count(4)->create() as $team) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => 'ESP1',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        app(SeasonInitializationService::class)
            ->generateLeagueFixtures($game->id, 'ESP1', '2027');

        $firstMatchday = GameMatch::where('game_id', $game->id)
            ->where('competition_id', 'ESP1')
            ->orderBy('scheduled_date')
            ->first();

        $this->assertNotNull($firstMatchday);
        $this->assertSame('2027', $firstMatchday->scheduled_date->format('Y'));
    }

    public function test_cup_draw_schedules_ties_in_the_games_own_season(): void
    {
        Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
            'season' => '2026',
        ]);

        $game = Game::factory()->create([
            'season' => '2025',
            'base_season' => '2025',
        ]);

        foreach (Team::factory()->count(4)->create() as $team) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => 'ESPCUP',
                'team_id' => $team->id,
                'entry_round' => 1,
            ]);
        }

        $ties = app(CupDrawService::class)->conductDraw($game->id, 'ESPCUP', 1);

        $this->assertCount(2, $ties);

        $firstLeg = GameMatch::find($ties->first()->first_leg_match_id);
        $this->assertSame(
            self::CUP_ROUND_1_DATE,
            $firstLeg->scheduled_date->toDateString(),
        );
    }

    public function test_knockout_placeholders_read_the_games_base_season(): void
    {
        // The release has moved the configured season on; the folder for it may
        // not even be on disk yet. An in-flight career must not notice.
        config(['season.current' => '2026']);

        Competition::factory()->knockoutCup()->create([
            'id' => 'ESPCUP',
            'name' => 'Copa del Rey',
            'season' => '2026',
        ]);

        $game = Game::factory()->create([
            'season' => '2025',
            'base_season' => '2025',
        ]);

        $placeholders = app(CalendarService::class)->getKnockoutPlaceholders($game, 'ESPCUP');

        $this->assertNotEmpty($placeholders, 'Knockout placeholders disappeared for an in-flight career');
        $this->assertSame(
            self::CUP_ROUND_1_DATE,
            $placeholders->first()->scheduled_date->toDateString(),
        );
    }

    public function test_base_season_falls_back_to_the_configured_season_when_unset(): void
    {
        config(['season.current' => '2026']);

        $game = Game::factory()->make(['base_season' => null]);

        $this->assertSame('2026', $game->base_season);
    }
}
