<?php

namespace Tests\Feature;

use App\Models\ActivationEvent;
use App\Models\Game;
use App\Models\InviteCode;
use App\Models\User;
use App\Modules\Analytics\Services\ActivationFunnelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivationFunnelServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActivationFunnelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ActivationFunnelService::class);
    }

    public function test_career_funnel_excludes_tournament_only_users(): void
    {
        $careerUser = User::factory()->create(['has_career_access' => true]);
        $tournamentUser = User::factory()->create(['has_tournament_access' => true, 'has_career_access' => false]);

        ActivationEvent::create([
            'user_id' => $careerUser->id,
            'event' => ActivationEvent::EVENT_REGISTERED,
            'occurred_at' => now(),
        ]);

        ActivationEvent::create([
            'user_id' => $tournamentUser->id,
            'event' => ActivationEvent::EVENT_REGISTERED,
            'occurred_at' => now(),
        ]);

        $result = $this->service->getFunnel('all', Game::MODE_CAREER);

        $this->assertEquals(1, $result['totalRegistered']);
    }

    public function test_career_funnel_includes_admin_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'has_career_access' => false]);

        ActivationEvent::create([
            'user_id' => $admin->id,
            'event' => ActivationEvent::EVENT_REGISTERED,
            'occurred_at' => now(),
        ]);

        $result = $this->service->getFunnel('all', Game::MODE_CAREER);

        $this->assertEquals(1, $result['totalRegistered']);
    }

    public function test_career_funnel_invite_count_only_counts_career_grants(): void
    {
        InviteCode::create([
            'code' => 'CAREER1',
            'invite_sent' => true,
            'invite_sent_at' => now(),
            'grants_career' => true,
            'grants_tournament' => false,
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        InviteCode::create([
            'code' => 'CAREER2',
            'invite_sent' => true,
            'invite_sent_at' => now(),
            'grants_career' => true,
            'grants_tournament' => true,
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        InviteCode::create([
            'code' => 'TOURNAMENT1',
            'invite_sent' => true,
            'invite_sent_at' => now(),
            'grants_career' => false,
            'grants_tournament' => true,
            'max_uses' => 1,
            'times_used' => 0,
        ]);

        $result = $this->service->getFunnel('all', Game::MODE_CAREER);

        $this->assertEquals(2, $result['totalInvites']);
    }

    public function test_tournament_funnel_excludes_career_only_users(): void
    {
        $careerUser = User::factory()->create(['has_career_access' => true, 'has_tournament_access' => false]);
        $tournamentUser = User::factory()->create(['has_tournament_access' => true]);

        ActivationEvent::create([
            'user_id' => $tournamentUser->id,
            'game_mode' => Game::MODE_TOURNAMENT,
            'event' => ActivationEvent::EVENT_GAME_CREATED,
            'occurred_at' => now(),
        ]);

        $result = $this->service->getFunnel('all', Game::MODE_TOURNAMENT);

        // totalRegistered in tournament funnel is the count of users with tournament access
        $this->assertEquals(1, $result['totalRegistered']);
    }

    public function test_career_funnel_computes_overall_conversion(): void
    {
        $user = User::factory()->create(['has_career_access' => true]);

        ActivationEvent::create([
            'user_id' => $user->id,
            'event' => ActivationEvent::EVENT_REGISTERED,
            'game_mode' => null,
            'occurred_at' => now(),
        ]);

        ActivationEvent::create([
            'user_id' => $user->id,
            'event' => ActivationEvent::EVENT_FIRST_MATCH_PLAYED,
            'game_mode' => Game::MODE_CAREER,
            'occurred_at' => now(),
        ]);

        $result = $this->service->getFunnel('all', Game::MODE_CAREER);

        $this->assertEquals(100.0, $result['overallConversion']);
    }

    public function test_career_funnel_respects_time_period(): void
    {
        $recentUser = User::factory()->create(['has_career_access' => true]);
        $oldUser = User::factory()->create(['has_career_access' => true]);

        ActivationEvent::create([
            'user_id' => $recentUser->id,
            'event' => ActivationEvent::EVENT_REGISTERED,
            'occurred_at' => now()->subDays(5),
        ]);

        ActivationEvent::create([
            'user_id' => $oldUser->id,
            'event' => ActivationEvent::EVENT_REGISTERED,
            'occurred_at' => now()->subDays(60),
        ]);

        $result = $this->service->getFunnel('30', Game::MODE_CAREER);

        $this->assertEquals(1, $result['totalRegistered']);
    }
}
