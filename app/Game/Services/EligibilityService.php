<?php

namespace App\Game\Services;

use App\Models\GamePlayer;
use Carbon\Carbon;

class EligibilityService
{
    // Yellow card accumulation thresholds (La Liga rules)
    private const YELLOW_CARD_THRESHOLDS = [
        5 => 1,   // 5 yellows = 1 match ban
        10 => 2,  // 10 yellows = 2 match ban
        15 => 3,  // 15 yellows = 3 match ban
    ];

    /**
     * Check if a player is eligible to play in a given matchday.
     */
    public function isEligible(GamePlayer $player, int $matchday, ?Carbon $matchDate = null): bool
    {
        // Check suspension
        if ($player->suspended_until_matchday !== null && $player->suspended_until_matchday > $matchday) {
            return false;
        }

        // Check injury
        if ($matchDate && $player->injury_until !== null && $player->injury_until->gt($matchDate)) {
            return false;
        }

        return true;
    }

    /**
     * Get the reason why a player is ineligible.
     */
    public function getIneligibilityReason(GamePlayer $player, int $matchday, ?Carbon $matchDate = null): ?string
    {
        if ($player->suspended_until_matchday !== null && $player->suspended_until_matchday > $matchday) {
            $matchesRemaining = $player->suspended_until_matchday - $matchday;
            return "Suspended ({$matchesRemaining} match" . ($matchesRemaining > 1 ? 'es' : '') . " remaining)";
        }

        if ($matchDate && $player->injury_until !== null && $player->injury_until->gt($matchDate)) {
            return "Injured until " . $player->injury_until->format('M j');
        }

        return null;
    }

    /**
     * Apply a suspension to a player.
     *
     * @param int $matches Number of matches to suspend
     * @param int $currentMatchday The matchday when the suspension is given
     */
    public function applySuspension(GamePlayer $player, int $matches, int $currentMatchday): void
    {
        // Suspension starts after current matchday
        $player->suspended_until_matchday = $currentMatchday + $matches + 1;
        $player->save();
    }

    /**
     * Apply an injury to a player.
     *
     * @param string $injuryType Description of the injury
     * @param int $weeksOut Number of weeks the player will be out
     * @param Carbon $matchDate The date of the match when injury occurred
     */
    public function applyInjury(GamePlayer $player, string $injuryType, int $weeksOut, Carbon $matchDate): void
    {
        $player->injury_type = $injuryType;
        $player->injury_until = $matchDate->copy()->addWeeks($weeksOut);
        $player->save();
    }

    /**
     * Check if a player has crossed a yellow card threshold and should be suspended.
     * Returns the number of matches to suspend, or null if no suspension.
     */
    public function checkYellowCardAccumulation(GamePlayer $player): ?int
    {
        $yellowCards = $player->yellow_cards;

        // Check thresholds in descending order
        foreach (array_reverse(self::YELLOW_CARD_THRESHOLDS, true) as $threshold => $matches) {
            if ($yellowCards === $threshold) {
                return $matches;
            }
        }

        return null;
    }

    /**
     * Process a red card and apply appropriate suspension.
     *
     * @param bool $isSecondYellow Whether this was a second yellow card
     * @param int $currentMatchday The matchday when the red card was given
     */
    public function processRedCard(GamePlayer $player, bool $isSecondYellow, int $currentMatchday): void
    {
        // Second yellow = 1 match ban
        // Direct red = 1-3 match ban (default 1, could be extended for violent conduct)
        $matches = $isSecondYellow ? 1 : 1;

        $this->applySuspension($player, $matches, $currentMatchday);
    }

    /**
     * Clear a player's suspension if they have served it.
     */
    public function clearSuspensionIfServed(GamePlayer $player, int $currentMatchday): bool
    {
        if ($player->suspended_until_matchday !== null && $player->suspended_until_matchday <= $currentMatchday) {
            $player->suspended_until_matchday = null;
            $player->save();
            return true;
        }

        return false;
    }

    /**
     * Clear a player's injury if they have recovered.
     */
    public function clearInjuryIfRecovered(GamePlayer $player, Carbon $currentDate): bool
    {
        if ($player->injury_until !== null && $player->injury_until->lte($currentDate)) {
            $player->injury_type = null;
            $player->injury_until = null;
            $player->save();
            return true;
        }

        return false;
    }
}
