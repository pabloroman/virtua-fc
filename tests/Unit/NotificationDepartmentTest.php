<?php

namespace Tests\Unit;

use App\Models\GameNotification;
use Illuminate\Support\Collection;
use ReflectionClass;
use Tests\TestCase;

class NotificationDepartmentTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function allTypes(): array
    {
        $types = [];
        foreach ((new ReflectionClass(GameNotification::class))->getConstants() as $name => $value) {
            if (str_starts_with($name, 'TYPE_')) {
                $types[] = $value;
            }
        }

        return $types;
    }

    public function test_every_notification_type_maps_to_a_known_department(): void
    {
        $this->assertNotEmpty($this->allTypes());

        foreach ($this->allTypes() as $type) {
            $notification = new GameNotification(['type' => $type]);

            $this->assertContains(
                $notification->getDepartment(),
                GameNotification::DEPARTMENTS,
                "Type [{$type}] resolved to an unknown department.",
            );
        }
    }

    public function test_department_summary_keeps_canonical_order_and_counts_unread(): void
    {
        $notifications = new Collection([
            new GameNotification(['type' => GameNotification::TYPE_COMPETITION_ADVANCEMENT, 'read_at' => null]),
            new GameNotification(['type' => GameNotification::TYPE_PLAYER_INJURED, 'read_at' => null]),
            new GameNotification(['type' => GameNotification::TYPE_PLAYER_RECOVERED, 'read_at' => '2024-01-01 00:00:00']),
        ]);

        $summary = GameNotification::departmentSummary($notifications);

        // Sporting comes before competition in DEPARTMENTS order, regardless of
        // the order the notifications were added.
        $this->assertSame(
            [GameNotification::DEPARTMENT_SPORTING, GameNotification::DEPARTMENT_COMPETITION],
            array_column($summary, 'key'),
        );

        $sporting = $summary[0];
        $this->assertSame(2, $sporting['total']);
        $this->assertSame(1, $sporting['unread']);
    }

    public function test_department_summary_omits_empty_departments(): void
    {
        $notifications = new Collection([
            new GameNotification(['type' => GameNotification::TYPE_SCOUT_REPORT_COMPLETE, 'read_at' => null]),
        ]);

        $summary = GameNotification::departmentSummary($notifications);

        $this->assertCount(1, $summary);
        $this->assertSame(GameNotification::DEPARTMENT_SCOUTING, $summary[0]['key']);
    }
}
