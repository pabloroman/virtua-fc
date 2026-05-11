<?php

namespace App\Modules\Season\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\TeamReputation;
use App\Modules\Stadium\Services\FanLoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed per-game TeamReputation rows for every team registered in a country's
 * competitions. Used by SetupNewGame for the initial-country and by the
 * pro-manager ApplyPendingTeamSwitchProcessor when a manager moves to a
 * country whose teams have not yet been seeded.
 *
 * The seeder is idempotent at the (game_id, country) grain — teams already
 * carrying a TeamReputation row for the given game are left untouched.
 */
class TeamReputationSeeder
{
    public function __construct(
        private readonly FanLoyaltyService $fanLoyaltyService,
    ) {}

    /**
     * Seed reputations for every team registered in $countryCode's
     * competitions inside the given game.
     */
    public function seedForCountry(string $gameId, string $countryCode): void
    {
        $countryCompetitionIds = Competition::where('country', $countryCode)->pluck('id');

        $entries = CompetitionEntry::where('game_id', $gameId)
            ->whereIn('competition_id', $countryCompetitionIds)
            ->get();

        $teamIds = $entries->pluck('team_id')->unique();

        if ($teamIds->isEmpty()) {
            return;
        }

        // Skip teams that already have a TeamReputation row for this game;
        // makes a partial cross-country seed (e.g. after a crashed switch)
        // safe to re-run.
        $alreadySeeded = TeamReputation::where('game_id', $gameId)
            ->whereIn('team_id', $teamIds)
            ->pluck('team_id')
            ->all();

        $teamIds = $teamIds->reject(fn ($id) => in_array($id, $alreadySeeded, true))->values();

        if ($teamIds->isEmpty()) {
            return;
        }

        $clubProfileRows = ClubProfile::whereIn('team_id', $teamIds)
            ->get(['team_id', 'reputation_level', 'fan_loyalty'])
            ->keyBy('team_id');

        // Build a map of team_id => lowest competition tier (1 = top division)
        $competitionTiers = Competition::whereIn('id', $entries->pluck('competition_id')->unique())
            ->pluck('tier', 'id');

        $teamCompetitionTier = [];
        foreach ($entries as $entry) {
            $tier = $competitionTiers[$entry->competition_id] ?? 99;
            if (!isset($teamCompetitionTier[$entry->team_id]) || $tier < $teamCompetitionTier[$entry->team_id]) {
                $teamCompetitionTier[$entry->team_id] = $tier;
            }
        }

        $divisionBonus = (int) config('reputation.division_bonus', 25);

        $rows = [];
        foreach ($teamIds as $teamId) {
            $profile = $clubProfileRows[$teamId] ?? null;
            $level = $profile->reputation_level ?? ClubProfile::REPUTATION_LOCAL;
            $curatedLoyalty = $profile?->fan_loyalty;
            $points = TeamReputation::pointsForTier($level);

            // Apply division bonus for Modest/Local teams in tier 1
            $competitionTier = $teamCompetitionTier[$teamId] ?? 99;
            if ($competitionTier === 1 && in_array($level, [ClubProfile::REPUTATION_MODEST, ClubProfile::REPUTATION_LOCAL])) {
                $points += $divisionBonus;
            }

            // base_loyalty captures cultural identity (never moves);
            // loyalty_points starts equal and drifts from that anchor.
            $seededLoyalty = $this->fanLoyaltyService->seedInitialValue(
                $curatedLoyalty !== null ? (int) $curatedLoyalty : null,
            );

            $rows[] = [
                'id' => Str::uuid()->toString(),
                'game_id' => $gameId,
                'team_id' => $teamId,
                'reputation_level' => $level,
                'base_reputation_level' => $level,
                'reputation_points' => $points,
                'base_loyalty' => $seededLoyalty,
                'loyalty_points' => $seededLoyalty,
            ];
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 100) as $chunk) {
                TeamReputation::insert($chunk);
            }
        });
    }
}
