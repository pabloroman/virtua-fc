<?php

namespace Tests\Unit;

use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Services\OpponentAnalysisBuilder;
use Tests\TestCase;

class OpponentAnalysisBuilderTest extends TestCase
{
    private OpponentAnalysisBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new OpponentAnalysisBuilder();
    }

    /**
     * Anonymous class that mirrors the slice of GamePlayer the builder
     * touches: getEffectiveRating() for top-threats sort, plus public
     * fitness/morale/position_group for radar/coach-tip helpers.
     */
    private function fakePlayer(string $name, int $rating, string $group = 'Defender'): object
    {
        return new class($name, $rating, $group) {
            public function __construct(
                public string $name,
                public int $rating,
                public string $position_group,
                public int $fitness = 90,
                public int $morale = 80,
            ) {}

            public function getEffectiveRating(): int
            {
                return $this->rating;
            }
        };
    }

    public function test_build_uses_slot_assignments_directly_so_secondary_placements_are_not_lost(): void
    {
        // Reproduces the Scout Opponent RB-empty bug: the recommender placed
        // a Forward into the RB slot via secondary, and the old position_group
        // bucketing dropped the player off the pitch entirely. With the slot
        // map driving the layout, every slot in the predicted XI must carry
        // its assigned player — including the cross-group placement.
        $rbPlayer = $this->fakePlayer('Aitor Ruibal', 73, 'Forward');
        $bestXIPlayers = collect([
            $this->fakePlayer('Valles', 79, 'Goalkeeper'),
            $this->fakePlayer('Firpo', 73, 'Defender'),
            $this->fakePlayer('Natan', 79, 'Defender'),
            $this->fakePlayer('Gomez', 78, 'Defender'),
            $rbPlayer, // forward placed at RB via secondary
            $this->fakePlayer('Amrabat', 77, 'Midfielder'),
            $this->fakePlayer('Lo Celso', 78, 'Midfielder'),
            $this->fakePlayer('Altimira', 79, 'Midfielder'),
            $this->fakePlayer('Abde', 81, 'Forward'),
            $this->fakePlayer('Bakambu', 75, 'Forward'),
            $this->fakePlayer('Antony', 83, 'Forward'),
        ]);

        $slots = Formation::F_4_3_3->pitchSlots();
        $bestXISlots = [];
        foreach ($slots as $i => $slot) {
            $bestXISlots[] = ['slot' => $slot, 'player' => $bestXIPlayers->get($i)];
        }

        $result = $this->builder->build([
            'bestXIPlayers' => $bestXIPlayers,
            'bestXISlots' => $bestXISlots,
            'formation' => '4-3-3',
            'mentality' => 'balanced',
            'playingStyle' => 'balanced',
            'pressing' => 'standard',
            'defensiveLine' => 'normal',
        ]);

        $this->assertCount(11, $result['pitchSlots']);
        foreach ($result['pitchSlots'] as $entry) {
            $this->assertNotNull(
                $entry['player'],
                "Slot {$entry['slot']['label']} should be filled — the slot map was authoritative",
            );
        }

        $rbEntry = collect($result['pitchSlots'])->firstWhere('slot.label', 'RB');
        $this->assertSame($rbPlayer, $rbEntry['player'], 'Cross-group placement (Forward at RB) must survive');
    }

    public function test_top_threats_picks_highest_rated_players(): void
    {
        $best = $this->fakePlayer('A', 90);
        $mid = $this->fakePlayer('B', 70);
        $worst = $this->fakePlayer('C', 50);

        $bestXIPlayers = collect([$worst, $best, $mid]);

        $result = $this->builder->build([
            'bestXIPlayers' => $bestXIPlayers,
            'bestXISlots' => [],
            'formation' => '4-3-3',
            'mentality' => 'balanced',
            'playingStyle' => 'balanced',
            'pressing' => 'standard',
            'defensiveLine' => 'normal',
        ]);

        $this->assertSame($best, $result['topThreats']->first(), 'Highest effective rating should top the list');
    }
}
