<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Match\Services\MatchNarrativeService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Guards the cross-line coherence of the pre-match news feed.
 *
 * selectCandidates() picks one line per category in priority order, and drops the
 * generic opponent preview once a European or rivalry line already frames the
 * fixture (the merged European line now names the opponent + venue itself).
 * europeanCandidates() builds that opponent/venue-aware line. Both are exercised
 * here as pure logic — no matchday simulation required.
 */
class MatchNarrativeCoherenceTest extends TestCase
{
    private function service(): MatchNarrativeService
    {
        return new MatchNarrativeService();
    }

    private function candidate(string $category, int $priority, string $key): array
    {
        return ['category' => $category, 'priority' => $priority, 'key' => $key, 'params' => []];
    }

    /** Run the pure selector and return just the chosen keys, in order. */
    private function select(array $candidates, int $limit): array
    {
        $service = $this->service();
        $method = new ReflectionMethod($service, 'selectCandidates');
        $method->setAccessible(true);

        return array_map(fn ($c) => $c['key'], $method->invoke($service, $candidates, $limit));
    }

    // ── Coherence: generic preview suppression ──────────────────────────

    public function test_european_line_suppresses_generic_preview(): void
    {
        $keys = $this->select([
            $this->candidate('european', 7, 'euro_group_home'),
            $this->candidate('scouting', 4, 'opponent_preview_home'),
        ], 4);

        $this->assertContains('euro_group_home', $keys);
        $this->assertNotContains('opponent_preview_home', $keys);
    }

    public function test_rivalry_line_suppresses_generic_preview(): void
    {
        $keys = $this->select([
            $this->candidate('rivalry', 8, 'rivalry_lost_reverse'),
            $this->candidate('scouting', 4, 'opponent_preview_away'),
        ], 4);

        $this->assertContains('rivalry_lost_reverse', $keys);
        $this->assertNotContains('opponent_preview_away', $keys);
    }

    public function test_analytical_scouting_line_is_not_suppressed(): void
    {
        // Only the generic preview is redundant; a real opponent read (position,
        // form) still adds information beside the European headline.
        $keys = $this->select([
            $this->candidate('european', 7, 'euro_group_home'),
            $this->candidate('scouting', 7, 'opponent_strong'),
        ], 4);

        $this->assertContains('euro_group_home', $keys);
        $this->assertContains('opponent_strong', $keys);
    }

    public function test_suppressed_preview_frees_slot_for_next_line(): void
    {
        // The preview is skipped without consuming a slot, so a lower-priority
        // coherent line takes its place rather than the feed losing a line.
        $keys = $this->select([
            $this->candidate('european', 7, 'euro_group_home'),
            $this->candidate('scouting', 4, 'opponent_preview_home'),
            $this->candidate('mood', 3, 'morale_high'),
        ], 2);

        $this->assertSame(['euro_group_home', 'morale_high'], $keys);
    }

    public function test_cup_line_does_not_suppress_preview(): void
    {
        // Domestic cup is intentionally out of FIXTURE_FRAMING_CATEGORIES: the cup
        // line carries round/second-leg state, so the opponent preview complements it.
        $keys = $this->select([
            $this->candidate('cup', 9, 'cup_semi'),
            $this->candidate('scouting', 4, 'opponent_preview_home'),
        ], 4);

        $this->assertContains('cup_semi', $keys);
        $this->assertContains('opponent_preview_home', $keys);
    }

    public function test_preview_is_kept_without_a_framing_line(): void
    {
        $keys = $this->select([
            $this->candidate('form', 8, 'streak_win'),
            $this->candidate('scouting', 4, 'opponent_preview_home'),
        ], 4);

        $this->assertContains('opponent_preview_home', $keys);
    }

    // ── European generator: opponent + venue-aware keys ─────────────────

    private function europeanCandidatesFor(GameMatch $match, Game $game): array
    {
        $service = $this->service();
        $method = new ReflectionMethod($service, 'europeanCandidates');
        $method->setAccessible(true);

        return $method->invoke($service, $match, $game);
    }

    private function euroMatch(string $homeId, string $awayId, ?string $roundName, ?string $cupTieId): GameMatch
    {
        $comp = new Competition();
        $comp->id = 'champions_league';
        $comp->name = 'Champions League';
        $comp->role = Competition::ROLE_EUROPEAN;

        $home = new Team();
        $home->name = 'Real Madrid';
        $home->type = 'club';

        $away = new Team();
        $away->name = 'Newcastle United';
        $away->type = 'club';

        $match = new GameMatch();
        $match->home_team_id = $homeId;
        $match->away_team_id = $awayId;
        $match->round_name = $roundName;
        $match->cup_tie_id = $cupTieId;
        $match->setRelation('competition', $comp);
        $match->setRelation('homeTeam', $home);
        $match->setRelation('awayTeam', $away);

        return $match;
    }

    private function game(string $teamId): Game
    {
        $game = new Game();
        $game->team_id = $teamId;

        return $game;
    }

    public function test_home_league_phase_uses_group_home_key_and_names_opponent(): void
    {
        $candidates = $this->europeanCandidatesFor(
            $this->euroMatch('USER', 'OPP', null, null),
            $this->game('USER'),
        );

        $this->assertCount(1, $candidates);
        $this->assertSame('euro_group_home', $candidates[0]['key']);
        $this->assertSame(7, $candidates[0]['priority']);
        $this->assertSame('Newcastle United', $candidates[0]['params']['opponent']);
        $this->assertSame('al Newcastle United', $candidates[0]['params']['opponent_a']);
    }

    public function test_away_league_phase_uses_group_away_key(): void
    {
        $candidates = $this->europeanCandidatesFor(
            $this->euroMatch('OPP', 'USER', null, null),
            $this->game('USER'),
        );

        $this->assertSame('euro_group_away', $candidates[0]['key']);
        $this->assertSame('Real Madrid', $candidates[0]['params']['opponent']);
    }

    public function test_home_knockout_uses_knockout_home_key(): void
    {
        $candidates = $this->europeanCandidatesFor(
            $this->euroMatch('USER', 'OPP', 'round_of_16', 'tie-1'),
            $this->game('USER'),
        );

        $this->assertSame('euro_knockout_home', $candidates[0]['key']);
        $this->assertSame(8, $candidates[0]['priority']);
    }

    public function test_final_uses_neutral_opponent_named_key(): void
    {
        // The final is single-leg at a neutral venue — no home/away split.
        $candidates = $this->europeanCandidatesFor(
            $this->euroMatch('USER', 'OPP', 'cup.final', 'tie-1'),
            $this->game('USER'),
        );

        $this->assertSame('euro_final', $candidates[0]['key']);
        $this->assertSame(10, $candidates[0]['priority']);
        $this->assertSame('Newcastle United', $candidates[0]['params']['opponent']);
    }

    // ── Translation-key parity ──────────────────────────────────────────

    public function test_es_en_narrative_key_parity(): void
    {
        $en = array_keys(trans('narrative', [], 'en'));
        $es = array_keys(trans('narrative', [], 'es'));
        sort($en);
        sort($es);

        $this->assertSame($en, $es, 'lang/en and lang/es narrative keys must stay in 1:1 parity');
    }
}
