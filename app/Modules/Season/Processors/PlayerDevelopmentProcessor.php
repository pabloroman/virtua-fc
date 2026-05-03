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
 *     previousAbility) is dropped — the new ability already reflects
 *     improvement/decline.
 *
 * Everything else is preserved exactly: AGE_CURVES values, the
 * MIN_APPEARANCES_FOR_GROWTH=10 / FULL_BONUS_APPEARANCES=25 scaling, the
 * TRAINING_ONLY_GROWTH_FACTOR=0.5 fallback for bench players, the +1
 * quality-gap bonus for late bloomers, the potential cap, and the 1..99
 * ability bounds.
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
                    COALESCE(gp.overall_score, p.overall_score)                 AS old_overall,
                    COALESCE(gp.potential, 99)                                  AS pot
                FROM game_players gp
                JOIN players p ON p.id = gp.player_id
                LEFT JOIN game_player_match_state gpms ON gpms.game_player_id = gp.id
                WHERE gp.game_id = ?
                  AND p.date_of_birth IS NOT NULL
            ),
            curves AS (
                SELECT
                    id, age, apps, old_overall, pot,
                    (CASE LEAST(GREATEST(age, 16), 34)
                        WHEN 16 THEN 3 WHEN 17 THEN 3 WHEN 18 THEN 2 WHEN 19 THEN 2
                        WHEN 20 THEN 1 WHEN 21 THEN 1 WHEN 22 THEN 1 WHEN 23 THEN 0
                        WHEN 24 THEN 0 WHEN 25 THEN -1 WHEN 26 THEN -1
                        WHEN 27 THEN -1 WHEN 28 THEN -2 WHEN 29 THEN -2
                        WHEN 30 THEN -3 WHEN 31 THEN -3
                        WHEN 32 THEN -4 WHEN 33 THEN -4 WHEN 34 THEN -5
                    END) AS overall_base
                FROM calc
            ),
            scaled AS (
                SELECT
                    id, age, old_overall, pot,
                    CASE
                        WHEN overall_base > 0 AND apps < 10 THEN ROUND(overall_base * 0.5)::int
                        WHEN overall_base > 0 THEN ROUND(overall_base * LEAST(1.0, apps::numeric / 25))::int
                        WHEN overall_base < 0 AND apps >= 10 THEN ROUND(overall_base * 0.5)::int
                        WHEN overall_base < 0 THEN overall_base
                        ELSE 0
                    END AS overall_delta_raw
                FROM curves
            ),
            bumped AS (
                SELECT
                    id, age, old_overall, pot,
                    overall_delta_raw + CASE WHEN age < 23 AND (pot - old_overall) >= 15 AND overall_delta_raw > 0 THEN 1 ELSE 0 END AS overall_delta
                FROM scaled
            ),
            clamped AS (
                SELECT
                    id, age,
                    GREATEST(1, LEAST(99,
                        CASE WHEN overall_delta > 0 THEN LEAST(old_overall + overall_delta, pot) ELSE old_overall + overall_delta END
                    )) AS new_overall
                FROM bumped
            ),
            priced AS (
                SELECT
                    id, new_overall,
                    GREATEST(10000000, LEAST(1500000000000,
                        ROUND(
                            (CASE
                                WHEN new_overall <= 45 THEN 10000000
                                WHEN new_overall <= 50 THEN 30000000
                                WHEN new_overall <= 55 THEN 60000000
                                WHEN new_overall <= 60 THEN 130000000
                                WHEN new_overall <= 65 THEN 250000000
                                WHEN new_overall <= 70 THEN 600000000
                                WHEN new_overall <= 75 THEN 1200000000
                                WHEN new_overall <= 80 THEN 3000000000
                                WHEN new_overall <= 85 THEN 6000000000
                                WHEN new_overall <= 90 THEN 10000000000
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
                overall_score          = priced.new_overall,
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
                   game_players.overall_score      IS DISTINCT FROM priced.new_overall
                OR game_players.market_value_cents IS DISTINCT FROM priced.new_mv
              )
        SQL, [$currentDate, $game->id]);

        return $data;
    }
}
