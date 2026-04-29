<?php

namespace App\Modules\Season\Processors;

use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Competition\Services\CountryConfig;
use App\Models\CupTie;
use App\Models\Game;
use App\Models\CompetitionEntry;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use RuntimeException;

/**
 * Determines supercup qualifiers for the next season, driven by country config.
 *
 * Qualification rules (RFEF for ESP, generalised for any country):
 *  1. The two cup finalists always qualify (cup winner + cup runner-up).
 *  2. The league champion qualifies, unless already in slot 1–2.
 *  3. The league runner-up qualifies, unless already in slot 1–3.
 *  4. If either cup finalist also finished league 1st or 2nd, the *league*
 *     slot they would have taken cascades down to 3rd / 4th to fill the
 *     four-team field. The cup-finalist slot itself is never displaced.
 *
 * RFEF wording (Copa del Rey → Supercopa de España):
 *   "Si el campeón o subcampeón de Copa del Rey ya está clasificado entre
 *    los dos primeros de Liga, la plaza de la Supercopa se otorga al 3º
 *    (y 4º si fuera necesario) clasificado de la Liga para completar los
 *    cuatro participantes."
 *
 * The "plaza" (spot) that advances is the league's, not the cup's — the
 * cup finalists keep priority and the league simply fills whatever
 * remains. Implementing this priority order also matters for downstream
 * display: the persisted entry order labels each slot's role.
 *
 * Priority: 25 (runs after stats reset but before fixture generation)
 */
class SupercupQualificationProcessor implements SeasonProcessor
{
    public function __construct(
        private CountryConfig $countryConfig,
    ) {}

    public function priority(): int
    {
        return 80;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $countryCode = $game->country ?? 'ES';
        $supercupConfig = $this->countryConfig->supercup($countryCode);

        if (!$supercupConfig) {
            return $data;
        }

        $this->processCountrySupercup($game, $data, $supercupConfig);

        return $data;
    }

    private function processCountrySupercup(Game $game, SeasonTransitionData $data, array $config): void
    {
        $cupId = $config['cup'];
        $leagueId = $config['league'];
        $supercupId = $config['competition'];
        $cupFinalRound = $config['cup_final_round'];

        // Skip when this country isn't part of the game (no top-league
        // entries). The 4-qualifier guard below catches partial-data bugs
        // — wholly absent data is a different signal and shouldn't trip it.
        $hasLeague = CompetitionEntry::where('game_id', $game->id)
            ->where('competition_id', $leagueId)
            ->exists();
        if (!$hasLeague) {
            return;
        }

        // Get cup finalists
        $cupFinalists = $this->getCupFinalists($game->id, $cupId, $cupFinalRound);

        // Fetch league top 4 — enough to backfill 3rd/4th slots when both
        // cup finalists overlap with the league champion/runner-up.
        $leagueTopTeams = $this->getLeagueTopTeams($game->id, $leagueId, 4);

        // Determine the 4 supercup qualifiers
        $qualifiers = $this->determineQualifiers($cupFinalists, $leagueTopTeams);

        if (count($qualifiers) !== 4) {
            // Source of Population A in the cup-draw incident: cup didn't
            // run AND fewer than 4 league top teams are available, so the
            // supercup ends up with < 4 entries and the downstream draw
            // creates the wrong bracket. Surface this loudly — silent
            // shortfalls are how the bug stayed hidden in the first place.
            throw new RuntimeException(sprintf(
                '[SupercupQualification] expected 4 qualifiers for %s in game %s, got %d. '
                . 'cup_winner=%s cup_runnerup=%s league_top_teams=%d',
                $supercupId,
                $game->id,
                count($qualifiers),
                $cupFinalists['winner'] ?? 'null',
                $cupFinalists['runnerUp'] ?? 'null',
                count($leagueTopTeams),
            ));
        }

        // Update supercup competition_entries for this game
        $this->updateSupercupTeams($game->id, $supercupId, $qualifiers);

        // Store qualifiers in metadata for display
        $data->setMetadata('supercupQualifiers', $qualifiers);
    }

    /**
     * Get the two cup finalists.
     *
     * @return array{winner: string|null, runnerUp: string|null}
     */
    private function getCupFinalists(string $gameId, string $cupId, int $finalRound): array
    {
        $finalTie = CupTie::where('game_id', $gameId)
            ->where('competition_id', $cupId)
            ->where('round_number', $finalRound)
            ->where('completed', true)
            ->first();

        if (!$finalTie) {
            return ['winner' => null, 'runnerUp' => null];
        }

        return [
            'winner' => $finalTie->winner_id,
            'runnerUp' => $finalTie->getLoserId(),
        ];
    }

    /**
     * Get the top N teams from league standings.
     *
     * @return array<int, string> Team IDs in order of position
     */
    private function getLeagueTopTeams(string $gameId, string $leagueId, int $count): array
    {
        // Try real standings first (player's league)
        $teams = GameStanding::where('game_id', $gameId)
            ->where('competition_id', $leagueId)
            ->orderBy('position')
            ->limit($count)
            ->pluck('team_id')
            ->toArray();

        if (!empty($teams)) {
            return $teams;
        }

        // Fall back to simulated season results (non-player leagues)
        $game = Game::find($gameId);
        $simulated = SimulatedSeason::where('game_id', $gameId)
            ->where('competition_id', $leagueId)
            ->where('season', $game->season)
            ->first();

        if ($simulated && !empty($simulated->results)) {
            return array_slice($simulated->results, 0, $count);
        }

        return [];
    }

    /**
     * Determine the 4 supercup qualifiers in RFEF priority order: the two
     * cup finalists hold their slots regardless of league finish, then
     * league positions fill what remains, in order. When a cup finalist
     * also happens to be league 1st/2nd, the league's slot is what
     * cascades down to 3rd / 4th — mirroring "la plaza de la Supercopa
     * se otorga al 3º (y 4º si fuera necesario) clasificado de la Liga".
     *
     * @param  array{winner: string|null, runnerUp: string|null}  $cupFinalists
     * @param  array<int, string>  $leagueTopTeams  league positions 1..N (1st first)
     * @return array<string>  up to 4 team IDs
     */
    private function determineQualifiers(array $cupFinalists, array $leagueTopTeams): array
    {
        $qualifiers = [];
        $usedTeams = [];

        if ($cupFinalists['winner']) {
            $qualifiers[] = $cupFinalists['winner'];
            $usedTeams[$cupFinalists['winner']] = true;
        }
        if ($cupFinalists['runnerUp']) {
            $qualifiers[] = $cupFinalists['runnerUp'];
            $usedTeams[$cupFinalists['runnerUp']] = true;
        }

        foreach ($leagueTopTeams as $teamId) {
            if (count($qualifiers) >= 4) {
                break;
            }

            if (isset($usedTeams[$teamId])) {
                continue;
            }

            $qualifiers[] = $teamId;
            $usedTeams[$teamId] = true;
        }

        return $qualifiers;
    }

    /**
     * Update supercup competition_entries for this game.
     */
    private function updateSupercupTeams(string $gameId, string $supercupId, array $teamIds): void
    {
        // Remove old entries
        CompetitionEntry::where('game_id', $gameId)
            ->where('competition_id', $supercupId)
            ->delete();

        // Insert new qualifiers in batch
        if (!empty($teamIds)) {
            $rows = array_map(fn ($teamId) => [
                'game_id' => $gameId,
                'competition_id' => $supercupId,
                'team_id' => $teamId,
                'entry_round' => 1,
            ], $teamIds);

            CompetitionEntry::insert($rows);
        }
    }
}
