<?php

namespace App\Modules\Season\Processors;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\Team;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\Log;

/**
 * Writes the UEFA Super Cup CompetitionEntry rows for the new season.
 * Mirrors the role of SupercupQualificationProcessor for ESPSUP: the
 * actual match is drawn afterwards by ContinentalAndCupInitProcessor →
 * SeasonInitializationService::conductCupDraws → CupDrawService.
 *
 * The finalists are the previous season's Champions League and Europa
 * League winners, captured by SeasonArchiveProcessor during the closing
 * pipeline as META_UCL_WINNER / META_UEL_WINNER. On the initial season
 * we fall back to the real 2024/25 winners: PSG (UCL) and Tottenham (UEL).
 *
 * Priority 85: runs next to SupercupQualificationProcessor (80) and well
 * before ContinentalAndCupInitProcessor (106) so the entries are in place
 * by the time the draw runs.
 */
class UefaSuperCupQualificationProcessor implements SeasonProcessor
{
    public const COMPETITION_ID = 'UEFASUP';

    // Real 2024/25 UCL and UEL winners — used as seeds on the initial season.
    private const INITIAL_UCL_WINNER_TRANSFERMARKT_ID = 583;   // Paris Saint-Germain
    private const INITIAL_UEL_WINNER_TRANSFERMARKT_ID = 148;   // Tottenham Hotspur

    public function priority(): int
    {
        return 85;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (!Competition::where('id', self::COMPETITION_ID)->exists()) {
            return $data;
        }

        [$uclWinnerId, $uelWinnerId] = $this->resolveFinalists($data);

        if (!$uclWinnerId || !$uelWinnerId || $uclWinnerId === $uelWinnerId) {
            Log::warning('[UefaSuperCup] Could not resolve both finalists — skipping', [
                'game_id' => $game->id,
                'season' => $data->newSeason,
                'is_initial_season' => $data->isInitialSeason,
                'ucl_winner_id' => $uclWinnerId,
                'uel_winner_id' => $uelWinnerId,
            ]);

            return $data;
        }

        $this->writeEntries($game->id, [$uclWinnerId, $uelWinnerId]);

        return $data;
    }

    /**
     * Resolve the two finalists from season transition metadata. On the
     * initial season there's no prior competition to capture winners from,
     * so we fall back to the real 2024/25 winners. On later seasons we
     * trust the metadata; if it's missing (e.g. a partial rollover that
     * straddled an upgrade of this code), we skip the fixture for that
     * year rather than seed an incorrect pairing.
     *
     * @return array{0: ?string, 1: ?string} [uclWinnerId, uelWinnerId]
     */
    private function resolveFinalists(SeasonTransitionData $data): array
    {
        $uclWinnerId = $data->getMetadata(SeasonTransitionData::META_UCL_WINNER);
        $uelWinnerId = $data->getMetadata(SeasonTransitionData::META_UEL_WINNER);

        if ($data->isInitialSeason) {
            $uclWinnerId ??= Team::where('transfermarkt_id', self::INITIAL_UCL_WINNER_TRANSFERMARKT_ID)->value('id');
            $uelWinnerId ??= Team::where('transfermarkt_id', self::INITIAL_UEL_WINNER_TRANSFERMARKT_ID)->value('id');
        }

        return [$uclWinnerId, $uelWinnerId];
    }

    /**
     * Rewrite this game's CompetitionEntry rows for UEFASUP. Mirrors the
     * pattern used by SupercupQualificationProcessor: hard-delete prior
     * finalists, bulk-insert the new pair at entry_round = 1.
     *
     * @param  array<int, string>  $teamIds
     */
    private function writeEntries(string $gameId, array $teamIds): void
    {
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', self::COMPETITION_ID)
            ->delete();

        CompetitionEntry::insert(array_map(fn (string $teamId) => [
            'game_id' => $gameId,
            'competition_id' => self::COMPETITION_ID,
            'team_id' => $teamId,
            'entry_round' => 1,
        ], $teamIds));
    }
}
