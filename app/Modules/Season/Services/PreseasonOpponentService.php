<?php

namespace App\Modules\Season\Services;

use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Match\Jobs\ProcessCareerActions;
use App\Support\CountryCodeMapper;
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
     * Candidate opponents the player can choose from: every team in this game
     * that has a generated squad — the same universe as the transfer market /
     * explore. transferMarketEligible() drops national, reserve, and cup-only
     * (squad-less) sides; the user's own teams are excluded so you can't play
     * yourself. Sorted by name for a stable picker.
     *
     * @return Collection<int, Team>
     */
    public function candidatePool(Game $game): Collection
    {
        $teamIds = CompetitionEntry::where('game_id', $game->id)
            ->distinct()
            ->pluck('team_id');

        return Team::transferMarketEligible()
            ->whereIn('id', $teamIds)
            ->whereNotIn('id', $game->userTeamIds())
            ->orderBy('name')
            ->get();
    }

    /**
     * The candidate pool grouped by country for the picker modal. Mirrors the
     * shape Explore's pool mode consumes:
     * {code, name, flag, teams: [{id, name, image}]}.
     *
     * @return Collection<int, array{code: string, name: string, flag: string, teams: array}>
     */
    public function candidateTeamsGroupedByCountry(Game $game): Collection
    {
        return $this->candidatePool($game)
            ->groupBy('country')
            ->map(function (Collection $teams, ?string $countryCode) {
                $code = strtolower((string) $countryCode);
                $englishName = CountryCodeMapper::toName((string) $countryCode) ?? $countryCode;

                return [
                    'code' => $code,
                    'name' => __("countries.{$englishName}"),
                    // The Team flag accessor handles the EN -> gb-eng case.
                    'flag' => $teams->first()->flag,
                    'teams' => $teams->map(fn (Team $team) => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'image' => $team->image,
                    ])->values()->all(),
                ];
            })
            ->sortBy('name')
            ->values();
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
     * and pool members, and enforce one fixture per slot (two friendlies can't
     * share a date). The same opponent may be picked in multiple slots.
     *
     * @param  array<int, array{slot?:mixed, team_id?:mixed, is_home?:mixed}>  $selections
     * @return array<int, array{slot:int, team_id:string, is_home:bool}>
     */
    private function sanitizeSelections(Game $game, array $selections): array
    {
        $validTeamIds = $this->candidatePool($game)->pluck('id')->flip();

        $clean = [];
        $usedSlots = [];

        foreach ($selections as $selection) {
            $slot = (int) ($selection['slot'] ?? -1);
            $teamId = $selection['team_id'] ?? null;

            if ($slot < 0 || $slot >= self::NUM_SLOTS) {
                continue;
            }
            if (! is_string($teamId) || ! $validTeamIds->has($teamId)) {
                continue;
            }
            if (isset($usedSlots[$slot])) {
                continue;
            }

            $usedSlots[$slot] = true;

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
