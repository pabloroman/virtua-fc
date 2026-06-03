<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Display layer: list surfaces show a player's release clause in place of his
 * market value when (and only when) the feature is enabled, the player carries a
 * clause, and his OWNING club is in a mandatory-clause country (config-driven,
 * currently only ES). The decision + value live on the GamePlayer model
 * (displaysReleaseClauseAsMarketReference / marketReferenceValue / ownerCountry),
 * which every surface routes through.
 */
class ReleaseClauseDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const MV = 5_000_000_000;        // €50M
    private const CLAUSE = 6_250_000_000;    // €62.5M

    protected function setUp(): void
    {
        parent::setUp();

        // Spain is the only mandatory-clause country by default; pin it so the
        // test is independent of future config tuning.
        config()->set('finances.release_clause.mandatory_countries', ['ES']);
        Competition::factory()->league()->create(['id' => 'ESP1']);
    }

    public function test_es_owned_contracted_player_shows_the_clause_as_market_reference(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        $player = $this->clausePlayer($game, $team);

        $this->assertTrue($player->displaysReleaseClauseAsMarketReference($game));
        $this->assertSame($player->formatted_release_clause, $player->marketReferenceValue($game));
    }

    public function test_flag_off_keeps_the_market_value_for_existing_saves(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: false);
        $player = $this->clausePlayer($game, $team);

        $this->assertFalse($player->displaysReleaseClauseAsMarketReference($game));
        $this->assertSame($player->formatted_market_value, $player->marketReferenceValue($game));
    }

    public function test_non_mandatory_country_keeps_market_value_even_with_a_clause(): void
    {
        // A clause is forced onto an EN-owned player: the gate is the
        // mandatory-country list, NOT merely "has a clause", so he still shows
        // his market value.
        [$game, $team] = $this->makeGame(country: 'EN', enabled: true);
        $player = $this->clausePlayer($game, $team);

        $this->assertFalse($player->displaysReleaseClauseAsMarketReference($game));
        $this->assertSame($player->formatted_market_value, $player->marketReferenceValue($game));
    }

    public function test_free_agent_keeps_market_value(): void
    {
        [$game] = $this->makeGame(country: 'ES', enabled: true);
        $freeAgent = GamePlayer::factory()->forGame($game)->create([
            'team_id' => null,
            'market_value_cents' => self::MV,
            'release_clause' => null,
        ]);

        $this->assertNull($freeAgent->ownerCountry());
        $this->assertFalse($freeAgent->displaysReleaseClauseAsMarketReference($game));
        $this->assertSame($freeAgent->formatted_market_value, $freeAgent->marketReferenceValue($game));
    }

    public function test_loaned_in_player_resolves_to_the_owning_clubs_country(): void
    {
        // Owner = Spanish club, currently on loan AT an English club. The owner
        // governs, so the clause is the market reference.
        [$game] = $this->makeGame(country: 'EN', enabled: true);
        $owner = Team::factory()->create(['country' => 'ES']);
        $host = Team::factory()->create(['country' => 'EN']);
        $player = $this->clausePlayer($game, $host); // team_id = current location (host)
        $this->activeLoan($game, $player, parent: $owner, host: $host);

        $this->assertSame('ES', $player->ownerCountry());
        $this->assertTrue($player->displaysReleaseClauseAsMarketReference($game));
    }

    public function test_loaned_in_from_non_mandatory_owner_keeps_market_value(): void
    {
        // Inverse: the player currently sits at a Spanish club but is OWNED by an
        // English club, so he shows his market value despite playing in Spain.
        [$game] = $this->makeGame(country: 'ES', enabled: true);
        $owner = Team::factory()->create(['country' => 'EN']);
        $host = Team::factory()->create(['country' => 'ES']);
        $player = $this->clausePlayer($game, $host);
        $this->activeLoan($game, $player, parent: $owner, host: $host);

        $this->assertSame('EN', $player->ownerCountry());
        $this->assertFalse($player->displaysReleaseClauseAsMarketReference($game));
    }

    public function test_owner_country_is_not_queried_per_row_when_relations_are_eager_loaded(): void
    {
        [$game, $team] = $this->makeGame(country: 'ES', enabled: true);
        foreach (range(1, 5) as $i) {
            $this->clausePlayer($game, $team);
        }

        // Mirror the eager-loading every list surface now applies.
        $players = GamePlayer::with(['team', 'activeLoan.parentTeam'])
            ->where('game_id', $game->id)
            ->get();

        DB::enableQueryLog();
        foreach ($players as $player) {
            $player->marketReferenceValue($game);
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertEmpty($queries, 'ownerCountry() must read eager-loaded relations, not query per row');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * @return array{0: Game, 1: Team}
     */
    private function makeGame(string $country, bool $enabled): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['country' => $country]);

        $game = Game::factory()->forTeam($team)->create([
            'user_id' => $user->id,
            'competition_id' => 'ESP1',
            'country' => $country,
            'season' => '2024',
            'current_date' => '2025-02-15',
            'release_clauses_enabled' => $enabled,
        ]);

        return [$game, $team];
    }

    private function clausePlayer(Game $game, Team $team): GamePlayer
    {
        return GamePlayer::factory()->forGame($game)->forTeam($team)->create([
            'market_value_cents' => self::MV,
            'release_clause' => self::CLAUSE,
            'contract_until' => '2026-06-30',
        ]);
    }

    private function activeLoan(Game $game, GamePlayer $player, Team $parent, Team $host): Loan
    {
        return Loan::create([
            'game_id' => $game->id,
            'game_player_id' => $player->id,
            'parent_team_id' => $parent->id,
            'loan_team_id' => $host->id,
            'started_at' => '2025-01-01',
            'return_at' => '2025-06-30',
            'status' => Loan::STATUS_ACTIVE,
        ]);
    }
}
