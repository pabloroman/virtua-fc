<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use Illuminate\Support\Facades\DB;

/**
 * Applies player development, market revaluation, and tier recompute at
 * season end.
 *
 * Single-statement strategy: every per-row computation (development curve,
 * appearance scaling, gap bonus, ability clamp, market value bucket, tier)
 * is expressed as PostgreSQL CASE expressions inside one UPDATE...FROM. No
 * PHP loop, no upsert chain, one round-trip.
 *
 * Heuristic trade-offs vs. the prior implementation:
 *   - Market value uses a discretized lookup (10 ability bands × 9 age
 *     buckets) instead of log-linear interpolation across 11 anchors. The
 *     band midpoints track the original anchors closely; worst-case error
 *     is ~±15% near band boundaries.
 *   - The performance trend multiplier (small ±5–30% bump driven by
 *     previousAbility) is dropped — the new ability average already
 *     reflects improvement/decline.
 *
 * Everything else is preserved exactly: AGE_CURVES values, the
 * MIN_APPEARANCES_FOR_GROWTH=10 / FULL_BONUS_APPEARANCES=25 scaling, the
 * +1 quality-gap bonus for late bloomers, the potential cap, and the
 * 1..99 ability bounds.
 */
class PlayerDevelopmentProcessor implements SeasonProcessor
{
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

        DB::update(<<<'SQL'
            WITH calc AS (
                SELECT
                    gp.id,
                    DATE_PART('year', AGE(?::date, p.date_of_birth))::int       AS age,
                    COALESCE(gpms.season_appearances, 0)                        AS apps,
                    COALESCE(gp.game_technical_ability, p.technical_ability)    AS old_tech,
                    COALESCE(gp.game_physical_ability, p.physical_ability)      AS old_phys,
                    COALESCE(gp.potential, 99)                                  AS pot
                FROM game_players gp
                JOIN players p ON p.id = gp.player_id
                LEFT JOIN game_player_match_state gpms ON gpms.game_player_id = gp.id
                WHERE gp.game_id = ?
                  AND p.date_of_birth IS NOT NULL
            ),
            curves AS (
                SELECT
                    id, age, apps, old_tech, old_phys, pot,
                    ROUND((old_tech + old_phys)::numeric / 2)::int AS old_avg,
                    (CASE LEAST(GREATEST(age, 16), 34)
                        WHEN 16 THEN 3 WHEN 17 THEN 2 WHEN 18 THEN 2 WHEN 19 THEN 2
                        WHEN 20 THEN 1 WHEN 21 THEN 1 WHEN 22 THEN 1 WHEN 23 THEN 0
                        WHEN 24 THEN 0 WHEN 25 THEN 0 WHEN 26 THEN 0
                        WHEN 27 THEN -1 WHEN 28 THEN -1 WHEN 29 THEN -1
                        WHEN 30 THEN -2 WHEN 31 THEN -2
                        WHEN 32 THEN -3 WHEN 33 THEN -3 WHEN 34 THEN -4
                    END) AS tech_base,
                    (CASE LEAST(GREATEST(age, 16), 34)
                        WHEN 16 THEN 3 WHEN 17 THEN 3 WHEN 18 THEN 2 WHEN 19 THEN 2
                        WHEN 20 THEN 1 WHEN 21 THEN 1 WHEN 22 THEN 0 WHEN 23 THEN 0
                        WHEN 24 THEN 0 WHEN 25 THEN -1 WHEN 26 THEN -1
                        WHEN 27 THEN -1 WHEN 28 THEN -2 WHEN 29 THEN -2
                        WHEN 30 THEN -3 WHEN 31 THEN -3
                        WHEN 32 THEN -4 WHEN 33 THEN -4 WHEN 34 THEN -5
                    END) AS phys_base
                FROM calc
            ),
            scaled AS (
                SELECT
                    id, age, old_tech, old_phys, pot, old_avg,
                    CASE
                        WHEN tech_base > 0 AND apps < 10 THEN 0
                        WHEN tech_base > 0 THEN ROUND(tech_base * LEAST(1.0, apps::numeric / 25))::int
                        WHEN tech_base < 0 AND apps >= 10 THEN ROUND(tech_base * 0.5)::int
                        WHEN tech_base < 0 THEN tech_base
                        ELSE 0
                    END AS tech_delta_raw,
                    CASE
                        WHEN phys_base > 0 AND apps < 10 THEN 0
                        WHEN phys_base > 0 THEN ROUND(phys_base * LEAST(1.0, apps::numeric / 25))::int
                        WHEN phys_base < 0 AND apps >= 10 THEN ROUND(phys_base * 0.5)::int
                        WHEN phys_base < 0 THEN phys_base
                        ELSE 0
                    END AS phys_delta_raw
                FROM curves
            ),
            bumped AS (
                SELECT
                    id, age, old_tech, old_phys, pot,
                    tech_delta_raw + CASE WHEN age < 23 AND (pot - old_avg) >= 15 AND tech_delta_raw > 0 THEN 1 ELSE 0 END AS tech_delta,
                    phys_delta_raw + CASE WHEN age < 23 AND (pot - old_avg) >= 15 AND phys_delta_raw > 0 THEN 1 ELSE 0 END AS phys_delta
                FROM scaled
            ),
            clamped AS (
                SELECT
                    id, age,
                    GREATEST(1, LEAST(99,
                        CASE WHEN tech_delta > 0 THEN LEAST(old_tech + tech_delta, pot) ELSE old_tech + tech_delta END
                    )) AS new_tech,
                    GREATEST(1, LEAST(99,
                        CASE WHEN phys_delta > 0 THEN LEAST(old_phys + phys_delta, pot) ELSE old_phys + phys_delta END
                    )) AS new_phys
                FROM bumped
            ),
            priced AS (
                SELECT
                    id, new_tech, new_phys,
                    GREATEST(10000000, LEAST(1500000000000,
                        ROUND(
                            (CASE
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 45 THEN 10000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 50 THEN 30000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 55 THEN 60000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 60 THEN 130000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 65 THEN 250000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 70 THEN 600000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 75 THEN 1200000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 80 THEN 3000000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 85 THEN 6000000000
                                WHEN ROUND((new_tech + new_phys)::numeric / 2) <= 90 THEN 10000000000
                                ELSE 14000000000
                            END)::numeric
                            *
                            (CASE
                                WHEN age <= 19 THEN 1.30
                                WHEN age <= 21 THEN 1.20
                                WHEN age <= 23 THEN 1.10
                                WHEN age <= 26 THEN 1.05
                                WHEN age <= 31 THEN 1.00
                                WHEN age <= 33 THEN 0.75
                                WHEN age <= 35 THEN 0.45
                                WHEN age <= 37 THEN 0.30
                                ELSE 0.15
                            END)
                        )::bigint
                    )) AS new_mv
                FROM clamped
            )
            UPDATE game_players
            SET
                game_technical_ability = priced.new_tech,
                game_physical_ability  = priced.new_phys,
                market_value_cents     = priced.new_mv,
                tier = CASE
                    WHEN priced.new_mv >= 5000000000 THEN 5
                    WHEN priced.new_mv >= 2000000000 THEN 4
                    WHEN priced.new_mv >= 500000000  THEN 3
                    WHEN priced.new_mv >= 100000000  THEN 2
                    ELSE 1
                END
            FROM priced
            WHERE game_players.id = priced.id
              AND (
                   game_players.game_technical_ability IS DISTINCT FROM priced.new_tech
                OR game_players.game_physical_ability  IS DISTINCT FROM priced.new_phys
                OR game_players.market_value_cents     IS DISTINCT FROM priced.new_mv
              )
        SQL, [$currentDate, $game->id]);

        return $data;
    }
}
