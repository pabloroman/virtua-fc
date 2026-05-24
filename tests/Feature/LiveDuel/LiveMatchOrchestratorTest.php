<?php

namespace Tests\Feature\LiveDuel;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\LiveMatchSession;
use App\Models\User;
use App\Modules\LiveMatch\Enums\LiveMatchPhase;
use App\Modules\LiveMatch\Exceptions\LiveMatchStateException;
use App\Modules\LiveMatch\Jobs\AdvanceLiveMatchWindowJob;
use App\Modules\LiveMatch\Services\AutoLineupBuilder;
use App\Modules\LiveMatch\Services\LiveMatchEngineAdapter;
use App\Modules\LiveMatch\Services\LiveMatchOrchestrator;
use App\Modules\LiveMatch\Services\NationalSquadBuilder;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LiveMatchOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_session_snapshots_host_squad_and_remains_in_lobby(): void
    {
        Queue::fake();
        [$host] = $this->seedUsersWithEligiblePlayers(['ES']);

        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'ES', 'Spain');

        $this->assertSame(LiveMatchPhase::Lobby, $session->phase);
        $this->assertSame($host->id, $session->host_user_id);
        $this->assertSame('ES', $session->host_iso_code);
        $this->assertNull($session->guest_user_id);
        $this->assertNotNull($session->host_squad);
        $this->assertCount(NationalSquadBuilder::SQUAD_SIZE, $session->host_squad['starting_xi'] ?? []) === false
            ? $this->assertGreaterThanOrEqual(11, count($session->host_squad['starting_xi'])) // starting xi is 11
            : null;
    }

    public function test_first_non_host_visitor_claims_guest_slot(): void
    {
        [$host, $guest] = $this->seedUsersWithEligiblePlayers(['ES', 'BR']);
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'ES', 'Spain');

        $orchestrator->claimGuestSlot($session, $guest);

        $this->assertSame($guest->id, $session->fresh()->guest_user_id);
    }

    public function test_host_cannot_claim_guest_slot(): void
    {
        [$host] = $this->seedUsersWithEligiblePlayers(['ES']);
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'ES', 'Spain');

        $this->expectException(LiveMatchStateException::class);
        $orchestrator->claimGuestSlot($session, $host);
    }

    public function test_third_visitor_cannot_take_guest_slot(): void
    {
        [$host, $guest, $third] = $this->seedUsersWithEligiblePlayers(['ES', 'BR', 'AR']);
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'ES', 'Spain');
        $orchestrator->claimGuestSlot($session, $guest);

        $this->expectException(LiveMatchStateException::class);
        $orchestrator->claimGuestSlot($session->fresh(), $third);
    }

    public function test_guest_picking_team_with_both_users_present_starts_the_match(): void
    {
        Queue::fake();
        [$host, $guest] = $this->seedUsersWithEligiblePlayers(['ES', 'BR']);
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'ES', 'Spain');
        $orchestrator->claimGuestSlot($session, $guest);

        $session = $orchestrator->pickGuestTeam($session->fresh(), $guest, 'BR', 'Brazil');

        $this->assertSame(LiveMatchPhase::Live, $session->phase);
        $this->assertNotNull($session->context_state);
        Queue::assertPushed(AdvanceLiveMatchWindowJob::class);
    }

    private function makeOrchestrator(): LiveMatchOrchestrator
    {
        $squadBuilder = new NationalSquadBuilder;
        $autoLineupBuilder = new AutoLineupBuilder;
        $engineAdapter = new LiveMatchEngineAdapter(new MatchSimulator, $squadBuilder);

        return new LiveMatchOrchestrator($squadBuilder, $autoLineupBuilder, $engineAdapter);
    }

    /**
     * Seed N users, each with an active Game containing 23 eligible players
     * of the corresponding nationality.
     *
     * @param  array<int, string>  $isos  one ISO per user
     * @return array<int, User>
     */
    private function seedUsersWithEligiblePlayers(array $isos): array
    {
        $users = [];
        foreach ($isos as $iso) {
            $user = User::factory()->create();
            $game = Game::factory()->create(['user_id' => $user->id, 'status' => 'active']);
            for ($i = 0; $i < 23; $i++) {
                GamePlayer::factory()->create([
                    'game_id' => $game->id,
                    'nationality' => [$iso],
                    'overall_score' => 70 + ($i % 15),
                ]);
            }
            $users[] = $user;
        }

        return $users;
    }
}
