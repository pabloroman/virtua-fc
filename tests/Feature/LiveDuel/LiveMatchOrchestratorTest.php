<?php

namespace Tests\Feature\LiveDuel;

use App\Models\GamePlayerTemplate;
use App\Models\LiveMatchSession;
use App\Models\Team;
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
        [$host] = $this->seedUsersWithEligiblePlayers(['Spain']);

        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'Spain', 'Spain');

        $this->assertSame(LiveMatchPhase::Lobby, $session->phase);
        $this->assertSame($host->id, $session->host_user_id);
        $this->assertSame('Spain', $session->host_iso_code);
        $this->assertNull($session->guest_user_id);
        $this->assertNotNull($session->host_squad);
        $this->assertCount(11, $session->host_squad['starting_xi']);
        $this->assertGreaterThanOrEqual(7, count($session->host_squad['bench']));
    }

    public function test_first_non_host_visitor_claims_guest_slot(): void
    {
        [$host, $guest] = $this->seedUsersWithEligiblePlayers(['Spain', 'Brazil']);
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'Spain', 'Spain');

        $orchestrator->claimGuestSlot($session, $guest);

        $this->assertSame($guest->id, $session->fresh()->guest_user_id);
    }

    public function test_host_cannot_claim_guest_slot(): void
    {
        [$host] = $this->seedUsersWithEligiblePlayers(['Spain']);
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'Spain', 'Spain');

        $this->expectException(LiveMatchStateException::class);
        $orchestrator->claimGuestSlot($session, $host);
    }

    public function test_third_visitor_cannot_take_guest_slot(): void
    {
        [$host, $guest, $third] = $this->seedUsersWithEligiblePlayers(['Spain', 'Brazil', 'Argentina']);
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'Spain', 'Spain');
        $orchestrator->claimGuestSlot($session, $guest);

        $this->expectException(LiveMatchStateException::class);
        $orchestrator->claimGuestSlot($session->fresh(), $third);
    }

    public function test_guest_picking_team_with_both_users_present_starts_the_match(): void
    {
        Queue::fake();
        [$host, $guest] = $this->seedUsersWithEligiblePlayers(['Spain', 'Brazil']);
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, 'Spain', 'Spain');
        $orchestrator->claimGuestSlot($session, $guest);

        $session = $orchestrator->pickGuestTeam($session->fresh(), $guest, 'Brazil', 'Brazil');

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
     * Seed N users plus a shared pool of GamePlayerTemplate rows for every
     * requested nationality (23 each). Templates live on the control plane
     * and are shared across users — matches the production prototype's
     * data source.
     *
     * @param  array<int, string>  $nationalities  one per user; also used
     *                                             as the template pool to seed
     * @return array<int, User>
     */
    private function seedUsersWithEligiblePlayers(array $nationalities): array
    {
        $team = Team::factory()->create();
        $positions = ['Goalkeeper', 'Centre-Back', 'Left-Back', 'Right-Back', 'Central Midfield', 'Attacking Midfield', 'Left Winger', 'Right Winger', 'Centre-Forward'];

        foreach (array_unique($nationalities) as $nationality) {
            for ($i = 0; $i < 23; $i++) {
                GamePlayerTemplate::create([
                    'season' => '2025/2026',
                    'player_id' => (string) \Illuminate\Support\Str::uuid(),
                    'transfermarkt_id' => 'tm-'.\Illuminate\Support\Str::random(8),
                    'name' => "{$nationality} Player {$i}",
                    'date_of_birth' => '1995-01-01',
                    'nationality' => [$nationality],
                    'foot' => 'right',
                    'team_id' => $team->id,
                    'number' => $i + 1,
                    'position' => $positions[$i % count($positions)],
                    'overall_score' => 70 + ($i % 15),
                    'durability' => 70,
                    'tier' => 3,
                    'potential' => 80,
                    'potential_low' => 75,
                    'potential_high' => 85,
                ]);
            }
        }

        $users = [];
        foreach ($nationalities as $_) {
            $users[] = User::factory()->create();
        }

        return $users;
    }
}
