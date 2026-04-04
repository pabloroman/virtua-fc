<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Player\PlayerAge;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SquadNumberService
{
    private const FIRST_TEAM_MAX = 25;

    /**
     * Traditional football numbering conventions (Spanish/La Liga style).
     *
     * Each position maps to an ordered list of preferred numbers.
     * The first available number in the list is assigned.
     */
    private const POSITION_NUMBERS = [
        'Goalkeeper'         => [1, 13, 25],
        'Right-Back'         => [2, 12, 22],
        'Left-Back'          => [3, 18, 24],
        'Centre-Back'        => [4, 5, 15, 16, 23],
        'Defensive Midfield' => [6, 14, 20],
        'Central Midfield'   => [8, 6, 14, 22],
        'Attacking Midfield' => [10, 8, 21],
        'Right Midfield'     => [7, 14, 22],
        'Left Midfield'      => [11, 17, 24],
        'Right Winger'       => [7, 11, 17],
        'Left Winger'        => [11, 7, 17],
        'Centre-Forward'     => [9, 19, 21],
        'Second Striker'     => [10, 9, 19, 21],
    ];

    /**
     * Assign a smart squad number for a player joining the user's team.
     *
     * Uses traditional football numbering conventions by position.
     * Over-23 players get slots 1-25 (bumping the youngest under-23 if needed).
     * Under-23 players get slots 1-25 if available, otherwise 26-99.
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
            // Under-23: try preferred numbers in 1-25, then any 1-25, then 26-99
            return $this->preferredNumber($player->position, $takenNumbers, 1, self::FIRST_TEAM_MAX)
                ?? $this->firstAvailable(1, self::FIRST_TEAM_MAX, $takenNumbers)
                ?? $this->firstAvailable(self::FIRST_TEAM_MAX + 1, 99, $takenNumbers);
        }

        // Over-23: try preferred numbers, then any free 1-25
        $number = $this->preferredNumber($player->position, $takenNumbers, 1, self::FIRST_TEAM_MAX)
            ?? $this->firstAvailable(1, self::FIRST_TEAM_MAX, $takenNumbers);

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
     * Uses traditional football numbering conventions when assigning new numbers.
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
        $over23InFirstTeam = collect();
        $under23InFirstTeam = collect();
        $over23NeedSlot = collect();
        $under23NeedSlot = collect();
        $under23InAcademy = collect();

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
                $under23NeedSlot->push($player);
            }
        }

        $over23NeedingCount = $over23NeedSlot->count();

        if ($over23NeedingCount === 0 && $under23NeedSlot->isEmpty()) {
            return 0;
        }

        // Calculate available first-team slots
        $freeFirstTeamSlots = self::FIRST_TEAM_MAX - $over23InFirstTeam->count() - $under23InFirstTeam->count();

        // How many under-23 need to be bumped to make room for over-23?
        $bumpCount = max(0, $over23NeedingCount - $freeFirstTeamSlots);

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

        // Collect free academy slots
        $allAcademyNumbers = collect(range(self::FIRST_TEAM_MAX + 1, 99));
        $freeAcademy = $allAcademyNumbers
            ->reject(fn ($n) => $stableNumbers->has($n))
            ->values();

        $updates = [];
        $academyIdx = 0;

        // Track which first-team numbers are taken (stable + newly assigned)
        $usedFirstTeam = $stableNumbers->filter(fn ($v, $n) => $n >= 1 && $n <= self::FIRST_TEAM_MAX);

        // Assign over-23 to first-team slots using position preferences
        $resolvableOver23 = $over23NeedSlot->take($over23NeedingCount - $unresolvable);
        foreach ($resolvableOver23 as $player) {
            $number = $this->preferredNumber($player->position, $usedFirstTeam, 1, self::FIRST_TEAM_MAX)
                ?? $this->firstAvailable(1, self::FIRST_TEAM_MAX, $usedFirstTeam);

            if ($number !== null) {
                $updates[$player->id] = $number;
                $usedFirstTeam->put($number, true);
            } elseif ($player->number !== null) {
                $updates[$player->id] = null;
            }
        }

        // Safety net: any over-23 with an existing academy number who didn't get
        // a first-team slot must have their old number cleared to prevent collisions
        foreach ($resolvableOver23 as $player) {
            if ($player->number !== null && ! array_key_exists($player->id, $updates)) {
                $updates[$player->id] = null;
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

        // Unregistered under-23: try first-team with position prefs, then academy
        foreach ($under23NeedSlot as $player) {
            $number = $this->preferredNumber($player->position, $usedFirstTeam, 1, self::FIRST_TEAM_MAX)
                ?? $this->firstAvailable(1, self::FIRST_TEAM_MAX, $usedFirstTeam);

            if ($number !== null) {
                $updates[$player->id] = $number;
                $usedFirstTeam->put($number, true);
            } elseif ($academyIdx < $freeAcademy->count()) {
                $updates[$player->id] = $freeAcademy[$academyIdx++];
            }
        }

        if (! empty($updates)) {
            DB::transaction(function () use ($updates, $game) {
                // Clear numbers first to avoid unique constraint violations
                // when swapping numbers between players
                GamePlayer::where('game_id', $game->id)
                    ->whereIn('id', array_keys($updates))
                    ->update(['number' => null]);

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
     * Find the first preferred number for a position that is available.
     * Only returns numbers within the given range.
     */
    private function preferredNumber(string $position, Collection $taken, int $min, int $max): ?int
    {
        $preferred = self::POSITION_NUMBERS[$position] ?? [];

        foreach ($preferred as $number) {
            if ($number >= $min && $number <= $max && ! $taken->has($number)) {
                return $number;
            }
        }

        return null;
    }

    private function firstAvailable(int $from, int $to, Collection $taken): ?int
    {
        for ($n = $from; $n <= $to; $n++) {
            if (! $taken->has($n)) {
                return $n;
            }
        }

        return null;
    }
}
