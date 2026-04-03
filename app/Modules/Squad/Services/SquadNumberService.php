<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Player\PlayerAge;
use Illuminate\Support\Facades\DB;

class SquadNumberService
{
    private const FIRST_TEAM_MAX = 25;

    /**
     * Assign a smart squad number for a player joining the user's team.
     *
     * Over-23 players get slots 2-25 (bumping the youngest under-23 if needed).
     * Under-23 players get slots 2-25 if available, otherwise 26-99.
     * Returns null only when there are already 25+ over-23 players (unresolvable).
     */
    public function assignNumberForNewPlayer(Game $game, GamePlayer $player): ?int
    {
        $age = $player->age($game->current_date);
        $isYoung = $age <= PlayerAge::YOUNG_END;

        $teamPlayers = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('id', '!=', $player->id)
            ->whereNotNull('number')
            ->with('player')
            ->get();

        $takenNumbers = $teamPlayers->pluck('number')->flip();

        if ($isYoung) {
            // Under-23: try 2-25 first, then 26-99
            $number = $this->firstAvailable(2, self::FIRST_TEAM_MAX, $takenNumbers);

            return $number ?? $this->firstAvailable(self::FIRST_TEAM_MAX + 1, 99, $takenNumbers);
        }

        // Over-23: must be in 2-25
        $number = $this->firstAvailable(2, self::FIRST_TEAM_MAX, $takenNumbers);

        if ($number !== null) {
            return $number;
        }

        // 1-25 full — find youngest under-23 in 1-25 to bump to 26+
        $bumpCandidate = $teamPlayers
            ->filter(fn ($p) => $p->number >= 1 && $p->number <= self::FIRST_TEAM_MAX)
            ->filter(fn ($p) => $p->age($game->current_date) <= PlayerAge::YOUNG_END)
            ->sortBy(fn ($p) => $p->age($game->current_date))
            ->first();

        if (! $bumpCandidate) {
            // All 25 slots occupied by over-23 players — unresolvable
            return null;
        }

        $academySlot = $this->firstAvailable(self::FIRST_TEAM_MAX + 1, 99, $takenNumbers);
        $freedSlot = $bumpCandidate->number;

        $bumpCandidate->update(['number' => $academySlot]);

        return $freedSlot;
    }

    /**
     * Bulk reassign squad numbers for the user's team.
     *
     * Preserves existing numbers where valid. Only moves players when necessary:
     * - Over-23 in academy slots (26+) → moved to freed 1-25 slots
     * - Under-23 in 1-25 → bumped to 26+ only if an over-23 needs the slot
     * - Unregistered under-23 → assigned 26+ slots
     * - Unregistered over-23 → assigned 1-25 if possible
     *
     * Returns the count of over-23 players left without a number (unresolvable).
     */
    public function reassignNumbers(Game $game): int
    {
        $players = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->with('player')
            ->get();

        if ($players->isEmpty()) {
            return 0;
        }

        $currentDate = $game->current_date;

        // Categorize players by age and current number position
        $over23InFirstTeam = collect();   // valid, keep
        $under23InFirstTeam = collect();  // valid, but bumpable
        $over23NeedSlot = collect();      // in 26+ or null, need 1-25
        $under23NeedSlot = collect();     // in 26+ already (valid) or null (need any slot)
        $under23InAcademy = collect();    // in 26+, valid, keep

        foreach ($players as $player) {
            $age = $player->age($currentDate);
            $isYoung = $age <= PlayerAge::YOUNG_END;
            $number = $player->number;
            $inFirstTeam = $number !== null && $number >= 1 && $number <= self::FIRST_TEAM_MAX;
            $inAcademy = $number !== null && $number > self::FIRST_TEAM_MAX;

            if (! $isYoung && $inFirstTeam) {
                $over23InFirstTeam->push($player);
            } elseif ($isYoung && $inFirstTeam) {
                $under23InFirstTeam->push($player);
            } elseif (! $isYoung) {
                $over23NeedSlot->push($player);
            } elseif ($inAcademy) {
                $under23InAcademy->push($player);
            } else {
                // under-23 with null number
                $under23NeedSlot->push($player);
            }
        }

        $over23NeedingCount = $over23NeedSlot->count();

        if ($over23NeedingCount === 0 && $under23NeedSlot->isEmpty()) {
            // Everyone is already in a valid position
            return 0;
        }

        // Calculate available first-team slots
        $freeFirstTeamSlots = self::FIRST_TEAM_MAX - $over23InFirstTeam->count() - $under23InFirstTeam->count();

        // How many under-23 need to be bumped to make room for over-23?
        $bumpCount = max(0, $over23NeedingCount - $freeFirstTeamSlots);

        // Can we resolve all over-23? Check if total over-23 exceeds 25
        $totalOver23 = $over23InFirstTeam->count() + $over23NeedingCount;
        $unresolvable = max(0, $totalOver23 - self::FIRST_TEAM_MAX);

        // Determine which under-23 to bump (youngest first)
        $toBump = $under23InFirstTeam
            ->sortBy(fn ($p) => $p->age($currentDate))
            ->take($bumpCount);

        // Build the set of all taken numbers that won't change
        $stableNumbers = collect()
            ->merge($over23InFirstTeam->pluck('number'))
            ->merge($under23InFirstTeam->reject(fn ($p) => $toBump->contains('id', $p->id))->pluck('number'))
            ->merge($under23InAcademy->pluck('number'))
            ->flip();

        // Collect freed first-team slots (from bumped under-23 + already-free slots)
        $allFirstTeamNumbers = collect(range(2, self::FIRST_TEAM_MAX));
        $freeFirstTeam = $allFirstTeamNumbers
            ->reject(fn ($n) => $stableNumbers->has($n))
            ->values();

        // Collect free academy slots
        $allAcademyNumbers = collect(range(self::FIRST_TEAM_MAX + 1, 99));
        $freeAcademy = $allAcademyNumbers
            ->reject(fn ($n) => $stableNumbers->has($n))
            ->values();

        $updates = [];
        $firstTeamIdx = 0;
        $academyIdx = 0;

        // Assign over-23 to first-team slots (up to 25)
        $resolvableOver23 = $over23NeedSlot->take($over23NeedingCount - $unresolvable);
        foreach ($resolvableOver23 as $player) {
            if ($firstTeamIdx < $freeFirstTeam->count()) {
                $updates[$player->id] = $freeFirstTeam[$firstTeamIdx++];
            }
        }

        // Unresolvable over-23 get null
        $unresolvableOver23 = $over23NeedSlot->skip($over23NeedingCount - $unresolvable);
        foreach ($unresolvableOver23 as $player) {
            if ($player->number !== null) {
                $updates[$player->id] = null;
            }
        }

        // Bumped under-23 go to academy slots
        foreach ($toBump as $player) {
            if ($academyIdx < $freeAcademy->count()) {
                $updates[$player->id] = $freeAcademy[$academyIdx++];
            }
        }

        // Unregistered under-23 get academy slots (or first-team if available)
        foreach ($under23NeedSlot as $player) {
            if ($firstTeamIdx < $freeFirstTeam->count()) {
                $updates[$player->id] = $freeFirstTeam[$firstTeamIdx++];
            } elseif ($academyIdx < $freeAcademy->count()) {
                $updates[$player->id] = $freeAcademy[$academyIdx++];
            }
        }

        // Apply updates in a transaction
        if (! empty($updates)) {
            DB::transaction(function () use ($updates, $game) {
                foreach ($updates as $playerId => $number) {
                    GamePlayer::where('id', $playerId)
                        ->where('game_id', $game->id)
                        ->update(['number' => $number]);
                }
            });
        }

        return $unresolvable;
    }

    /**
     * Find the next available squad number for AI teams (2-99).
     * Always returns a number — AI teams must never have unregistered players.
     */
    public function nextAvailableNumberForAI(string $gameId, string $teamId): int
    {
        $taken = GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->whereNotNull('number')
            ->pluck('number')
            ->all();

        for ($n = 2; $n <= 99; $n++) {
            if (! in_array($n, $taken)) {
                return $n;
            }
        }

        return 99;
    }

    private function firstAvailable(int $from, int $to, \Illuminate\Support\Collection $taken): ?int
    {
        for ($n = $from; $n <= $to; $n++) {
            if (! $taken->has($n)) {
                return $n;
            }
        }

        return null;
    }
}
