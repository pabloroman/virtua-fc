<?php

namespace App\Modules\LiveMatch\Services;

use App\Models\Team;
use App\Modules\LiveMatch\DTOs\SquadSnapshot;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Modules\Lineup\Services\FormationRecommender;
use Illuminate\Support\Collection;

class AutoLineupBuilder
{
    public function __construct(
        private readonly FormationRecommender $formationRecommender = new FormationRecommender,
    ) {}

    /**
     * Pick the best formation for this squad, fill it with the best XI, and
     * package the result as a frozen SquadSnapshot. Bench is everyone not in
     * the XI, sorted by overall.
     *
     * @param  Collection  $rehydratedPlayers  Result of NationalSquadBuilder::rehydrate().
     */
    public function build(Team $team, Collection $rehydratedPlayers): SquadSnapshot
    {
        $formation = $this->formationRecommender->getBestFormation($rehydratedPlayers);
        $bestXi = $this->formationRecommender->bestXIFor($formation, $rehydratedPlayers);

        $startingIds = [];
        $slotMap = [];
        foreach ($bestXi as $assignment) {
            if (($assignment['player'] ?? null) !== null) {
                $playerId = $assignment['player']['id'];
                $slotCode = $assignment['slot']['code'] ?? $assignment['slot']['id'] ?? '';
                $startingIds[] = $playerId;
                if ($slotCode !== '') {
                    $slotMap[$playerId] = $slotCode;
                }
            }
        }

        $startingXi = $rehydratedPlayers
            ->filter(fn ($p) => in_array($p->id, $startingIds, true))
            ->map(fn ($p) => $this->summarize($p))
            ->values()
            ->all();

        $bench = $rehydratedPlayers
            ->filter(fn ($p) => ! in_array($p->id, $startingIds, true))
            ->sortByDesc('overall_score')
            ->map(fn ($p) => $this->summarize($p))
            ->values()
            ->all();

        return new SquadSnapshot(
            teamId: $team->id,
            teamName: $team->name,
            country: $team->getAttributes()['country'] ?? null,
            formation: $formation->value,
            mentality: Mentality::BALANCED->value,
            playingStyle: PlayingStyle::BALANCED->value,
            pressing: PressingIntensity::STANDARD->value,
            defensiveLine: DefensiveLineHeight::NORMAL->value,
            startingXi: $startingXi,
            bench: $bench,
            playerSlotMap: $slotMap,
        );
    }

    /**
     * Serialize a player into the frozen snapshot.
     *
     * This must carry every attribute LiveMatchEngineAdapter rehydrates back
     * into a GamePlayer for the simulator — not just the UI-facing fields.
     * In particular `date_of_birth` is mandatory: the simulator calls
     * `$player->age()`, which dereferences it (a null DOB throws and kills the
     * match window, so no events ever broadcast). Mirror NationalSquadBuilder::
     * rehydrate()'s field set.
     */
    private function summarize($player): array
    {
        return [
            'id' => $player->id,
            'team_id' => $player->team_id,
            'name' => $player->name,
            'position' => $player->position,
            'secondary_positions' => $player->secondary_positions ?? [],
            'nationality' => $player->nationality ?? [],
            'overall_score' => $player->overall_score,
            'number' => $player->number,
            'foot' => $player->foot,
            'height' => $player->height,
            'durability' => $player->durability,
            'date_of_birth' => $player->date_of_birth?->toDateString(),
            'tier' => $player->tier,
            'potential' => $player->potential,
            'potential_low' => $player->potential_low,
            'potential_high' => $player->potential_high,
        ];
    }
}
