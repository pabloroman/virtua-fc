<?php

namespace Tests\Feature;

use App\Events\SeasonStarted;
use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameNotification;
use App\Models\Team;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademyYearRoundProspectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_season_start_does_not_create_an_academy_batch(): void
    {
        $game = $this->makeGame(tier: 4);

        // Firing SeasonStarted used to dump a full batch of prospects; the
        // academy now starts empty and fills gradually during play.
        event(new SeasonStarted($game));

        $this->assertSame(0, AcademyPlayer::where('game_id', $game->id)->count());
    }

    public function test_prospects_can_arrive_on_different_dates_through_the_season(): void
    {
        $service = app(YouthAcademyService::class);
        $game = $this->makeGame(tier: 4);

        $game->update(['current_date' => '2024-09-01']);
        $first = $this->forceProspect($service, $game->refresh());

        $game->update(['current_date' => '2025-02-15']);
        $second = $this->forceProspect($service, $game->refresh());

        $this->assertSame('2024-09-01', $first->appeared_at->toDateString());
        $this->assertSame('2025-02-15', $second->appeared_at->toDateString());
        $this->assertSame(2, AcademyPlayer::where('game_id', $game->id)->count());
    }

    public function test_notifies_manager_for_each_new_prospect(): void
    {
        $service = app(YouthAcademyService::class);
        $notifications = app(NotificationService::class);
        $game = $this->makeGame(tier: 3);

        $prospect = $this->forceProspect($service, $game);
        $notifications->notifyAcademyProspect($game, $prospect);

        $notification = GameNotification::where('game_id', $game->id)
            ->where('type', GameNotification::TYPE_ACADEMY_PROSPECT)
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString($prospect->name, $notification->message);
    }

    private function forceProspect(YouthAcademyService $service, Game $game): AcademyPlayer
    {
        for ($i = 0; $i < 500; $i++) {
            $prospect = $service->maybeGenerateProspect($game);
            if ($prospect instanceof AcademyPlayer) {
                return $prospect;
            }
        }

        $this->fail('No prospect generated in 500 ticks.');
    }

    private function makeGame(int $tier): Game
    {
        $team = Team::factory()->create();

        $game = Game::factory()->forTeam($team)->create([
            'season' => '2025',
            'current_date' => '2024-08-15',
        ]);

        GameInvestment::create([
            'game_id' => $game->id,
            'season' => 2025,
            'available_surplus' => 0,
            'transfer_budget' => 0,
            'youth_academy_amount' => 0,
            'youth_academy_tier' => $tier,
            'medical_amount' => 0,
            'medical_tier' => 0,
            'scouting_amount' => 0,
            'scouting_tier' => 0,
        ]);

        return $game;
    }
}
