<?php

namespace App\Modules\Squad\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Player\PlayerAge;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SquadRegistrationService
{
    // Minimum registered players by position group
    public const MIN_REGISTERED_GK = 2;
    public const MIN_REGISTERED_DEF = 5;
    public const MIN_REGISTERED_MID = 5;
    public const MIN_REGISTERED_FWD = 3;
    public const MIN_REGISTERED_TOTAL = 18;

    /**
     * Get full registration status for the user's team.
     *
     * @return array{registered: Collection, unregistered: Collection, academyRegistered: Collection, standardCount: int, slotsRemaining: int}
     */
    public function getRegistrationStatus(Game $game): array
    {
        $players = GamePlayer::with('player')
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->get();

        $registered = $players->filter(fn (GamePlayer $p) => $p->isStandardRegistered())
            ->sortBy('number')
            ->values();

        $academyRegistered = $players->filter(fn (GamePlayer $p) => $p->isAcademyRegistered())
            ->sortBy('number')
            ->values();

        $unregistered = $players->filter(fn (GamePlayer $p) => !$p->isRegistered())
            ->sortByDesc('overall_score')
            ->values();

        $standardCount = $registered->count();

        return [
            'registered' => $registered,
            'unregistered' => $unregistered,
            'academyRegistered' => $academyRegistered,
            'standardCount' => $standardCount,
            'slotsRemaining' => GamePlayer::MAX_REGISTERED_STANDARD - $standardCount,
        ];
    }

    /**
     * Count standard registered players (1–25) for the user's team.
     */
    public function standardRegisteredCount(Game $game): int
    {
        return GamePlayer::standardRegisteredCount($game->id, $game->team_id);
    }

    /**
     * Check if there are available standard registration slots.
     */
    public function canRegisterMore(Game $game): bool
    {
        return $this->standardRegisteredCount($game) < GamePlayer::MAX_REGISTERED_STANDARD;
    }

    /**
     * Bulk register players with specific number assignments.
     * Validates constraints: max 25 standard slots, unique numbers, position minimums.
     *
     * @param array<string, int> $assignments Player ID => squad number
     * @return array{success: bool, error?: string}
     */
    public function registerPlayers(Game $game, array $assignments): array
    {
        $teamId = $game->team_id;
        $gameId = $game->id;

        // Load all team players
        $players = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get()
            ->keyBy('id');

        // Validate all player IDs belong to this team
        foreach ($assignments as $playerId => $number) {
            if (!$players->has($playerId)) {
                return ['success' => false, 'error' => __('messages.registration_invalid_player')];
            }
        }

        // Separate standard (1–25) and academy (26+) assignments
        $standardAssignments = [];
        $academyAssignments = [];
        $usedNumbers = [];

        foreach ($assignments as $playerId => $number) {
            $number = (int) $number;

            if ($number < 1 || $number > 99) {
                return ['success' => false, 'error' => __('messages.registration_invalid_number')];
            }

            if (in_array($number, $usedNumbers)) {
                return ['success' => false, 'error' => __('messages.registration_duplicate_number', ['number' => $number])];
            }

            $usedNumbers[] = $number;

            if ($number <= GamePlayer::MAX_REGISTERED_STANDARD) {
                $standardAssignments[$playerId] = $number;
            } else {
                $academyAssignments[$playerId] = $number;
            }
        }

        // Validate standard slot count
        if (count($standardAssignments) > GamePlayer::MAX_REGISTERED_STANDARD) {
            return ['success' => false, 'error' => __('messages.registration_too_many', ['max' => GamePlayer::MAX_REGISTERED_STANDARD])];
        }

        // Validate minimum total
        if (count($assignments) < self::MIN_REGISTERED_TOTAL) {
            return ['success' => false, 'error' => __('messages.registration_too_few', ['min' => self::MIN_REGISTERED_TOTAL])];
        }

        // Validate academy age eligibility for numbers 26+
        foreach ($academyAssignments as $playerId => $number) {
            $player = $players->get($playerId);
            $age = $player->age($game->current_date);
            if ($age > GamePlayer::MAX_ACADEMY_AGE) {
                return ['success' => false, 'error' => __('messages.registration_academy_age', [
                    'name' => $player->name,
                    'age' => $age,
                ])];
            }
        }

        // Validate position group minimums among all registered
        $positionCounts = ['Goalkeeper' => 0, 'Defender' => 0, 'Midfielder' => 0, 'Forward' => 0];
        foreach ($assignments as $playerId => $number) {
            $group = $players->get($playerId)->position_group;
            $positionCounts[$group] = ($positionCounts[$group] ?? 0) + 1;
        }

        if ($positionCounts['Goalkeeper'] < self::MIN_REGISTERED_GK) {
            return ['success' => false, 'error' => __('messages.registration_min_position', [
                'position' => __('squad.goalkeepers'),
                'min' => self::MIN_REGISTERED_GK,
            ])];
        }
        if ($positionCounts['Defender'] < self::MIN_REGISTERED_DEF) {
            return ['success' => false, 'error' => __('messages.registration_min_position', [
                'position' => __('squad.defenders'),
                'min' => self::MIN_REGISTERED_DEF,
            ])];
        }
        if ($positionCounts['Midfielder'] < self::MIN_REGISTERED_MID) {
            return ['success' => false, 'error' => __('messages.registration_min_position', [
                'position' => __('squad.midfielders'),
                'min' => self::MIN_REGISTERED_MID,
            ])];
        }
        if ($positionCounts['Forward'] < self::MIN_REGISTERED_FWD) {
            return ['success' => false, 'error' => __('messages.registration_min_position', [
                'position' => __('squad.forwards'),
                'min' => self::MIN_REGISTERED_FWD,
            ])];
        }

        // Clear all existing numbers for this team
        GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->update(['number' => null]);

        // Assign new numbers
        foreach ($assignments as $playerId => $number) {
            GamePlayer::where('id', $playerId)->update(['number' => (int) $number]);
        }

        // Clear pending action if it exists
        if ($game->hasPendingAction('squad_registration')) {
            $game->removePendingAction('squad_registration');
        }

        return ['success' => true];
    }

    /**
     * Auto-assign registration for a team (used for AI teams and "auto-fill" button).
     * Picks the best players by position balance and overall rating.
     */
    public function autoAssignRegistration(Game $game, ?string $teamId = null, ?Carbon $referenceDate = null): void
    {
        $teamId = $teamId ?? $game->team_id;
        $referenceDate = $referenceDate ?? $game->current_date;
        $gameId = $game->id;

        $players = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get();

        if ($players->isEmpty()) {
            return;
        }

        // Clear all current numbers
        GamePlayer::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->update(['number' => null]);

        // Group by position
        $grouped = $players->groupBy('position_group');
        $gk = ($grouped->get('Goalkeeper') ?? collect())->sortByDesc('overall_score')->values();
        $def = ($grouped->get('Defender') ?? collect())->sortByDesc('overall_score')->values();
        $mid = ($grouped->get('Midfielder') ?? collect())->sortByDesc('overall_score')->values();
        $fwd = ($grouped->get('Forward') ?? collect())->sortByDesc('overall_score')->values();

        // Pick minimum per position, then fill remaining slots with best available
        $selected = collect();
        $selected = $selected->merge($gk->take(self::MIN_REGISTERED_GK));
        $selected = $selected->merge($def->take(self::MIN_REGISTERED_DEF));
        $selected = $selected->merge($mid->take(self::MIN_REGISTERED_MID));
        $selected = $selected->merge($fwd->take(self::MIN_REGISTERED_FWD));

        $remaining = $players->diff($selected)->sortByDesc('overall_score');
        $slotsLeft = GamePlayer::MAX_REGISTERED_STANDARD - $selected->count();

        // Separate academy-eligible youth from the remaining
        $academyEligible = collect();
        $standardCandidates = collect();

        foreach ($remaining as $player) {
            $age = $player->age($referenceDate);
            if ($age <= GamePlayer::MAX_ACADEMY_AGE) {
                $academyEligible->push($player);
            } else {
                $standardCandidates->push($player);
            }
        }

        // Fill remaining standard slots with best available (prefer older players for standard slots)
        $selected = $selected->merge($standardCandidates->take($slotsLeft));
        $slotsLeft = GamePlayer::MAX_REGISTERED_STANDARD - $selected->count();

        // If still slots left, add academy-eligible as standard
        if ($slotsLeft > 0) {
            $toStandard = $academyEligible->take($slotsLeft);
            $selected = $selected->merge($toStandard);
            $academyEligible = $academyEligible->diff($toStandard);
        }

        // Assign standard numbers (1–25)
        $number = 1;
        $taken = [];
        foreach ($selected as $player) {
            while (in_array($number, $taken) || $number > GamePlayer::MAX_REGISTERED_STANDARD) {
                $number++;
            }
            GamePlayer::where('id', $player->id)->update(['number' => $number]);
            $taken[] = $number;
            $number++;
        }

        // Register remaining academy-eligible youth with numbers 26+
        $academyNumber = GamePlayer::ACADEMY_NUMBER_START;
        foreach ($academyEligible as $player) {
            GamePlayer::where('id', $player->id)->update(['number' => $academyNumber]);
            $academyNumber++;
        }
    }

    /**
     * Auto-register a new signing if standard slots are available.
     * Returns the assigned number or null if no slot available.
     */
    public function registerNewSigning(Game $game, GamePlayer $player): ?int
    {
        $number = GamePlayer::nextAvailableStandardNumber($game->id, $game->team_id);

        if ($number !== null) {
            $player->update(['number' => $number]);
        }

        return $number;
    }

    /**
     * Register an academy promotion with a number 26+ (if under 23).
     * Falls back to a standard slot if over the academy age limit.
     */
    public function registerAcademyPromotion(Game $game, GamePlayer $player): ?int
    {
        $age = $player->age($game->current_date);

        if ($age <= GamePlayer::MAX_ACADEMY_AGE) {
            $number = GamePlayer::nextAvailableAcademyNumber($game->id, $game->team_id);
            $player->update(['number' => $number]);
            return $number;
        }

        // Over academy age — needs a standard slot
        return $this->registerNewSigning($game, $player);
    }

    /**
     * Build auto-assignment suggestions for the registration UI.
     * Returns an array of player_id => suggested_number.
     *
     * @return array<string, int>
     */
    public function buildAutoAssignSuggestions(Game $game): array
    {
        $teamId = $game->team_id;
        $gameId = $game->id;
        $referenceDate = $game->current_date;

        $players = GamePlayer::with('player')
            ->where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->get();

        $grouped = $players->groupBy('position_group');
        $gk = ($grouped->get('Goalkeeper') ?? collect())->sortByDesc('overall_score')->values();
        $def = ($grouped->get('Defender') ?? collect())->sortByDesc('overall_score')->values();
        $mid = ($grouped->get('Midfielder') ?? collect())->sortByDesc('overall_score')->values();
        $fwd = ($grouped->get('Forward') ?? collect())->sortByDesc('overall_score')->values();

        // Pick minimum per position, then fill remaining
        $selected = collect();
        $selected = $selected->merge($gk->take(self::MIN_REGISTERED_GK));
        $selected = $selected->merge($def->take(self::MIN_REGISTERED_DEF));
        $selected = $selected->merge($mid->take(self::MIN_REGISTERED_MID));
        $selected = $selected->merge($fwd->take(self::MIN_REGISTERED_FWD));

        $remaining = $players->diff($selected)->sortByDesc('overall_score');
        $slotsLeft = GamePlayer::MAX_REGISTERED_STANDARD - $selected->count();

        $academyEligible = collect();
        $standardCandidates = collect();

        foreach ($remaining as $player) {
            if ($player->age($referenceDate) <= GamePlayer::MAX_ACADEMY_AGE) {
                $academyEligible->push($player);
            } else {
                $standardCandidates->push($player);
            }
        }

        $selected = $selected->merge($standardCandidates->take($slotsLeft));
        $slotsLeft = GamePlayer::MAX_REGISTERED_STANDARD - $selected->count();

        if ($slotsLeft > 0) {
            $toStandard = $academyEligible->take($slotsLeft);
            $selected = $selected->merge($toStandard);
            $academyEligible = $academyEligible->diff($toStandard);
        }

        $suggestions = [];
        $number = 1;

        // Assign GK first (number 1 for best GK, then 13 for backup)
        $gkNumbers = [1, 13];
        $gkIdx = 0;
        foreach ($selected->filter(fn ($p) => $p->position_group === 'Goalkeeper') as $player) {
            $suggestions[$player->id] = $gkNumbers[$gkIdx] ?? ++$number;
            $gkIdx++;
        }

        // Assign outfield: DEF 2–6, MID 7–11, FWD starting from remaining
        $takenNumbers = array_values($suggestions);
        $positionRanges = [
            'Defender' => range(2, 6),
            'Midfielder' => range(6, 12),
            'Forward' => range(7, 11),
        ];

        foreach (['Defender', 'Midfielder', 'Forward'] as $group) {
            foreach ($selected->filter(fn ($p) => $p->position_group === $group) as $player) {
                // Find first available number
                $assigned = false;
                foreach ($positionRanges[$group] as $preferredNum) {
                    if (!in_array($preferredNum, $takenNumbers)) {
                        $suggestions[$player->id] = $preferredNum;
                        $takenNumbers[] = $preferredNum;
                        $assigned = true;
                        break;
                    }
                }
                if (!$assigned) {
                    // Fallback: next available 1–25
                    for ($n = 1; $n <= GamePlayer::MAX_REGISTERED_STANDARD; $n++) {
                        if (!in_array($n, $takenNumbers)) {
                            $suggestions[$player->id] = $n;
                            $takenNumbers[] = $n;
                            break;
                        }
                    }
                }
            }
        }

        // Academy-eligible youth get 26+
        $academyNumber = GamePlayer::ACADEMY_NUMBER_START;
        foreach ($academyEligible as $player) {
            $suggestions[$player->id] = $academyNumber;
            $academyNumber++;
        }

        return $suggestions;
    }
}
