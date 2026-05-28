<?php

namespace Tests\Feature\LiveDuel;

use App\Models\GamePlayerTemplate;
use App\Models\LiveMatchSession;
use App\Models\Team;
use App\Models\User;
use App\Modules\LiveMatch\Enums\LiveMatchPhase;
use App\Modules\LiveMatch\Enums\QueuedActionType;
use App\Modules\LiveMatch\Exceptions\InvalidLiveActionException;
use App\Modules\LiveMatch\Exceptions\LiveMatchStateException;
use App\Modules\LiveMatch\Jobs\AdvanceLiveMatchWindowJob;
use App\Modules\LiveMatch\Services\AutoLineupBuilder;
use App\Modules\LiveMatch\Services\LiveMatchActionQueue;
use App\Modules\LiveMatch\Services\LiveMatchEngineAdapter;
use App\Modules\LiveMatch\Services\LiveMatchOrchestrator;
use App\Modules\LiveMatch\Services\NationalSquadBuilder;
use App\Modules\Match\Services\MatchSimulator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class LiveMatchOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_session_snapshots_host_squad_and_remains_in_lobby(): void
    {
        Queue::fake();
        $host = User::factory()->create();
        $team = $this->seedNationalTeam('Spain', 'ES');

        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, $team);

        $this->assertSame(LiveMatchPhase::Lobby, $session->phase);
        $this->assertSame($host->id, $session->host_user_id);
        $this->assertSame($team->id, $session->host_team_id);
        $this->assertNull($session->guest_user_id);
        $this->assertNotNull($session->host_squad);
        $this->assertCount(11, $session->host_squad['starting_xi']);
        $this->assertGreaterThanOrEqual(7, count($session->host_squad['bench']));
    }

    public function test_first_non_host_visitor_claims_guest_slot(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $team = $this->seedNationalTeam('Spain', 'ES');
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, $team);

        $orchestrator->claimGuestSlot($session, $guest);

        $this->assertSame($guest->id, $session->fresh()->guest_user_id);
    }

    public function test_host_cannot_claim_guest_slot(): void
    {
        $host = User::factory()->create();
        $team = $this->seedNationalTeam('Spain', 'ES');
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, $team);

        $this->expectException(LiveMatchStateException::class);
        $orchestrator->claimGuestSlot($session, $host);
    }

    public function test_third_visitor_cannot_take_guest_slot(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $third = User::factory()->create();
        $team = $this->seedNationalTeam('Spain', 'ES');
        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, $team);
        $orchestrator->claimGuestSlot($session, $guest);

        $this->expectException(LiveMatchStateException::class);
        $orchestrator->claimGuestSlot($session->fresh(), $third);
    }

    public function test_guest_picking_team_with_both_users_present_starts_the_match(): void
    {
        Queue::fake();
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $hostTeam = $this->seedNationalTeam('Spain', 'ES');
        $guestTeam = $this->seedNationalTeam('Brazil', 'BR');

        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, $hostTeam);
        $orchestrator->claimGuestSlot($session, $guest);

        $session = $orchestrator->pickGuestTeam($session->fresh(), $guest, $guestTeam);

        $this->assertSame(LiveMatchPhase::Live, $session->phase);
        $this->assertNotNull($session->context_state);
        Queue::assertPushed(AdvanceLiveMatchWindowJob::class);
    }

    public function test_acknowledge_pause_only_resumes_once_when_acks_race(): void
    {
        Queue::fake();
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $hostTeam = $this->seedNationalTeam('Spain', 'ES');
        $guestTeam = $this->seedNationalTeam('Brazil', 'BR');

        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, $hostTeam);
        $orchestrator->claimGuestSlot($session, $guest);
        $session = $orchestrator->pickGuestTeam($session->fresh(), $guest, $guestTeam);
        Queue::assertPushed(AdvanceLiveMatchWindowJob::class, 1);

        // Force the session into a halftime-style pause to exercise the ack path.
        $session->update([
            'phase' => LiveMatchPhase::Paused,
            'pause_reason' => 'halftime',
            'pause_acked_by_host' => false,
            'pause_acked_by_guest' => false,
            'current_minute' => 50,
        ]);

        // Both acks arrive. Even though the second one observes both flags
        // truthy, only the *first* commit transitions Paused→Live, so the
        // adapter should only dispatch one advance job.
        $orchestrator->acknowledgePause($session->fresh(), $host);
        $orchestrator->acknowledgePause($session->fresh(), $guest);

        $this->assertSame(LiveMatchPhase::Live, $session->fresh()->phase);
        // 1 kickoff job + 1 resume job = 2 total.
        Queue::assertPushed(AdvanceLiveMatchWindowJob::class, 2);
    }

    public function test_queue_rejects_invalid_formation_payload(): void
    {
        Queue::fake();
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $hostTeam = $this->seedNationalTeam('Spain', 'ES');
        $guestTeam = $this->seedNationalTeam('Brazil', 'BR');

        $orchestrator = $this->makeOrchestrator();
        $session = $orchestrator->createSession($host, $hostTeam);
        $orchestrator->claimGuestSlot($session, $guest);
        $session = $orchestrator->pickGuestTeam($session->fresh(), $guest, $guestTeam);
        $session->update([
            'phase' => LiveMatchPhase::Paused,
            'pause_reason' => 'halftime',
        ]);

        $queue = new LiveMatchActionQueue;

        $this->expectException(InvalidLiveActionException::class);
        $queue->queue($session->fresh(), $host, QueuedActionType::Formation, ['formation' => 'not-a-real-formation']);
    }

    private function makeOrchestrator(): LiveMatchOrchestrator
    {
        $squadBuilder = new NationalSquadBuilder;
        $autoLineupBuilder = new AutoLineupBuilder;
        $engineAdapter = new LiveMatchEngineAdapter(new MatchSimulator, $squadBuilder);

        return new LiveMatchOrchestrator($squadBuilder, $autoLineupBuilder, $engineAdapter);
    }

    /**
     * Seed a national Team and 23 GamePlayerTemplate rows linked to it.
     * Mirrors what tournament mode expects: type=national + fifa_code +
     * templates with team_id pointing at the Team.
     */
    private function seedNationalTeam(string $name, string $country): Team
    {
        $team = Team::factory()->create([
            'type' => 'national',
            'name' => $name,
            'country' => $country,
            'fifa_code' => strtoupper(substr($name, 0, 3)),
        ]);

        $positions = ['Goalkeeper', 'Centre-Back', 'Left-Back', 'Right-Back', 'Central Midfield', 'Attacking Midfield', 'Left Winger', 'Right Winger', 'Centre-Forward'];
        for ($i = 0; $i < 23; $i++) {
            GamePlayerTemplate::create([
                'season' => '2025/2026',
                'player_id' => (string) Str::uuid(),
                'transfermarkt_id' => 'tm-'.Str::random(8),
                'name' => "{$name} Player {$i}",
                'date_of_birth' => '1995-01-01',
                'nationality' => [$name],
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

        return $team;
    }
}
