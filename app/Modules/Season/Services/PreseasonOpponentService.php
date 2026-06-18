<?php

namespace App\Modules\Season\Services;

use App\Models\ClubProfile;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Match\Jobs\ProcessCareerActions;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds the pool of candidate pre-season opponents and materialises the
 * friendlies the player chooses on the pre-season setup screen.
 *
 * Pre-season runs four fixed fixture slots (mid-July → mid-August). The player
 * picks an opponent and home/away for each slot, may leave slots empty, and may
 * pick none at all. Picking none is the deliberate replacement for the old
 * one-click "Skip Pre-Season".
 */
class PreseasonOpponentService
{
    public const PRESEASON_COMPETITION_ID = 'PRESEASON';

    public const NUM_SLOTS = 4;

    /** Largest candidate pool offered to the player in the picker. */
    private const POOL_SIZE = 24;

    /** Fixed fixture dates (day/month) for the four pre-season slots. */
    private const SCHEDULE = [
        ['day' => 12, 'month' => 7],
        ['day' => 22, 'month' => 7],
        ['day' => 2,  'month' => 8],
        ['day' => 10, 'month' => 8],
    ];

    /**
     * The four fixed fixture dates for the given game's current season.
     *
     * @return array<int, Carbon> keyed by slot index (0-3)
     */
    public function fixtureSlots(Game $game): array
    {
        $seasonYear = (int) $game->season;

        $slots = [];
        foreach (self::SCHEDULE as $i => $schedule) {
            $slots[$i] = Carbon::createFromDate($seasonYear, $schedule['month'], $schedule['day']);
        }

        return $slots;
    }

    /**
     * Candidate opponents the player can choose from.
     *
     * Foreign clubs of similar reputation (±1 tier), excluding cup-only and
     * national sides. Primera RFEF sides (ESP3A/ESP3B) wouldn't realistically
     * tour against foreign clubs, so they instead draw from the domestic
     * Segunda (ESP2) pool. Sorted by name for a stable picker.
     *
     * @return Collection<int, Team>
     */
    public function candidatePool(Game $game): Collection
    {
        if (in_array($game->competition_id, ['ESP3A', 'ESP3B'], true)) {
            $pool = $this->segundaPool($game);
        } else {
            $pool = $this->foreignPool($game);
        }

        return $pool->sortBy('name')->values();
    }

    /**
     * @return Collection<int, Team>
     */
    private function foreignPool(Game $game): Collection
    {
        $userProfile = ClubProfile::where('team_id', $game->team_id)->first();
        $userTierIndex = $userProfile
            ? ClubProfile::getReputationTierIndex($userProfile->reputation_level)
            : 3;

        // Reputation levels within ±1 tier of the user's club.
        $tiers = ClubProfile::REPUTATION_TIERS;
        $validLevels = [];
        for ($i = max(0, $userTierIndex - 1); $i <= min(count($tiers) - 1, $userTierIndex + 1); $i++) {
            $validLevels[] = $tiers[$i];
        }

        $userCountry = $game->country ?? 'ES';

        // transferMarketEligible also excludes cup-only teams (e.g. Segunda
        // Federación regional sides loaded from data/<year>/ESPCUP) which have a
        // ClubProfile but no generated squad.
        return Team::transferMarketEligible()
            ->where('country', '!=', $userCountry)
            ->whereHas('clubProfile', function ($query) use ($validLevels) {
                $query->whereIn('reputation_level', $validLevels);
            })
            ->inRandomOrder()
            ->limit(self::POOL_SIZE)
            ->get();
    }

    /**
     * @return Collection<int, Team>
     */
    private function segundaPool(Game $game): Collection
    {
        $segundaTeamIds = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', 'ESP2')
            ->where('team_id', '!=', $game->team_id)
            ->pluck('team_id');

        return Team::transferMarketEligible()
            ->whereIn('id', $segundaTeamIds)
            ->inRandomOrder()
            ->limit(self::POOL_SIZE)
            ->get();
    }

    /**
     * Materialise the chosen friendlies and end pre-season setup.
     *
     * @param  array<int, array{slot:int, team_id:string, is_home:bool}>  $selections
     */
    public function confirmSelections(Game $game, array $selections): void
    {
        if (! $game->needsPreseasonOpponentSelection()) {
            return;
        }

        $selections = $this->sanitizeSelections($game, $selections);
        $slots = $this->fixtureSlots($game);

        $createdDates = [];
        foreach ($selections as $selection) {
            $slot = $selection['slot'];
            $date = $slots[$slot];

            GameMatch::create([
                'id' => Str::uuid()->toString(),
                'game_id' => $game->id,
                'competition_id' => self::PRESEASON_COMPETITION_ID,
                'home_team_id' => $selection['is_home'] ? $game->team_id : $selection['team_id'],
                'away_team_id' => $selection['is_home'] ? $selection['team_id'] : $game->team_id,
                'scheduled_date' => $date->toDateString(),
                'round_number' => $slot + 1,
                'played' => false,
            ]);

            $createdDates[] = $date;
        }

        if ($createdDates !== []) {
            // At least one friendly: park the game on the earliest one. Playing
            // the friendlies advances dates (and closes the transfer window)
            // naturally, exactly as before this feature existed.
            $earliest = collect($createdDates)->min();
            $game->update([
                'current_date' => $earliest->toDateString(),
                'preseason_opponents_pending' => false,
            ]);

            return;
        }

        // No friendlies chosen — the deliberate replacement for the old skip.
        $this->skipPreSeason($game);
    }

    /**
     * Validate and normalise raw selections: drop blanks, clamp to valid slots
     * and pool members, and enforce uniqueness of slots and teams.
     *
     * @param  array<int, array{slot?:mixed, team_id?:mixed, is_home?:mixed}>  $selections
     * @return array<int, array{slot:int, team_id:string, is_home:bool}>
     */
    private function sanitizeSelections(Game $game, array $selections): array
    {
        $validTeamIds = $this->candidatePool($game)->pluck('id')->flip();

        $clean = [];
        $usedSlots = [];
        $usedTeams = [];

        foreach ($selections as $selection) {
            $slot = (int) ($selection['slot'] ?? -1);
            $teamId = $selection['team_id'] ?? null;

            if ($slot < 0 || $slot >= self::NUM_SLOTS) {
                continue;
            }
            if (! is_string($teamId) || ! $validTeamIds->has($teamId)) {
                continue;
            }
            if (isset($usedSlots[$slot]) || isset($usedTeams[$teamId])) {
                continue;
            }

            $usedSlots[$slot] = true;
            $usedTeams[$teamId] = true;

            $clean[] = [
                'slot' => $slot,
                'team_id' => $teamId,
                'is_home' => (bool) ($selection['is_home'] ?? true),
            ];
        }

        return $clean;
    }

    /**
     * End pre-season with no friendlies: jump to the first competitive match,
     * flush summer signings / close the window, and simulate AI window activity.
     * This is the deliberate replacement for the old one-click "Skip Pre-Season".
     */
    private function skipPreSeason(Game $game): void
    {
        $earliestMatch = GameMatch::where('game_id', $game->id)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->first();

        $previousDate = $game->current_date;
        $updates = [
            'pre_season' => false,
            'preseason_opponents_pending' => false,
        ];
        if ($earliestMatch) {
            $updates['current_date'] = $earliestMatch->scheduled_date->toDateString();
        }

        $game->update($updates);

        // Notify listeners that the date jumped from pre-season to matchday 1.
        // This lets the transfer subsystem flush summer signings (parked as
        // STATUS_AGREED) and lets the AI window-close handler run before the
        // first competitive match.
        if ($earliestMatch && $earliestMatch->scheduled_date->gt($previousDate)) {
            GameDateAdvanced::dispatch($game->refresh(), $previousDate, $earliestMatch->scheduled_date);
        }

        // Run career action ticks in the background to simulate pre-season transfer activity
        $updated = Game::where('id', $game->id)
            ->whereNull('career_actions_processing_at')
            ->update(['career_actions_processing_at' => now()]);

        if ($updated) {
            try {
                ProcessCareerActions::dispatch($game->id, 4);
            } catch (\Throwable $e) {
                Game::where('id', $game->id)->update(['career_actions_processing_at' => null]);
                Log::error('Failed to dispatch pre-season career actions', [
                    'game_id' => $game->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
