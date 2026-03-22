<?php

namespace App\Modules\Transfer\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\TeamReputation;
use App\Modules\Player\PlayerAge;

class ClubDispositionService
{
    /**
     * Ideal squad depth per position group.
     * Shared with AITransferMarketService for consistency.
     */
    public const IDEAL_GROUP_COUNTS = [
        'Goalkeeper' => 3,
        'Defender' => 6,
        'Midfielder' => 6,
        'Forward' => 4,
    ];

    /**
     * Calculate a club's willingness to sell a player.
     *
     * Combines player importance, contract length, age, listed status,
     * and squad composition (position surplus + ability gap) into a
     * single 0.10–0.95 score.
     */
    public function calculateSellDisposition(GamePlayer $player, ScoutingService $scoutingService): float
    {
        $disposition = 0.50;

        // Player importance (key players are harder to buy)
        $importance = $scoutingService->calculatePlayerImportance($player);
        if ($importance >= 0.85) {
            $disposition -= 0.20;
        } elseif ($importance >= 0.60) {
            $disposition -= 0.10;
        } elseif ($importance <= 0.30) {
            $disposition += 0.10;
        }

        // Contract length (longer = more reluctant)
        if ($player->contract_until) {
            $yearsLeft = $player->game->current_date->diffInYears($player->contract_until);
            if ($yearsLeft >= 4) {
                $disposition -= 0.10;
            } elseif ($yearsLeft <= 1) {
                $disposition += 0.15;
            }
        } else {
            $disposition += 0.20; // No contract = very willing
        }

        // Transfer listed = very willing
        if ($player->transfer_status === 'listed') {
            $disposition += 0.20;
        }

        // Age (older = more willing to sell)
        $age = $player->age($player->game->current_date);
        if ($age >= PlayerAge::PRIME_END) {
            $disposition += 0.10;
        } elseif ($age < PlayerAge::YOUNG_END) {
            $disposition -= 0.05;
        }

        // Squad composition: position surplus + ability gap
        $disposition += $this->calculateSquadFitFactor($player);

        return max(0.10, min(0.95, $disposition));
    }

    /**
     * Calculate how expendable a player is based on squad composition.
     *
     * Returns a modifier: positive = more expendable, negative = harder to sell.
     * Considers position group depth and ability relative to group average.
     */
    private function calculateSquadFitFactor(GamePlayer $player): float
    {
        if (!$player->team_id) {
            return 0.0;
        }

        $group = $player->position_group;

        // Load teammates in the same position group
        $groupPlayers = GamePlayer::where('game_id', $player->game_id)
            ->where('team_id', $player->team_id)
            ->get()
            ->filter(fn (GamePlayer $p) => $p->position_group === $group);

        $groupCount = $groupPlayers->count();
        $idealCount = self::IDEAL_GROUP_COUNTS[$group] ?? 4;
        $factor = 0.0;

        // Position surplus: above ideal depth → more willing to sell
        $surplus = $groupCount - $idealCount;
        if ($surplus > 0) {
            $factor += min(0.10, $surplus * 0.05);
        } elseif ($surplus < 0) {
            // Below ideal depth → reluctant to sell
            $factor -= 0.10;
        }

        // Ability gap: below group average → more expendable
        if ($groupPlayers->count() > 1) {
            $playerAbility = ($player->current_technical_ability + $player->current_physical_ability) / 2;
            $groupAvg = $groupPlayers->avg(fn (GamePlayer $p) => ($p->current_technical_ability + $p->current_physical_ability) / 2);

            $gap = $groupAvg - $playerAbility;
            if ($gap > 10) {
                $factor += 0.10;
            } elseif ($gap > 5) {
                $factor += 0.05;
            } elseif ($gap < -10) {
                // Well above average — key to the group
                $factor -= 0.05;
            }
        }

        return $factor;
    }

    /**
     * Get mood indicator for sell disposition.
     *
     * @return array{label: string, color: string}
     */
    public function getMoodIndicator(float $disposition): array
    {
        if ($disposition >= 0.65) {
            return ['label' => __('transfers.mood_willing_sell'), 'color' => 'green'];
        }
        if ($disposition >= 0.40) {
            return ['label' => __('transfers.mood_open_sell'), 'color' => 'amber'];
        }

        return ['label' => __('transfers.mood_reluctant_sell'), 'color' => 'red'];
    }

    /**
     * Calculate a player's willingness to transfer, from the player's perspective.
     *
     * Builds on the club's sell disposition, then applies reputation gap
     * modifiers (players are reluctant to move down, eager to move up).
     *
     * @return array{score: int, label: string}
     */
    public function calculateWillingness(GamePlayer $player, Game $game, ScoutingService $scoutingService, ?float $importance = null): array
    {
        $importance ??= $scoutingService->calculatePlayerImportance($player);

        // Base willingness: low importance players are more willing
        $score = (int) ((1.0 - $importance) * 50);

        // Contract length factor: fewer years left = more willing
        if ($player->contract_until) {
            $yearsLeft = max(0, $game->current_date->diffInYears($player->contract_until));
            if ($yearsLeft <= 1) {
                $score += 30;
            } elseif ($yearsLeft <= 2) {
                $score += 15;
            }
        } else {
            $score += 25; // No contract = very willing
        }

        // Age factor
        $age = $player->age($game->current_date);
        if ($age >= PlayerAge::PRIME_END) {
            $score += 10;
        } elseif ($age < PlayerAge::YOUNG_END) {
            $score += 5; // Young players seeking opportunities
        }

        // Squad fit: surplus players are more willing to leave
        $squadFit = $this->calculateSquadFitFactor($player);
        $score += (int) ($squadFit * 100);

        // Reputation gap: penalize moving down, reward moving up
        $reputationModifier = $scoutingService->calculateReputationModifier($game->team, $player);
        if ($reputationModifier < 1.0) {
            $score = (int) ($score * $reputationModifier);
        } elseif ($player->team_id) {
            $sourceReputation = TeamReputation::resolveLevel($player->game_id, $player->team_id);
            $offeringReputation = TeamReputation::resolveLevel($player->game_id, $game->team_id);
            $sourceIndex = ClubProfile::getReputationTierIndex($sourceReputation);
            $offeringIndex = ClubProfile::getReputationTierIndex($offeringReputation);
            $upwardGap = $offeringIndex - $sourceIndex;

            if ($upwardGap >= 3) {
                $score += 30;
            } elseif ($upwardGap === 2) {
                $score += 20;
            } elseif ($upwardGap === 1) {
                $score += 10;
            }
        }

        $score = min(100, max(0, $score + rand(-5, 5)));

        $label = match (true) {
            $score >= 80 => 'very_interested',
            $score >= 60 => 'open',
            $score >= 40 => 'undecided',
            $score >= 20 => 'reluctant',
            default => 'not_interested',
        };

        return ['score' => $score, 'label' => $label];
    }
}
