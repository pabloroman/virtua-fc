<?php

namespace Tests\Feature;

use App\Game\Services\NotificationService;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(NotificationService::class);

        // Create minimal test data
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->game = Game::create([
            'id' => fake()->uuid(),
            'user_id' => $user->id,
            'player_name' => 'Test Manager',
            'team_id' => $team->id,
            'season' => '2024',
            'current_matchday' => 1,
            'current_date' => now(),
        ]);
    }

    public function test_can_create_notification(): void
    {
        $notification = $this->service->create(
            game: $this->game,
            type: GameNotification::TYPE_PLAYER_INJURED,
            title: 'Test Player Injured',
            message: 'Test player has been injured',
            priority: GameNotification::PRIORITY_CRITICAL,
        );

        $this->assertNotNull($notification);
        $this->assertEquals($this->game->id, $notification->game_id);
        $this->assertEquals(GameNotification::TYPE_PLAYER_INJURED, $notification->type);
        $this->assertNull($notification->read_at);
    }

    public function test_can_mark_notification_as_read(): void
    {
        $notification = GameNotification::create([
            'id' => fake()->uuid(),
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_PLAYER_INJURED,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_INFO,
        ]);

        $this->assertNull($notification->read_at);

        $this->service->markAsRead($notification->id);
        $notification->refresh();

        $this->assertNotNull($notification->read_at);
    }

    public function test_get_unread_count(): void
    {
        // Create 3 unread notifications
        for ($i = 0; $i < 3; $i++) {
            GameNotification::create([
                'id' => fake()->uuid(),
                'game_id' => $this->game->id,
                'type' => GameNotification::TYPE_PLAYER_INJURED,
                'title' => "Test $i",
                'priority' => GameNotification::PRIORITY_INFO,
            ]);
        }

        // Create 1 read notification
        GameNotification::create([
            'id' => fake()->uuid(),
            'game_id' => $this->game->id,
            'type' => GameNotification::TYPE_PLAYER_INJURED,
            'title' => 'Read notification',
            'priority' => GameNotification::PRIORITY_INFO,
            'read_at' => now(),
        ]);

        $count = $this->service->getUnreadCount($this->game->id);

        $this->assertEquals(3, $count);
    }

    public function test_can_mark_all_notifications_as_read(): void
    {
        // Create 3 unread notifications
        for ($i = 0; $i < 3; $i++) {
            GameNotification::create([
                'id' => fake()->uuid(),
                'game_id' => $this->game->id,
                'type' => GameNotification::TYPE_PLAYER_INJURED,
                'title' => "Test $i",
                'priority' => GameNotification::PRIORITY_INFO,
            ]);
        }

        $this->assertEquals(3, $this->service->getUnreadCount($this->game->id));

        $this->service->markAllAsRead($this->game->id);

        $this->assertEquals(0, $this->service->getUnreadCount($this->game->id));
    }

    public function test_notification_navigation_routes(): void
    {
        $types = [
            GameNotification::TYPE_PLAYER_INJURED => 'game.squad',
            GameNotification::TYPE_PLAYER_SUSPENDED => 'game.squad',
            GameNotification::TYPE_LOW_FITNESS => 'game.squad',
            GameNotification::TYPE_TRANSFER_OFFER_RECEIVED => 'game.transfers',
            GameNotification::TYPE_SCOUT_REPORT_COMPLETE => 'game.scouting',
            GameNotification::TYPE_CONTRACT_EXPIRING => 'game.squad.contracts',
        ];

        foreach ($types as $type => $expectedRoute) {
            $notification = new GameNotification([
                'type' => $type,
                'title' => 'Test',
                'priority' => GameNotification::PRIORITY_INFO,
            ]);

            $this->assertEquals($expectedRoute, $notification->getNavigationRoute(), "Failed for type: $type");
        }
    }

    public function test_notification_priority_classes(): void
    {
        $critical = new GameNotification([
            'type' => GameNotification::TYPE_PLAYER_INJURED,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_CRITICAL,
        ]);

        $warning = new GameNotification([
            'type' => GameNotification::TYPE_PLAYER_INJURED,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_WARNING,
        ]);

        $info = new GameNotification([
            'type' => GameNotification::TYPE_PLAYER_INJURED,
            'title' => 'Test',
            'priority' => GameNotification::PRIORITY_INFO,
        ]);

        $this->assertStringContainsString('red', $critical->getPriorityClasses()['bg']);
        $this->assertStringContainsString('amber', $warning->getPriorityClasses()['bg']);
        $this->assertStringContainsString('sky', $info->getPriorityClasses()['bg']);
    }
}
