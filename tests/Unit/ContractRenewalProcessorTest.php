<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Processors\ContractRenewalProcessor;
use App\Modules\Transfer\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractRenewalProcessorTest extends TestCase
{
    use RefreshDatabase;

    private ContractRenewalProcessor $processor;
    private Team $team;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = app(ContractRenewalProcessor::class);

        $this->team = Team::factory()->create();
        $this->game = Game::factory()->forTeam($this->team)->create();
    }

    public function test_applies_pending_wage_to_annual_wage_and_clears_pending(): void
    {
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'annual_wage' => 500_000_00,
                'pending_annual_wage' => 1_200_000_00,
            ]);

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        $this->assertSame(1_200_000_00, $player->annual_wage);
        $this->assertNull($player->pending_annual_wage);
    }

    public function test_leaves_players_without_pending_wage_untouched(): void
    {
        $player = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'annual_wage' => 500_000_00,
                'pending_annual_wage' => null,
            ]);

        $this->processor->process($this->game, $this->transitionData());

        $player->refresh();
        $this->assertSame(500_000_00, $player->annual_wage);
    }

    public function test_ignores_players_from_other_teams(): void
    {
        $otherTeam = Team::factory()->create();
        $otherPlayer = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($otherTeam)
            ->create([
                'annual_wage' => 500_000_00,
                'pending_annual_wage' => 1_200_000_00,
            ]);

        $this->processor->process($this->game, $this->transitionData());

        $otherPlayer->refresh();
        $this->assertSame(500_000_00, $otherPlayer->annual_wage);
        $this->assertSame(1_200_000_00, $otherPlayer->pending_annual_wage);
    }

    public function test_records_renewal_metadata_for_each_renewed_player(): void
    {
        $renewed = GamePlayer::factory()
            ->forGame($this->game)
            ->forTeam($this->team)
            ->create([
                'pending_annual_wage' => 750_000_00,
            ]);

        $data = $this->processor->process($this->game, $this->transitionData());

        $renewals = $data->getMetadata('contractRenewals');
        $this->assertIsArray($renewals);
        $this->assertCount(1, $renewals);
        $this->assertSame($renewed->id, $renewals[0]['playerId']);
    }

    private function transitionData(): SeasonTransitionData
    {
        return new SeasonTransitionData(
            oldSeason: '2025',
            newSeason: '2026',
            competitionId: $this->game->competition_id,
        );
    }
}
