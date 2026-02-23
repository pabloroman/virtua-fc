<?php

namespace App\Modules\Squad\Services;

use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Modules\Squad\DTOs\SuspensionRuleSet;
use Carbon\Carbon;

class EligibilityService
{
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
        $player->injury_until = \Illuminate\Support\Carbon::instance($matchDate->copy()->addWeeks($weeksOut));
        $player->save();
    }

    /**
     * Clear a player's injury after recovery.
     */
    public function clearInjury(GamePlayer $player): void
    {
        $player->injury_until = null;
        $player->injury_type = null;
        $player->save();
    }

    /**
     * Record a yellow card and check if it triggers a suspension.
     * Tracks the yellow card on the per-competition counter and applies
     * the suspension if the accumulation threshold is reached.
     *
     * @return int|null Number of matches banned, or null if no suspension
     */
    public function processYellowCard(string $gamePlayerId, string $competitionId, string $handlerType = 'league'): ?int
    {
        $competitionYellows = PlayerSuspension::recordYellowCard($gamePlayerId, $competitionId);
        $banLength = $this->checkYellowCardAccumulation($competitionYellows, $handlerType);

        if ($banLength) {
            PlayerSuspension::applySuspension($gamePlayerId, $competitionId, $banLength);
        }

        return $banLength;
    }

    /**
     * Check if a yellow card count triggers a suspension.
     * Returns the number of matches to suspend, or null if no suspension.
     */
    public function checkYellowCardAccumulation(int $competitionYellowCards, string $handlerType = 'league'): ?int
    {
        $rules = $this->rulesForHandlerType($handlerType);

        return $rules->checkAccumulation($competitionYellowCards);
    }

    /**
     * Resolve the suspension rule set for a given competition handler type.
     */
    public function rulesForHandlerType(string $handlerType): SuspensionRuleSet
    {
        return match ($handlerType) {
            'knockout_cup' => SuspensionRuleSet::copaDelRey(),
            'group_stage_cup' => SuspensionRuleSet::worldCup(),
            'swiss_format' => SuspensionRuleSet::uefaClub(),
            default => SuspensionRuleSet::default(),
        };
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
     * Reset yellow card accumulation counters for all players in a competition.
     * Only resets the per-competition counter, not the visible season stat.
     */
    public function resetYellowCardsForCompetition(string $gameId, string $competitionId): void
    {
        PlayerSuspension::where('competition_id', $competitionId)
            ->whereHas('gamePlayer', fn ($q) => $q->where('game_id', $gameId))
            ->where('yellow_cards', '>', 0)
            ->update(['yellow_cards' => 0]);
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
}
