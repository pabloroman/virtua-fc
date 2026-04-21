<?php

namespace App\Modules\Season\Processors;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\Log;

/**
 * Writes the UEFA Super Cup CompetitionEntry rows for the new season.
 * Mirrors the role of SupercupQualificationProcessor for ESPSUP: the
 * actual match is drawn afterwards by ContinentalAndCupInitProcessor →
 * SeasonInitializationService::conductCupDraws → CupDrawService.
 *
 * Finalists are the previous season's Champions League and Europa
 * League winners, captured by SeasonArchiveProcessor during the closing
 * pipeline as META_UCL_WINNER / META_UEL_WINNER. On the initial season
 * there's no prior metadata; the entries copied from data/2025/UEFASUP/
 * teams.json by SetupNewGame::copyCompetitionTeamsToGame are the
 * finalists (PSG and Tottenham, the real 2024/25 winners), so this
 * processor is a no-op in that case — same seed-data-first pattern used
 * by every other cup.
 *
 * Priority 85: runs next to SupercupQualificationProcessor (80) and well
 * before ContinentalAndCupInitProcessor (106) so the entries are in place
 * by the time the draw runs.
 */
class UefaSuperCupQualificationProcessor implements SeasonProcessor
{
    public const COMPETITION_ID = 'UEFASUP';

    public function priority(): int
    {
        return 85;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (!Competition::where('id', self::COMPETITION_ID)->exists()) {
            return $data;
        }

        // Initial season: seed data was already copied into
        // competition_entries — leave it alone, same as every other cup.
        if ($data->isInitialSeason) {
            return $data;
        }

        $uclWinnerId = $data->getMetadata(SeasonTransitionData::META_UCL_WINNER);
        $uelWinnerId = $data->getMetadata(SeasonTransitionData::META_UEL_WINNER);

        // Always clear last season's finalists before re-qualifying. If
        // metadata is incomplete (rare — e.g. a partial rollover spanning
        // a deploy) we skip the fixture for this year rather than keep
        // stale entries around.
        CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', self::COMPETITION_ID)
            ->delete();

        if (!$uclWinnerId || !$uelWinnerId || $uclWinnerId === $uelWinnerId) {
            Log::warning('[UefaSuperCup] Could not resolve both finalists — skipping', [
                'game_id' => $game->id,
                'season' => $data->newSeason,
                'ucl_winner_id' => $uclWinnerId,
                'uel_winner_id' => $uelWinnerId,
            ]);

            return $data;
        }

        $this->writeEntries($game->id, [$uclWinnerId, $uelWinnerId]);

        return $data;
    }

    /**
     * Bulk-insert the two qualifiers at entry_round = 1. Mirrors
     * SupercupQualificationProcessor::updateSupercupTeams.
     *
     * @param  array<int, string>  $teamIds
     */
    private function writeEntries(string $gameId, array $teamIds): void
    {
        CompetitionEntry::insert(array_map(fn (string $teamId) => [
            'game_id' => $gameId,
            'competition_id' => self::COMPETITION_ID,
            'team_id' => $teamId,
            'entry_round' => 1,
        ], $teamIds));
    }
}
