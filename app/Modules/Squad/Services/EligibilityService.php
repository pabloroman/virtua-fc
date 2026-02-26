<?php

namespace App\Modules\Squad\Services;

use App\Models\GamePlayer;
use App\Models\PlayerSuspension;
use App\Modules\Squad\DTOs\SuspensionRuleSet;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
     * Process all card events (yellows and reds) in batch.
     * Loads/creates suspension records in bulk, aggregates yellow card increments,
     * checks accumulation thresholds, and applies suspensions — all in ~4-5 queries
     * regardless of how many cards were given.
     *
     * @param  array  $cardEvents  [{game_player_id, competitionId, event_type, metadata}]
     * @param  Collection  $competitions  keyed by ID
     * @return array  [suspensions => [{game_player_id, competition_id, ban_length, reason}]]
     */
    public function batchProcessCards(array $cardEvents, Collection $competitions): array
    {
        if (empty($cardEvents)) {
            return ['suspensions' => []];
        }

        // Normalize camelCase caller key to snake_case for internal use
        $normalized = array_map(function ($event) {
            $event['competition_id'] = $event['competitionId'];

            return $event;
        }, $cardEvents);

        // 1. Collect unique (game_player_id, competition_id) pairs
        $pairs = [];
        foreach ($normalized as $event) {
            $key = $event['game_player_id'] . '|' . $event['competition_id'];
            $pairs[$key] = [
                'game_player_id' => $event['game_player_id'],
                'competition_id' => $event['competition_id'],
            ];
        }

        // 2. Load all existing PlayerSuspension records for these pairs in ONE query
        $existingRecords = PlayerSuspension::where(function ($query) use ($pairs) {
            foreach ($pairs as $pair) {
                $query->orWhere(function ($q) use ($pair) {
                    $q->where('game_player_id', $pair['game_player_id'])
                        ->where('competition_id', $pair['competition_id']);
                });
            }
        })->get();

        // Index existing records by composite key
        $recordsByKey = [];
        foreach ($existingRecords as $record) {
            $key = $record->game_player_id . '|' . $record->competition_id;
            $recordsByKey[$key] = $record;
        }

        // 3. Batch-INSERT missing records
        $missingRows = [];
        foreach ($pairs as $key => $pair) {
            if (! isset($recordsByKey[$key])) {
                $newId = Str::uuid()->toString();
                $missingRows[] = [
                    'id' => $newId,
                    'game_player_id' => $pair['game_player_id'],
                    'competition_id' => $pair['competition_id'],
                    'yellow_cards' => 0,
                    'matches_remaining' => 0,
                ];
                // Create an in-memory model so we can track it
                $record = new PlayerSuspension();
                $record->id = $newId;
                $record->game_player_id = $pair['game_player_id'];
                $record->competition_id = $pair['competition_id'];
                $record->yellow_cards = 0;
                $record->matches_remaining = 0;
                $record->exists = true;
                $recordsByKey[$key] = $record;
            }
        }

        if (! empty($missingRows)) {
            PlayerSuspension::insert($missingRows);
        }

        // 4. Aggregate yellow card increments per (player, competition) in memory
        $yellowIncrements = []; // [composite_key => increment_count]
        $redCardEvents = [];

        foreach ($normalized as $event) {
            $key = $event['game_player_id'] . '|' . $event['competition_id'];

            if ($event['event_type'] === 'yellow_card') {
                $yellowIncrements[$key] = ($yellowIncrements[$key] ?? 0) + 1;
            } elseif ($event['event_type'] === 'red_card') {
                $redCardEvents[] = $event;
            }
        }

        // 5. Batch UPDATE yellow cards using CASE WHEN
        if (! empty($yellowIncrements)) {
            $incrementsByRecordId = [];
            foreach ($yellowIncrements as $key => $amount) {
                $record = $recordsByKey[$key];
                $incrementsByRecordId[$record->id] = $amount;
                // Update in-memory value for threshold checking
                $record->yellow_cards += $amount;
            }
            PlayerSuspension::batchRecordYellowCards($incrementsByRecordId);
        }

        // 6. Check accumulation thresholds in memory
        $suspensionResults = [];
        $suspensionsByRecordId = [];

        foreach ($yellowIncrements as $key => $amount) {
            $record = $recordsByKey[$key];
            $competitionId = $record->competition_id;
            $competition = $competitions->get($competitionId);
            $handlerType = $competition->handler_type ?? 'league';
            $rules = $this->rulesForHandlerType($handlerType);

            $banLength = $rules->checkAccumulation($record->yellow_cards);
            if ($banLength) {
                $suspensionsByRecordId[$record->id] = $banLength;
                $suspensionResults[] = [
                    'game_player_id' => $record->game_player_id,
                    'competition_id' => $competitionId,
                    'ban_length' => $banLength,
                    'reason' => 'yellow_accumulation',
                ];
            }
        }

        // 7. Collect red card suspensions
        foreach ($redCardEvents as $event) {
            $key = $event['game_player_id'] . '|' . $event['competition_id'];
            $record = $recordsByKey[$key];
            $isSecondYellow = $event['metadata']['second_yellow'] ?? false;
            $banLength = $isSecondYellow ? 1 : 1;

            $suspensionsByRecordId[$record->id] = $banLength;
            $suspensionResults[] = [
                'game_player_id' => $event['game_player_id'],
                'competition_id' => $event['competition_id'],
                'ban_length' => $banLength,
                'reason' => 'red_card',
            ];
        }

        // 8. Batch UPSERT suspensions
        if (! empty($suspensionsByRecordId)) {
            PlayerSuspension::batchApplySuspensions($suspensionsByRecordId);
        }

        return ['suspensions' => $suspensionResults];
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
