<?php

namespace App\Modules\Lineup\Services;

use App\Models\GamePlayer;
use App\Modules\Lineup\Enums\DefensiveLineHeight;
use App\Modules\Lineup\Enums\Mentality;
use App\Modules\Lineup\Enums\PlayingStyle;
use App\Modules\Lineup\Enums\PressingIntensity;
use App\Support\PositionSlotMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Composes the derived view data shared by the Scout Opponent page and the
 * pre-match analysis modal embedded in the lineup view.
 */
class OpponentAnalysisBuilder
{
    /**
     * Build the secondary derivations on top of LineupService::predictOpponentTactics().
     *
     * @return array{
     *     pitchSlots: list<array{slot: array, player: ?object}>,
     *     topThreats: \Illuminate\Support\Collection,
     *     tacticsSummaries: array<string, array{label: string, summary: string}>,
     * }
     */
    public function build(array $opponentData): array
    {
        $bestXI = $opponentData['bestXIPlayers']->values();

        // Walk the slot→player map produced by FormationRecommender. This is
        // the authoritative placement: bin-grouping by position_group used to
        // misalign here when a player was placed via secondary/swap/weighted
        // (e.g. a winger covering RB), leaving a slot empty.
        $slotsWithPlayers = array_map(function ($entry) {
            $slot = $entry['slot'];
            $slot['displayLabel'] = PositionSlotMapper::slotToDisplayAbbreviation($slot['label']);
            return ['slot' => $slot, 'player' => $entry['player'] ?? null];
        }, $opponentData['bestXISlots'] ?? []);

        $topThreats = $bestXI->sortByDesc(fn ($p) => $p->getEffectiveRating())
            ->take(5)
            ->values();

        $mentality = Mentality::tryFrom($opponentData['mentality']) ?? Mentality::BALANCED;
        $playingStyle = PlayingStyle::tryFrom($opponentData['playingStyle']) ?? PlayingStyle::BALANCED;
        $pressing = PressingIntensity::tryFrom($opponentData['pressing']) ?? PressingIntensity::STANDARD;
        $defLine = DefensiveLineHeight::tryFrom($opponentData['defensiveLine']) ?? DefensiveLineHeight::NORMAL;

        return [
            'pitchSlots' => $slotsWithPlayers,
            'topThreats' => $topThreats,
            'tacticsSummaries' => [
                'mentality' => ['label' => $mentality->label(), 'summary' => $mentality->summary()],
                'playingStyle' => ['label' => $playingStyle->label(), 'summary' => $playingStyle->summary()],
                'pressing' => ['label' => $pressing->label(), 'summary' => $pressing->summary()],
                'defensiveLine' => ['label' => $defLine->label(), 'summary' => $defLine->summary()],
            ],
        ];
    }

    /**
     * Pre-lineup coach tips for the standalone Scout Opponent page.
     *
     * Mirrors the subset of resources/js/modules/coach-tips.js that does not
     * depend on user-selected lineup state (no chosen formation/mentality/
     * selected XI yet). The remaining tips speak to the opponent's setup,
     * the strength gap, and the fitness/morale of the current best XI.
     *
     * @param  Collection  $userBestXI  user's best XI from LineupService::getBestXIWithAverage()
     * @return list<array{id: string, type: string, message: string}>
     */
    public function coachTips(array $opponentData, Collection $userBestXI, int $userTeamAverage, bool $isHome): array
    {
        $tips = [];
        $oppAvg = (int) ($opponentData['teamAverage'] ?? 0);
        $diff = $userTeamAverage - $oppAvg;
        $isWeaker = $oppAvg > 0 && $diff <= -5;
        $isStronger = $oppAvg > 0 && $diff >= 5;
        $oppFm = $opponentData['formation'] ?? null;
        $oppMent = $opponentData['mentality'] ?? null;
        $oppMentLabel = $oppMent ? __('squad.mentality_' . $oppMent) : '';

        // Opponent tactical setup
        if ($oppMent === 'defensive' && $oppFm) {
            $tips[] = ['id' => 'opp_defensive', 'priority' => 1, 'type' => 'info', 'message' => __('squad.coach_opponent_defensive_setup', ['formation' => $oppFm, 'mentality' => $oppMentLabel])];
        } elseif ($oppMent === 'attacking' && $oppFm) {
            $tips[] = ['id' => 'opp_attacking', 'priority' => 1, 'type' => 'info', 'message' => __('squad.coach_opponent_attacking_setup', ['formation' => $oppFm, 'mentality' => $oppMentLabel])];
        } elseif ($isWeaker) {
            $tips[] = ['id' => 'defensive_recommended', 'priority' => 1, 'type' => 'warning', 'message' => __('squad.coach_defensive_recommended')];
        }

        if ($isStronger && $oppMent !== 'attacking') {
            $tips[] = ['id' => 'attacking_vs_weaker', 'priority' => 4, 'type' => 'info', 'message' => __('squad.coach_attacking_recommended')];
        }

        // 5-at-the-back deep block
        if ($oppFm && (str_starts_with($oppFm, '5-') || $oppFm === '5-3-2' || $oppFm === '5-4-1')) {
            $tips[] = ['id' => 'opp_deep_block', 'priority' => 3, 'type' => 'info', 'message' => __('squad.coach_opponent_deep_block')];
        }

        // Fitness / morale signals from the projected best XI
        if ($userBestXI->isNotEmpty()) {
            $criticalNames = [];
            $lowFitnessCount = 0;
            $lowMoraleCount = 0;
            foreach ($userBestXI as $p) {
                if ($p->fitness < 50) {
                    $criticalNames[] = $p->name;
                } elseif ($p->fitness < 70) {
                    $lowFitnessCount++;
                }
                if ($p->morale < 60) {
                    $lowMoraleCount++;
                }
            }
            if (!empty($criticalNames)) {
                $tips[] = ['id' => 'critical_fitness', 'priority' => 0, 'type' => 'warning', 'message' => __('squad.coach_critical_fitness', ['names' => implode(', ', $criticalNames)])];
            }
            if ($lowFitnessCount > 0) {
                $tips[] = ['id' => 'low_fitness', 'priority' => 1, 'type' => 'warning', 'message' => __('squad.coach_low_fitness', ['count' => $lowFitnessCount])];
            }
            if ($lowMoraleCount > 0) {
                $tips[] = ['id' => 'low_morale', 'priority' => 2, 'type' => 'warning', 'message' => __('squad.coach_low_morale', ['count' => $lowMoraleCount])];
            }
        }

        // Home advantage (low priority filler)
        if ($isHome) {
            $tips[] = ['id' => 'home_advantage', 'priority' => 5, 'type' => 'info', 'message' => __('squad.coach_home_advantage')];
        }

        usort($tips, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        // Drop the home-advantage filler if we already have 4 stronger tips.
        if (count($tips) > 4) {
            $tips = array_values(array_filter($tips, fn ($tip) => $tip['id'] !== 'home_advantage'));
        }

        return array_map(
            fn ($tip) => ['id' => $tip['id'], 'type' => $tip['type'], 'message' => $tip['message']],
            array_slice($tips, 0, 4),
        );
    }

    /**
     * Build the opponent's unavailable players (injured or suspended) for the upcoming match.
     *
     * `predictOpponentTactics()` filters these out before running the formation
     * recommender, so they're invisible to the predicted-XI pipeline. The Scout
     * Opponent page surfaces them in a separate card.
     *
     * @param  Collection<int, GamePlayer>  $allOpponentPlayers  full opponent squad with `suspensions` eager-loaded
     * @return Collection<int, array{player: GamePlayer, reason: string, type: 'suspended'|'injured'}>
     */
    public function absentees(Collection $allOpponentPlayers, Carbon $matchDate, string $competitionId): Collection
    {
        return $allOpponentPlayers
            ->reject(fn (GamePlayer $p) => $p->isAvailable($matchDate, $competitionId))
            ->map(fn (GamePlayer $p) => [
                'player' => $p,
                'reason' => $p->getUnavailabilityReason($matchDate, $competitionId) ?? '',
                'type' => $p->isSuspendedInCompetition($competitionId) ? 'suspended' : 'injured',
            ])
            ->sort(function (array $a, array $b) {
                $posCmp = LineupService::positionSortOrder($a['player']->position)
                    <=> LineupService::positionSortOrder($b['player']->position);
                if ($posCmp !== 0) {
                    return $posCmp;
                }
                return $b['player']->getEffectiveRating() <=> $a['player']->getEffectiveRating();
            })
            ->values();
    }

    /**
     * Calculate radar chart values from a Best XI collection.
     *
     * @return array<string, int>
     */
    public function radarFor(Collection $players): array
    {
        if ($players->isEmpty()) {
            return array_fill_keys(['goalkeeper', 'defense', 'midfield', 'attack', 'fitness', 'morale', 'overall'], 0);
        }

        $grouped = $players->groupBy(fn ($p) => $p->position_group);

        $avgOverall = fn (string $group) => (int) round(
            ($grouped->get($group) ?? collect())->avg(fn ($p) => $p->effective_rating) ?? 0
        );

        return [
            'goalkeeper' => $avgOverall('Goalkeeper'),
            'defense' => $avgOverall('Defender'),
            'midfield' => $avgOverall('Midfielder'),
            'attack' => $avgOverall('Forward'),
            'fitness' => (int) round($players->avg('fitness')),
            'morale' => (int) round($players->avg('morale')),
            'overall' => (int) round($players->avg(fn ($p) => $p->effective_rating)),
        ];
    }
}
