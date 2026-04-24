<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Modules\Player\Services\DevelopmentCurve;
use App\Modules\Player\Services\PlayerTierService;
use App\Modules\Player\Services\PlayerValuationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\DB;

/**
 * Applies player development, market revaluation, and tier recompute at
 * season end.
 *
 * Hybrid approach: one raw SELECT pulls every row the calculation needs,
 * PHP computes new values (cheap arithmetic), chunked UPSERTs write
 * back. Avoids the two hidden N+1s that made the Eloquent-based version
 * take 60+ seconds:
 *   - current_technical_ability / current_physical_ability accessors
 *     lazy-loading the players row when game_*_ability is NULL
 *   - season_appearances accessor lazy-loading the matchState satellite
 */
class PlayerDevelopmentProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly PlayerValuationService $valuationService,
    ) {}

    public function priority(): int
    {
        return 55;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $currentDate = $game->current_date?->toDateString();
        if ($currentDate === null) {
            return $data;
        }

        $rows = DB::select(<<<'SQL'
            SELECT
                gp.id,
                gp.game_id,
                gp.player_id,
                gp.team_id,
                gp.position,
                DATE_PART('year', AGE(?::date, p.date_of_birth))::int AS age,
                COALESCE(gpms.season_appearances, 0) AS apps,
                COALESCE(gp.game_technical_ability, p.technical_ability) AS tech,
                COALESCE(gp.game_physical_ability, p.physical_ability) AS phys,
                gp.potential
            FROM game_players gp
            JOIN players p ON p.id = gp.player_id
            LEFT JOIN game_player_match_state gpms ON gpms.game_player_id = gp.id
            WHERE gp.game_id = ?
              AND p.date_of_birth IS NOT NULL
        SQL, [$currentDate, $game->id]);

        if (empty($rows)) {
            return $data;
        }

        $upsertRows = [];
        foreach ($rows as $r) {
            $age = (int) $r->age;
            $apps = (int) $r->apps;
            $tech = (int) $r->tech;
            $phys = (int) $r->phys;
            $pot = $r->potential !== null ? (int) $r->potential : 99;

            $curve = DevelopmentCurve::getChanges($age);
            $techDelta = DevelopmentCurve::calculateChange($curve['technical'], $apps);
            $physDelta = DevelopmentCurve::calculateChange($curve['physical'], $apps);

            $oldAvg = (int) round(($tech + $phys) / 2);
            if ($age < 23 && ($pot - $oldAvg) >= 15) {
                if ($techDelta > 0) {
                    $techDelta += 1;
                }
                if ($physDelta > 0) {
                    $physDelta += 1;
                }
            }

            $newTech = $tech + $techDelta;
            $newPhys = $phys + $physDelta;
            if ($techDelta > 0) {
                $newTech = min($newTech, $pot);
            }
            if ($physDelta > 0) {
                $newPhys = min($newPhys, $pot);
            }
            $newTech = max(1, min(99, $newTech));
            $newPhys = max(1, min(99, $newPhys));

            $newAvg = (int) round(($newTech + $newPhys) / 2);
            $newMV = $this->valuationService->abilityToMarketValue($newAvg, $age, $oldAvg);

            $upsertRows[] = [
                'id' => $r->id,
                'game_id' => $r->game_id,
                'player_id' => $r->player_id,
                'team_id' => $r->team_id,
                'position' => $r->position,
                'game_technical_ability' => $newTech,
                'game_physical_ability' => $newPhys,
                'market_value_cents' => $newMV,
                'tier' => PlayerTierService::tierFromMarketValue($newMV),
            ];
        }

        foreach (array_chunk($upsertRows, 500) as $chunk) {
            GamePlayer::upsert($chunk, ['id'], [
                'game_technical_ability',
                'game_physical_ability',
                'market_value_cents',
                'tier',
            ]);
        }

        return $data;
    }
}
