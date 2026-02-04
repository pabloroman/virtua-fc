<?php

namespace App\Game\Services;

use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
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
     * Check if a player is eligible to play in a specific competition.
     */
    public function isEligible(GamePlayer $player, string $competitionId, ?Carbon $matchDate = null): bool
    {
        // Check competition-specific suspension
        if (PlayerSuspension::isSuspended($player->id, $competitionId)) {
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
    public function getIneligibilityReason(GamePlayer $player, string $competitionId, ?Carbon $matchDate = null): ?string
    {
        $matchesRemaining = PlayerSuspension::getMatchesRemaining($player->id, $competitionId);
        if ($matchesRemaining > 0) {
            return "Suspended ({$matchesRemaining} match" . ($matchesRemaining > 1 ? 'es' : '') . " remaining)";
        }

        if ($matchDate && $player->injury_until !== null && $player->injury_until->gt($matchDate)) {
            return "Injured until " . $player->injury_until->format('M j');
        }

        return null;
    }

    /**
     * Apply a suspension to a player for a specific competition.
     *
     * @param GamePlayer $player The player to suspend
     * @param int $matches Number of matches to suspend
     * @param string $competitionId The competition where the suspension applies
     */
    public function applySuspension(GamePlayer $player, int $matches, string $competitionId): void
    {
        PlayerSuspension::applySuspension($player->id, $competitionId, $matches);
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
     * @param GamePlayer $player The player who received the red card
     * @param bool $isSecondYellow Whether this was a second yellow card
     * @param string $competitionId The competition where the card was given
     */
    public function processRedCard(GamePlayer $player, bool $isSecondYellow, string $competitionId): void
    {
        // Second yellow = 1 match ban
        // Direct red = 1-3 match ban (default 1, could be extended for violent conduct)
        $matches = $isSecondYellow ? 1 : 1;

        $this->applySuspension($player, $matches, $competitionId);
    }

    /**
     * Serve a match for a player's suspension in a competition.
     * Called after a player misses a match due to suspension.
     *
     * @return bool True if the suspension is now cleared
     */
    public function serveSuspensionMatch(GamePlayer $player, string $competitionId): bool
    {
        $suspension = PlayerSuspension::forPlayerInCompetition($player->id, $competitionId);

        if ($suspension) {
            return $suspension->serveMatch();
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

    /**
     * Get all active suspensions for a player.
     *
     * @return array<string, int> Map of competition_id => matches_remaining
     */
    public function getActiveSuspensions(GamePlayer $player): array
    {
        return PlayerSuspension::where('game_player_id', $player->id)
            ->where('matches_remaining', '>', 0)
            ->pluck('matches_remaining', 'competition_id')
            ->toArray();
    }
}
