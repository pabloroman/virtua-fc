<?php

namespace App\Modules\Manager\Processors;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\ManagerJobHistory;
use App\Models\ManagerJobOffer;
use App\Models\ManagerStats;
use App\Models\Team;
use App\Modules\Manager\Services\JobOfferService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\TeamReputationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            Log::warning('[ApplyPendingTeamSwitch] aborting: offer missing or not accepted', [
                'game_id' => $game->id,
                'pending_team_switch' => $game->pending_team_switch,
                'offer_status' => $offer?->status,
            ]);
            $game->update(['pending_team_switch' => null]);
            return $data;
        }

        $newTeam = Team::find($offer->team_id);
        if (!$newTeam) {
            Log::warning('[ApplyPendingTeamSwitch] aborting: offer team not found', [
                'game_id' => $game->id,
                'offer_id' => $offer->id,
                'team_id' => $offer->team_id,
            ]);
            $game->update(['pending_team_switch' => null]);
            return $data;
        }

        // Resolve from per-game competition_entries first. The offer was
        // stamped before the closing pipeline ran, so its competition_id is
        // stale whenever PromotionRelegationProcessor moved the destination
        // team between leagues during closing — picking the offer's value
        // strands the user in a league their new club isn't entered in,
        // and SyntheticLeagueResolver silently resolves the team's real
        // league as a "non-user" league at season-close (Copa del Rey is
        // the only thing left to play).
        $newCompetitionId = $this->resolveCurrentLeagueForTeam($game->id, $offer->team_id)
            ?? $offer->competition_id
            ?? $this->jobOfferService->resolveAcceptedCompetitionId($offer->team_id);

        if (!$newCompetitionId) {
            Log::warning('[ApplyPendingTeamSwitch] aborting: could not resolve destination competition', [
                'game_id' => $game->id,
                'offer_id' => $offer->id,
                'team_id' => $offer->team_id,
                'offer_competition_id' => $offer->competition_id,
            ]);
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

            // Keep the leaderboard aggregate pointing at the manager's current
            // club. No-op if the row doesn't exist yet (UpdateManagerStats will
            // create it against $game->team_id on the next finalized match).
            ManagerStats::where('game_id', $game->id)
                ->update(['team_id' => $newTeam->id]);
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

    /**
     * Return the league competition the team is currently entered in for this
     * game. Reads competition_entries (tenant) and the cached set of league
     * competition_ids (control) separately to avoid a planes-seam JOIN.
     */
    private function resolveCurrentLeagueForTeam(string $gameId, string $teamId): ?string
    {
        $leagueIds = Competition::where('role', Competition::ROLE_LEAGUE)->pluck('id')->all();
        if ($leagueIds === []) {
            return null;
        }

        return CompetitionEntry::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->whereIn('competition_id', $leagueIds)
            ->value('competition_id');
    }
}
