<?php

namespace App\Modules\Manager\Processors;

use App\Models\Game;
use App\Models\ManagerJobHistory;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\TeamReputationSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Apply a pro-manager team switch the user accepted at the previous
 * season-end screen. Runs at the top of SeasonSetupPipeline (priority 0)
 * so that downstream processors — fixtures, standings, cups, budget —
 * see the new team, competition, and country.
 *
 * For cross-country switches the destination country's TeamReputation rows
 * are seeded on demand via TeamReputationSeeder (idempotent).
 */
class ApplyPendingTeamSwitchProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly JobOfferService $jobOfferService,
        private readonly TeamReputationSeeder $teamReputationSeeder,
    ) {}

    public function priority(): int
    {
        return 0;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (!$game->isProManagerMode() || !$game->pending_team_switch) {
            return $data;
        }

        $offer = ManagerJobOffer::find($game->pending_team_switch);
        if (!$offer || $offer->status !== ManagerJobOffer::STATUS_ACCEPTED) {
            // Stale or invalid pointer — clear it so the user isn't stuck.
            $game->update(['pending_team_switch' => null]);
            return $data;
        }

        $newTeam = Team::find($offer->team_id);
        if (!$newTeam) {
            $game->update(['pending_team_switch' => null]);
            return $data;
        }

        $newCompetitionId = $offer->competition_id
            ?? $this->jobOfferService->resolveAcceptedCompetitionId($offer->team_id);

        if (!$newCompetitionId) {
            // Should never happen for reference-seeded clubs, but bail
            // rather than land the user in an unconfigured league.
            $game->update(['pending_team_switch' => null]);
            return $data;
        }

        $newCountry = $newTeam->country;
        $newSeason = $data->newSeason;
        $oldSeason = $data->oldSeason;

        $wasFired = $offer->offer_type === ManagerJobOffer::TYPE_POST_FIRING;

        DB::transaction(function () use ($game, $newTeam, $newCompetitionId, $newCountry, $newSeason, $oldSeason, $wasFired) {
            $this->closeOutgoingTenure($game, $oldSeason, $wasFired);
            $this->openNewTenure($game, $newTeam->id, $newCompetitionId, $newSeason);

            if ($newCountry !== $game->country) {
                $this->teamReputationSeeder->seedForCountry($game->id, $newCountry);
            }

            // Pro-manager carries the manager, not the club, across seasons:
            // wipe per-club state that wouldn't make sense at the new club
            // (reserve_team_id, season_goal — the latter is re-derived by
            // the standings/budget processors based on the new club's tier).
            $newReserveTeamId = $newTeam->parent_team_id === null
                ? $newTeam->reserveTeam?->id
                : null;

            $game->update([
                'team_id' => $newTeam->id,
                'reserve_team_id' => $newReserveTeamId,
                'competition_id' => $newCompetitionId,
                'country' => $newCountry,
                'pending_team_switch' => null,
                'season_goal' => null,
            ]);
        });

        // Mutate the transition DTO so LeagueFixtureProcessor and the rest
        // of the setup pipeline regenerate against the new club's league.
        $data->competitionId = $newCompetitionId;

        return $data;
    }

    private function closeOutgoingTenure(Game $game, string $oldSeason, bool $wasFired): void
    {
        $tenure = ManagerJobHistory::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->whereNull('season_end')
            ->orderByDesc('season_start')
            ->first();

        $endReason = $wasFired
            ? ManagerJobHistory::REASON_FIRED
            : ManagerJobHistory::REASON_LEFT_VOLUNTARILY;

        if (!$tenure) {
            // Backfill an end-of-tenure row for games that pre-date the
            // history table or that started before this processor existed.
            ManagerJobHistory::create([
                'game_id' => $game->id,
                'user_id' => $game->user_id,
                'team_id' => $game->team_id,
                'competition_id' => $game->competition_id,
                'season_start' => $oldSeason,
                'season_end' => $oldSeason,
                'end_reason' => $endReason,
            ]);
            return;
        }

        $tenure->update([
            'season_end' => $oldSeason,
            'end_reason' => $endReason,
        ]);
    }

    private function openNewTenure(Game $game, string $teamId, string $competitionId, ?string $newSeason): void
    {
        ManagerJobHistory::create([
            'game_id' => $game->id,
            'user_id' => $game->user_id,
            'team_id' => $teamId,
            'competition_id' => $competitionId,
            'season_start' => $newSeason ?? $game->season,
            'season_end' => null,
            'end_reason' => ManagerJobHistory::REASON_STILL_ACTIVE,
        ]);
    }
}
