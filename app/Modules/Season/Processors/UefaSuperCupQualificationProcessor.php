<?php

namespace App\Modules\Season\Processors;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\Team;
use App\Modules\Competition\Services\FinalVenueResolver;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Carbon\Carbon;

/**
 * Schedules the UEFA Super Cup: a single-leg final on August 13 at a
 * neutral European venue between the previous season's Champions League
 * winner and Europa League winner.
 *
 * Winner metadata is captured by SeasonArchiveProcessor during the
 * closing pipeline. For the initial season (no prior results), we fall
 * back to the 2024/25 real-world winners: PSG (UCL) vs Tottenham (UEL).
 *
 * Priority: 107 (runs after ContinentalAndCupInitProcessor at 106 and
 * before PreSeasonFixtureProcessor at 108).
 */
class UefaSuperCupQualificationProcessor implements SeasonProcessor
{
    public const COMPETITION_ID = 'UEFASUP';
    public const MATCH_MONTH = 8;
    public const MATCH_DAY = 13;

    // Real-world 2024/25 UCL and UEL winners used as seeds for the very first season.
    private const INITIAL_UCL_WINNER_TRANSFERMARKT_ID = 583;   // Paris Saint-Germain
    private const INITIAL_UEL_WINNER_TRANSFERMARKT_ID = 148;   // Tottenham Hotspur

    public function __construct(
        private FinalVenueResolver $venueResolver,
    ) {}

    public function priority(): int
    {
        return 107;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        if (!Competition::where('id', self::COMPETITION_ID)->exists()) {
            return $data;
        }

        [$homeTeamId, $awayTeamId] = $this->resolveFinalists($data);

        if (!$homeTeamId || !$awayTeamId || $homeTeamId === $awayTeamId) {
            return $data;
        }

        // Idempotency: if a UEFASUP match already exists for this game/season,
        // the processor already ran (e.g. crash-recovery checkpoint replay).
        $seasonYear = (int) $data->newSeason;
        $matchDate = Carbon::createFromDate($seasonYear, self::MATCH_MONTH, self::MATCH_DAY);

        $existing = GameMatch::where('game_id', $game->id)
            ->where('competition_id', self::COMPETITION_ID)
            ->whereDate('scheduled_date', $matchDate->toDateString())
            ->first();

        if ($existing) {
            return $data;
        }

        // Clear prior-season finalists. UEFASUP uses country='EU' and is not
        // touched by UefaQualificationProcessor's country-scoped cleanup, so
        // it's this processor's responsibility to wipe last year's entries
        // before seeding the new pair.
        CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', self::COMPETITION_ID)
            ->delete();

        // Participants for this season's tie
        foreach ([$homeTeamId, $awayTeamId] as $teamId) {
            CompetitionEntry::create([
                'game_id' => $game->id,
                'competition_id' => self::COMPETITION_ID,
                'team_id' => $teamId,
                'entry_round' => 1,
            ]);
        }

        $venue = $this->venueResolver->resolve(self::COMPETITION_ID, $homeTeamId, $awayTeamId);

        $match = GameMatch::create([
            'game_id' => $game->id,
            'competition_id' => self::COMPETITION_ID,
            'round_number' => 1,
            'round_name' => 'cup.final',
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'scheduled_date' => $matchDate->toDateString(),
            'played' => false,
            'neutral_venue_name' => $venue['name'] ?? null,
            'neutral_venue_capacity' => $venue['capacity'] ?? null,
        ]);

        $cupTie = CupTie::create([
            'game_id' => $game->id,
            'competition_id' => self::COMPETITION_ID,
            'round_number' => 1,
            'bracket_position' => 1,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'first_leg_match_id' => $match->id,
            'completed' => false,
        ]);

        $match->update(['cup_tie_id' => $cupTie->id]);

        return $data;
    }

    /**
     * Resolve the two finalists from season transition metadata, falling back
     * to the initial-season seeds when no prior winner was captured.
     *
     * @return array{0: ?string, 1: ?string} [homeTeamId (UCL winner), awayTeamId (UEL winner)]
     */
    private function resolveFinalists(SeasonTransitionData $data): array
    {
        $uclWinnerId = $data->getMetadata(SeasonTransitionData::META_UCL_WINNER);
        $uelWinnerId = $data->getMetadata(SeasonTransitionData::META_UEL_WINNER);

        if (!$uclWinnerId) {
            $uclWinnerId = Team::where('transfermarkt_id', self::INITIAL_UCL_WINNER_TRANSFERMARKT_ID)->value('id');
        }

        if (!$uelWinnerId) {
            $uelWinnerId = Team::where('transfermarkt_id', self::INITIAL_UEL_WINNER_TRANSFERMARKT_ID)->value('id');
        }

        return [$uclWinnerId, $uelWinnerId];
    }
}
