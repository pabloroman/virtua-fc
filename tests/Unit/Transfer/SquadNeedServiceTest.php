<?php

namespace Tests\Unit\Transfer;

use App\Models\GamePlayer;
use App\Modules\Transfer\Services\SquadNeedService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Pure-compute coverage for the buyer desire/need model. No DB — rosters are
 * built from in-memory GamePlayer instances via forceFill so the position_group
 * and overall_score accessors resolve without persistence.
 */
class SquadNeedServiceTest extends TestCase
{
    private SquadNeedService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SquadNeedService();
    }

    private function player(string $position, int $overall): GamePlayer
    {
        return (new GamePlayer)->forceFill([
            'position' => $position,
            'overall_score' => $overall,
        ]);
    }

    /**
     * @param  array<int, GamePlayer>  $players
     * @return Collection<int, GamePlayer>
     */
    private function roster(array $players): Collection
    {
        return collect($players);
    }

    public function test_position_deficit_is_ideal_minus_current_floored_at_zero(): void
    {
        $roster = $this->roster([
            $this->player('Centre-Forward', 70),
            $this->player('Centre-Forward', 65),
        ]);

        // Forward ideal = 4, have 2 → deficit 2.
        $this->assertSame(2, $this->service->positionDeficit($roster, 'Forward'));
        // Midfielder ideal = 6, have 0 → deficit 6.
        $this->assertSame(6, $this->service->positionDeficit($roster, 'Midfielder'));
    }

    public function test_full_group_has_zero_deficit(): void
    {
        $players = [];
        for ($i = 0; $i < 6; $i++) {
            $players[] = $this->player('Central Midfield', 70);
        }

        $this->assertSame(0, $this->service->positionDeficit($this->roster($players), 'Midfielder'));
    }

    public function test_best_in_group_returns_max_or_null(): void
    {
        $roster = $this->roster([
            $this->player('Central Midfield', 62),
            $this->player('Central Midfield', 78),
            $this->player('Centre-Forward', 90),
        ]);

        $this->assertSame(78, $this->service->bestInGroup($roster, 'Midfielder'));
        $this->assertNull($this->service->bestInGroup($roster, 'Goalkeeper'));
    }

    public function test_desire_high_when_group_empty_and_player_strong(): void
    {
        // Buyer fields only forwards; the target is a strong midfielder.
        $roster = $this->roster([
            $this->player('Centre-Forward', 60),
            $this->player('Centre-Forward', 60),
        ]);

        $desire = $this->service->desireScore($roster, $this->player('Central Midfield', 85));

        $this->assertGreaterThan(0.8, $desire);
    }

    public function test_desire_low_when_group_overstocked_and_player_below_best(): void
    {
        $players = [];
        for ($i = 0; $i < 8; $i++) {
            $players[] = $this->player('Central Midfield', 75);
        }

        $desire = $this->service->desireScore($this->roster($players), $this->player('Central Midfield', 55));

        // need 0, upgrade 0, finance neutral → 0.15 * 0.5 = 0.075.
        $this->assertLessThan(0.2, $desire);
    }

    public function test_desire_increases_with_position_need(): void
    {
        $target = $this->player('Central Midfield', 70);

        $deep = [];
        for ($i = 0; $i < 6; $i++) {
            $deep[] = $this->player('Central Midfield', 70);
        }
        $thin = [$this->player('Central Midfield', 70)];

        $low = $this->service->desireScore($this->roster($deep), $target);
        $high = $this->service->desireScore($this->roster($thin), $target);

        $this->assertGreaterThan($low, $high);
    }

    public function test_desire_increases_with_player_quality(): void
    {
        $roster = $this->roster([
            $this->player('Central Midfield', 60),
            $this->player('Central Midfield', 60),
        ]);

        $weak = $this->service->desireScore($roster, $this->player('Central Midfield', 55));
        $strong = $this->service->desireScore($roster, $this->player('Central Midfield', 85));

        $this->assertGreaterThan($weak, $strong);
    }

    public function test_null_financial_headroom_is_neutral_half(): void
    {
        $roster = $this->roster([$this->player('Central Midfield', 70)]);
        $target = $this->player('Central Midfield', 70);

        $this->assertSame(
            $this->service->desireScore($roster, $target, 0.5),
            $this->service->desireScore($roster, $target, null),
        );
    }

    public function test_desire_is_clamped_to_unit_interval(): void
    {
        $roster = $this->roster([$this->player('Centre-Forward', 40)]);

        $desire = $this->service->desireScore($roster, $this->player('Central Midfield', 99), 1.0);

        $this->assertGreaterThanOrEqual(0.0, $desire);
        $this->assertLessThanOrEqual(1.0, $desire);
    }

    public function test_jitter_is_deterministic_and_within_band(): void
    {
        $first = $this->service->jitter('offer-uuid-A', 0.05);
        $second = $this->service->jitter('offer-uuid-A', 0.05);

        $this->assertSame($first, $second);
        $this->assertGreaterThanOrEqual(-0.05, $first);
        $this->assertLessThanOrEqual(0.05, $first);
    }

    public function test_jitter_varies_by_seed(): void
    {
        $this->assertNotSame(
            $this->service->jitter('seed-A', 0.05),
            $this->service->jitter('seed-B', 0.05),
        );
    }
}
