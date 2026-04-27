<?php

namespace Tests\Unit;

use App\Models\ClubProfile;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GameTransfer;
use App\Models\Team;
use App\Models\TeamReputation;
use App\Models\TransferListing;
use App\Modules\Transfer\Services\TransferMarketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferMarketServiceRefreshListingsTest extends TestCase
{
    use RefreshDatabase;

    private TransferMarketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TransferMarketService::class);
    }

    private function makeGame(): Game
    {
        $userTeam = Team::factory()->create();

        return Game::factory()->create([
            'team_id' => $userTeam->id,
            'season' => '2025',
            'current_date' => '2024-08-15',
        ]);
    }

    private function makeAITeam(Game $game, string $reputationLevel): Team
    {
        $team = Team::factory()->create();
        TeamReputation::create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'reputation_level' => $reputationLevel,
            'base_reputation_level' => $reputationLevel,
            'reputation_points' => TeamReputation::pointsForTier($reputationLevel),
        ]);
        // sampleAITeams() now draws from competition_entries (country-agnostic)
        // so every test AI team needs an entry to be visible to the sampler.
        CompetitionEntry::create([
            'game_id' => $game->id,
            'competition_id' => $game->competition_id,
            'team_id' => $team->id,
        ]);

        return $team;
    }

    /**
     * Build a 25-player roster with surplus in every position group:
     * 4 GK (floor 3), 8 DEF (floor 6), 8 MID (floor 6), 5 FWD (floor 4).
     *
     * @param  array<string, mixed>  $overrides applied to every player
     */
    private function fillRoster(Game $game, Team $team, array $overrides = []): void
    {
        $positions = array_merge(
            array_fill(0, 4, 'Goalkeeper'),
            array_fill(0, 8, 'Centre-Back'),
            array_fill(0, 8, 'Central Midfield'),
            array_fill(0, 5, 'Centre-Forward'),
        );

        foreach ($positions as $position) {
            GamePlayer::factory()
                ->forGame($game)
                ->forTeam($team)
                ->create(array_merge([
                    'position' => $position,
                    'contract_until' => '2027-06-30', // ~3 years from current_date
                ], $overrides));
        }
    }

    public function test_soft_fill_skip_when_at_threshold(): void
    {
        $game = $this->makeGame();
        // Two AI teams to ensure ≥ 30 game_players exist (one 25-roster
        // team isn't enough seed material for the soft-fill threshold).
        $teamA = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        $teamB = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        $this->fillRoster($game, $teamA, ['contract_until' => '2025-06-30']);
        $this->fillRoster($game, $teamB, ['contract_until' => '2025-06-30']);

        // Pre-fill exactly 30 listings (== SOFT_FILL_THRESHOLD)
        $players = GamePlayer::where('game_id', $game->id)->take(30)->get();
        $this->assertCount(30, $players, 'fixture must have at least 30 players');
        foreach ($players as $p) {
            TransferListing::create([
                'game_id' => $game->id,
                'game_player_id' => $p->id,
                'team_id' => $p->team_id,
                'status' => TransferListing::STATUS_LISTED,
                'listed_at' => '2024-08-10', // within 30-day expiry window
                'asking_price' => 1_000_000_00,
            ]);
        }

        $this->service->refreshListings($game);

        $this->assertSame(30, TransferListing::where('game_id', $game->id)->count());
    }

    public function test_expires_old_listings(): void
    {
        $game = $this->makeGame();
        $team = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        $this->fillRoster($game, $team);

        $oldPlayer = GamePlayer::where('game_id', $game->id)->first();
        $stale = TransferListing::create([
            'game_id' => $game->id,
            'game_player_id' => $oldPlayer->id,
            'team_id' => $oldPlayer->team_id,
            'status' => TransferListing::STATUS_LISTED,
            'listed_at' => '2024-07-01', // > 30 days before current_date 2024-08-15
            'asking_price' => 1_000_000_00,
        ]);

        $this->service->refreshListings($game);

        $this->assertNull(TransferListing::find($stale->id));
    }

    public function test_does_not_list_players_from_user_team(): void
    {
        $game = $this->makeGame();
        $userTeam = Team::find($game->team_id);
        $this->fillRoster($game, $userTeam, ['contract_until' => '2025-06-30']);

        $aiTeam = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        $this->fillRoster($game, $aiTeam, ['contract_until' => '2025-06-30']);

        $this->service->refreshListings($game);

        $userListings = TransferListing::where('game_id', $game->id)
            ->where('team_id', $userTeam->id)
            ->count();

        $this->assertSame(0, $userListings);
    }

    public function test_lists_at_most_one_player_per_team(): void
    {
        $game = $this->makeGame();
        $team = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        // Tier-1 (under €1M) players + short contracts → every player is a strong listing candidate
        $this->fillRoster($game, $team, [
            'market_value_cents' => 500_000_00,
            'tier' => 1,
            'contract_until' => '2025-06-30',
        ]);

        $this->service->refreshListings($game);

        $teamListings = TransferListing::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->count();

        // MAX_PICKS_PER_TEAM = 1 — every listing comes from a different club.
        $this->assertSame(1, $teamListings);
    }

    public function test_protects_starter_tier_with_long_contract(): void
    {
        $game = $this->makeGame();
        $team = $this->makeAITeam($game, ClubProfile::REPUTATION_ELITE); // index 4
        // Tier 5 (index 4) + 4-year contract → tier_gap=0, yearsLeft>1 → protected.
        $this->fillRoster($game, $team, [
            'market_value_cents' => 60_000_000_00,
            'tier' => 5,
            'contract_until' => '2028-06-30', // ~4 years from current_date
        ]);

        $this->service->refreshListings($game);

        $teamListings = TransferListing::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->count();

        $this->assertSame(0, $teamListings);
    }

    public function test_starter_tier_listable_when_contract_runs_out(): void
    {
        $game = $this->makeGame();
        $team = $this->makeAITeam($game, ClubProfile::REPUTATION_ELITE);
        // Same tier-5 / elite-team setup, but contract ≤ 1 year → leverage gone, listable.
        $this->fillRoster($game, $team, [
            'market_value_cents' => 60_000_000_00,
            'tier' => 5,
            'contract_until' => '2025-06-30',
        ]);

        $this->service->refreshListings($game);

        $teamListings = TransferListing::where('game_id', $game->id)
            ->where('team_id', $team->id)
            ->count();

        $this->assertGreaterThan(0, $teamListings);
    }

    public function test_excludes_already_listed_players(): void
    {
        $game = $this->makeGame();
        $team = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        $this->fillRoster($game, $team, [
            'tier' => 1,
            'contract_until' => '2025-06-30',
        ]);

        $alreadyListed = GamePlayer::where('game_id', $game->id)->first();
        TransferListing::create([
            'game_id' => $game->id,
            'game_player_id' => $alreadyListed->id,
            'team_id' => $alreadyListed->team_id,
            'status' => TransferListing::STATUS_LISTED,
            'listed_at' => '2024-08-10',
            'asking_price' => 500_000_00,
        ]);

        $this->service->refreshListings($game);

        $rowsForPlayer = TransferListing::where('game_player_id', $alreadyListed->id)->count();
        $this->assertSame(1, $rowsForPlayer);
    }

    public function test_excludes_already_transferred_players(): void
    {
        $game = $this->makeGame();
        $team = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        $this->fillRoster($game, $team, [
            'tier' => 1,
            'contract_until' => '2025-06-30',
        ]);

        $transferred = GamePlayer::where('game_id', $game->id)->first();
        GameTransfer::create([
            'game_id' => $game->id,
            'game_player_id' => $transferred->id,
            'from_team_id' => $transferred->team_id,
            'to_team_id' => $game->team_id,
            'transfer_fee' => 1_000_000_00,
            'type' => GameTransfer::TYPE_TRANSFER,
            'season' => $game->season,
            'window' => 'summer',
        ]);

        $this->service->refreshListings($game);

        $rowsForPlayer = TransferListing::where('game_player_id', $transferred->id)->count();
        $this->assertSame(0, $rowsForPlayer);
    }

    public function test_asking_price_within_envelope(): void
    {
        $game = $this->makeGame();
        $team = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        $this->fillRoster($game, $team, [
            'market_value_cents' => 5_000_000_00,
            'tier' => 1,
            'contract_until' => '2025-06-30',
        ]);

        $this->service->refreshListings($game);

        $listings = TransferListing::where('game_id', $game->id)->get();
        $this->assertGreaterThan(0, $listings->count());

        foreach ($listings as $listing) {
            // Multiplier ∈ [0.8, 1.25]; allow Money::roundPrice slack
            // (rounds to nearest €100K at this magnitude → ~1% of value).
            $this->assertGreaterThanOrEqual(
                (int) (5_000_000_00 * 0.78),
                $listing->asking_price,
                "Listing price {$listing->asking_price} below envelope floor",
            );
            $this->assertLessThanOrEqual(
                (int) (5_000_000_00 * 1.27),
                $listing->asking_price,
                "Listing price {$listing->asking_price} above envelope ceiling",
            );
        }
    }

    public function test_skips_teams_below_min_squad_size(): void
    {
        $game = $this->makeGame();
        $team = $this->makeAITeam($game, ClubProfile::REPUTATION_MODEST);
        // Only 10 players — below MIN_SQUAD_SIZE (20). Even with tempting tiers/contracts.
        for ($i = 0; $i < 10; $i++) {
            GamePlayer::factory()
                ->forGame($game)
                ->forTeam($team)
                ->create([
                    'position' => 'Central Midfield',
                    'tier' => 1,
                    'market_value_cents' => 500_000_00,
                    'contract_until' => '2025-06-30',
                ]);
        }

        $this->service->refreshListings($game);

        $count = TransferListing::where('game_id', $game->id)->count();
        $this->assertSame(0, $count);
    }
}
