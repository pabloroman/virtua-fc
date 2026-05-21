<?php

namespace App\Modules\Lineup\Services;

use App\Models\ClubProfile;
use App\Models\TeamReputation;
use App\Modules\Competition\Services\CalendarService;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Formation;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use Illuminate\Support\Collection;

/**
 * Decision functions for AI-controlled teams: which formation to play,
 * which mentality, which instructions, and how to predict an opponent's
 * tactics for the Scout Opponent surface.
 *
 * Pure decisions; the only collaborators are FormationRecommender (for
 * "given a squad, what's the best shape?"), FormationBiasResolver (curated
 * club identity), and CalendarService (recent form for opponent analysis).
 *
 * Lifted out of LineupService so match prep (`ensureTeamLineup`) and
 * opponent scouting (`predictOpponentTactics`) share a single source of
 * truth instead of independently rebuilding the same decision tree.
 */
class AITacticsService
{
    public function __construct(
        private readonly FormationRecommender $formationRecommender,
        private readonly FormationBiasResolver $formationBiasResolver,
        private readonly CalendarService $calendarService,
    ) {}

    /**
     * Select the best formation for an AI team based on squad composition.
     * Uses FormationRecommender to evaluate all formations and pick the best fit.
     *
     * When $gameId/$teamId are supplied, the recommender is biased toward the
     * team's curated identity (preferred_formation on ClubProfile, with a
     * reputation-tier fallback). Without those, the recommendation is purely
     * mechanical — used for the bare-collection use cases (e.g. one-off
     * squad analysis where no game/team context is available).
     */
    public function selectAIFormation(
        Collection $availablePlayers,
        ?string $gameId = null,
        ?string $teamId = null,
    ): Formation {
        if ($availablePlayers->count() < 11) {
            return Formation::F_4_3_3;
        }

        $bias = ($gameId && $teamId)
            ? $this->formationBiasResolver->resolveForTeam($gameId, $teamId)
            : [];

        return $this->formationRecommender->getBestFormation($availablePlayers, $bias);
    }

    /**
     * Select mentality for an AI team based on reputation, venue, and relative strength.
     *
     * `$aggressionBias` (-2..+2) shifts the deterministic baseline up or down
     * the DEFENSIVE / BALANCED / ATTACKING ladder so curated club identity
     * (Cholo's Atleti at -2, Gasperini's Atalanta at +2) reads through. Same
     * inputs always produce the same output — the function stays deterministic.
     */
    public function selectAIMentality(
        ?string $reputationLevel,
        bool $isHome,
        float $teamAvg,
        float $opponentAvg,
        int $aggressionBias = 0,
    ): Mentality {
        if ($reputationLevel === null || $opponentAvg <= 0) {
            return Mentality::BALANCED;
        }

        $base = $this->baseAIMentality($reputationLevel, $isHome, $teamAvg, $opponentAvg);

        return $this->shiftLadder(
            [Mentality::DEFENSIVE, Mentality::BALANCED, Mentality::ATTACKING],
            $base,
            $aggressionBias,
        );
    }

    /**
     * Deterministic mentality from the venue/strength/tier inputs alone,
     * without identity bias. Kept as a private helper so the public method
     * can apply the bias as a clean ladder shift on top.
     */
    private function baseAIMentality(string $reputationLevel, bool $isHome, float $teamAvg, float $opponentAvg): Mentality
    {
        $diff = $teamAvg - $opponentAvg;
        $isStronger = $diff >= 5;
        $isWeaker = $diff <= -5;

        // Group reputations into tactical tiers
        $tier = match ($reputationLevel) {
            'elite' => 'bold',
            'continental', 'established' => 'mid',
            default => 'cautious', // modest, local
        };

        if ($isHome) {
            if ($isStronger) {
                return $tier === 'cautious' ? Mentality::BALANCED : Mentality::ATTACKING;
            }
            if ($isWeaker) {
                return $tier === 'bold' ? Mentality::BALANCED : Mentality::DEFENSIVE;
            }
            // Similar strength at home
            return Mentality::BALANCED;
        }

        // Away
        if ($isStronger) {
            return $tier === 'cautious' ? Mentality::DEFENSIVE : Mentality::BALANCED;
        }
        if ($isWeaker) {
            return Mentality::DEFENSIVE;
        }
        // Similar strength away
        return $tier === 'bold' ? Mentality::BALANCED : Mentality::DEFENSIVE;
    }

    /**
     * Shift `$base` along the supplied ordered ladder by `$bias` positions,
     * clamped to the ladder bounds. Used to apply the curated tactical-
     * aggression bias to mentality, pressing, defensive line, and playing
     * style outputs without re-implementing the base decision tree.
     *
     * @template T
     * @param  list<T>  $ladder  Ordered defensive→attacking enum cases.
     * @param  T  $base
     * @return T
     */
    private function shiftLadder(array $ladder, $base, int $bias)
    {
        if ($bias === 0) {
            return $base;
        }
        $i = array_search($base, $ladder, true);
        if ($i === false) {
            return $base;
        }
        $shifted = max(0, min(count($ladder) - 1, $i + $bias));
        return $ladder[$shifted];
    }

    /**
     * Select tactical instructions for an AI team based on context.
     *
     * `$aggressionBias` (-2..+2) ladder-shifts each output toward more
     * possession / higher press / higher line for positive values, and
     * toward counter-attack / low block / deep line for negative values.
     *
     * @return array{PlayingStyle, PressingIntensity, DefensiveLineHeight}
     */
    public function selectAIInstructions(
        ?string $reputationLevel,
        bool $isHome,
        float $teamAvg,
        float $opponentAvg,
        int $aggressionBias = 0,
    ): array {
        $diff = $teamAvg - $opponentAvg;
        $isStronger = $diff >= 5;
        $isWeaker = $diff <= -5;

        $tier = match ($reputationLevel) {
            'elite' => 'bold',
            'continental', 'established' => 'mid',
            default => 'cautious',
        };

        // Playing Style
        if ($isStronger && $isHome) {
            $style = $tier === 'cautious' ? PlayingStyle::BALANCED : PlayingStyle::POSSESSION;
        } elseif ($isWeaker && ! $isHome) {
            $style = PlayingStyle::COUNTER_ATTACK;
        } elseif ($isWeaker) {
            $style = PlayingStyle::COUNTER_ATTACK;
        } else {
            $style = $tier === 'bold' ? PlayingStyle::POSSESSION : PlayingStyle::BALANCED;
        }

        // Pressing Intensity
        if ($isStronger && $tier === 'bold') {
            $pressing = PressingIntensity::HIGH_PRESS;
        } elseif ($isWeaker && ! $isHome) {
            $pressing = PressingIntensity::LOW_BLOCK;
        } elseif ($isWeaker) {
            $pressing = $tier === 'bold' ? PressingIntensity::STANDARD : PressingIntensity::LOW_BLOCK;
        } else {
            $pressing = PressingIntensity::STANDARD;
        }

        // Defensive Line
        if ($isStronger && $tier === 'bold') {
            $defLine = $isHome ? DefensiveLineHeight::HIGH_LINE : DefensiveLineHeight::NORMAL;
        } elseif ($isWeaker) {
            $defLine = DefensiveLineHeight::DEEP;
        } else {
            $defLine = DefensiveLineHeight::NORMAL;
        }

        $style = $this->shiftLadder(
            [PlayingStyle::COUNTER_ATTACK, PlayingStyle::BALANCED, PlayingStyle::POSSESSION],
            $style,
            $aggressionBias,
        );
        $pressing = $this->shiftLadder(
            [PressingIntensity::LOW_BLOCK, PressingIntensity::STANDARD, PressingIntensity::HIGH_PRESS],
            $pressing,
            $aggressionBias,
        );
        $defLine = $this->shiftLadder(
            [DefensiveLineHeight::DEEP, DefensiveLineHeight::NORMAL, DefensiveLineHeight::HIGH_LINE],
            $defLine,
            $aggressionBias,
        );

        return [$style, $pressing, $defLine];
    }

    /**
     * Calculate the average overall score for a collection of players.
     */
    public function calculateTeamAverage(Collection $players): int
    {
        if ($players->isEmpty()) {
            return 0;
        }

        return (int) round($players->avg('overall_score'));
    }

    /**
     * Predict an opponent's tactics for the Scout Opponent surface.
     *
     * Same decision tree as `ensureTeamLineup`'s AI-team branch — formation
     * from squad fit, mentality and instructions from reputation/venue/
     * relative-strength, with the team's curated tactical_aggression as a
     * ladder bias.
     *
     * `bestXISlots` carries the full slot→player mapping produced by
     * FormationRecommender so consumers (e.g. the Scout Opponent pitch) can
     * render players in the formation slot the recommender actually placed
     * them in — including secondary/swap/weighted placements where a
     * player's position does not match the slot's role.
     *
     * @param  Collection  $availablePlayers  Pre-loaded opponent squad (caller is responsible for filtering injured/suspended).
     * @return array{teamAverage: int, avgFitness: int, form: array, formation: string, mentality: string, playingStyle: string, pressing: string, defensiveLine: string, bestXIPlayers: Collection, bestXISlots: array<array{slot: array, player: ?\App\Models\GamePlayer}>}
     */
    public function predictOpponentTactics(
        Collection $availablePlayers,
        string $gameId,
        string $opponentTeamId,
        bool $opponentIsHome,
        int $userTeamAverage,
    ): array {
        $predictedFormation = $this->selectAIFormation($availablePlayers, $gameId, $opponentTeamId);

        // Run the recommender directly so we keep the slot→player mapping;
        // selectBestXI drops it. We then derive the flat best-XI collection
        // from the slot assignments to keep both views perfectly consistent.
        $slotAssignments = $this->formationRecommender->bestXIFor($predictedFormation, $availablePlayers);
        $playersById = $availablePlayers->keyBy('id');
        $bestXISlots = [];
        $bestXI = collect();
        foreach ($slotAssignments as $assignment) {
            $playerId = $assignment['player']['id'] ?? null;
            $player = $playerId ? $playersById->get($playerId) : null;
            $bestXISlots[] = ['slot' => $assignment['slot'], 'player' => $player];
            if ($player) {
                $bestXI->push($player);
            }
        }

        $teamAverage = $this->calculateTeamAverage($bestXI);
        $avgFitness = (int) round($bestXI->avg('fitness') ?? 0);

        $opponentReputation = TeamReputation::resolveLevel($gameId, $opponentTeamId);
        $aggressionBias = (int) (ClubProfile::where('team_id', $opponentTeamId)->value('tactical_aggression') ?? 0);

        $predictedMentality = $this->selectAIMentality(
            $opponentReputation,
            $opponentIsHome,
            $teamAverage,
            $userTeamAverage,
            $aggressionBias,
        );

        [$predictedStyle, $predictedPressing, $predictedDefLine] = $this->selectAIInstructions(
            $opponentReputation,
            $opponentIsHome,
            $teamAverage,
            $userTeamAverage,
            $aggressionBias,
        );

        $form = $this->calendarService->getTeamForm($gameId, $opponentTeamId);

        return [
            'teamAverage' => $teamAverage,
            'avgFitness' => $avgFitness,
            'form' => $form,
            'formation' => $predictedFormation->value,
            'mentality' => $predictedMentality->value,
            'playingStyle' => $predictedStyle->value,
            'pressing' => $predictedPressing->value,
            'defensiveLine' => $predictedDefLine->value,
            'bestXIPlayers' => $bestXI,
            'bestXISlots' => $bestXISlots,
        ];
    }
}
