<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
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
 * Strategy: read inputs in one SELECT, resolve biographical fields
 * (date_of_birth) separately, compute the new ability / market value / tier
 * in PHP via the existing services, and write back in 500-row UPDATE…FROM
 * (VALUES …) chunks. PHP-side computation keeps the planner cost of every
 * statement low (no JIT trigger), the chunked writes keep peak bindings
 * small (Telescope-friendly), and using PlayerValuationService restores the
 * original log-linear interpolation and performance-trend multiplier that
 * the prior single-statement CTE approach had simplified to ~±15% banded
 * approximations.
 */
class PlayerDevelopmentProcessor implements SeasonProcessor
{
    private const UPDATE_CHUNK_SIZE = 500;
    public function __construct(
        private readonly PlayerValuationService $valuationService,
    ) {}

    public function priority(): int
    {
        return 55;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $currentDate = $game->current_date;
        if ($currentDate === null) {
            return $data;
        }

        // Read everything from game_players in one tenant-side query.
        // date_of_birth and overall_score live on game_players directly
        // (post-Phase-6), so no control-plane round-trip is needed.
        $inputs = DB::table('game_players AS gp')
            ->leftJoin('game_player_match_state AS gpms', function ($join) use ($game) {
                $join->on('gpms.game_player_id', '=', 'gp.id')
                    ->where('gpms.game_id', '=', $game->id);
            })
            ->where('gp.game_id', $game->id)
            ->whereNotNull('gp.date_of_birth')
            ->select([
                'gp.id',
                'gp.team_id',
                'gp.date_of_birth',
                'gp.overall_score',
                'gp.potential',
                'gp.position',
                'gp.market_value_cents AS old_market_value',
                'gp.tier AS old_tier',
                DB::raw('COALESCE(gpms.season_appearances, 0) AS season_appearances'),
            ])
            ->get();

        $userTeamId = $game->team_id;

        if ($inputs->isEmpty()) {
            return $data;
        }

        // Compute new values in PHP. Skip rows that don't change so we
        // don't burn writes on no-ops (parity with the old IS DISTINCT FROM
        // guard).
        $updates = [];
        foreach ($inputs as $row) {
            if ($row->overall_score === null) {
                continue;
            }

            $age = $this->ageOnDate($row->date_of_birth, $currentDate->toDateString());
            $previousOverall = (int) $row->overall_score;
            $potential = $row->potential !== null ? (int) $row->potential : 99;
            $appearances = (int) $row->season_appearances;
            // Only the user's team accumulates accurate season_appearances. For
            // every other team — sibling AI clubs whose matchdays the user
            // doesn't share, foreign leagues run by the synthetic resolver,
            // and the user's reserve in a lower division — the real count is
            // 0 or near-zero noise. Substitute a randomized play factor so
            // those players don't all get stuck at the training-only floor.
            $randomize = $row->team_id !== $userTeamId;

            $newOverall = $this->computeNewOverall($age, $appearances, $previousOverall, $potential, $randomize);
            $newMarketValue = $this->valuationService->overallScoreToMarketValue($newOverall, $age, $previousOverall, $row->position ?? null);
            $newTier = PlayerTierService::tierFromMarketValue($newMarketValue);

            $oldMarketValue = (int) $row->old_market_value;
            $oldTier = (int) $row->old_tier;

            if ($newOverall === $previousOverall && $newMarketValue === $oldMarketValue && $newTier === $oldTier) {
                continue;
            }

            $updates[] = [
                'id' => $row->id,
                'overall_score' => $newOverall,
                'market_value_cents' => $newMarketValue,
                'tier' => $newTier,
            ];
        }

        // 4. Write back in bounded chunks. UPDATE…FROM (VALUES …) keyed on
        // the PK so each chunk is a small, plan-trivial PK lookup.
        foreach (array_chunk($updates, self::UPDATE_CHUNK_SIZE) as $chunk) {
            $this->applyChunk($chunk);
        }

        return $data;
    }

    /**
     * Apply development arithmetic without hydrating a GamePlayer model.
     * Mirrors PlayerDevelopmentService::calculateDevelopment().
     */
    private function computeNewOverall(int $age, int $appearances, int $currentOverall, int $potential, bool $randomize = false): int
    {
        $baseChange = DevelopmentCurve::getChange($age);
        $change = DevelopmentCurve::calculateChange($baseChange, $appearances, $randomize);

        if ($change > 0) {
            $change += DevelopmentCurve::gapBonus($age, $currentOverall, $potential);
        }

        $newOverall = $currentOverall + $change;
        if ($change > 0) {
            $newOverall = min($newOverall, $potential);
        }

        return max(1, min(99, $newOverall));
    }

    /**
     * Compute integer age (full years) for a date string against a reference date.
     */
    private function ageOnDate(string $dateOfBirth, string $referenceDate): int
    {
        $dob = new \DateTimeImmutable($dateOfBirth);
        $ref = new \DateTimeImmutable($referenceDate);

        return $ref->diff($dob)->y;
    }

    /**
     * @param  array<int, array{id:string, overall_score:int, market_value_cents:int, tier:int}>  $chunk
     */
    private function applyChunk(array $chunk): void
    {
        if ($chunk === []) {
            return;
        }

        $valueRows = [];
        $bindings = [];
        foreach ($chunk as $row) {
            $valueRows[] = '(?::uuid, ?::smallint, ?::bigint, ?::smallint)';
            $bindings[] = $row['id'];
            $bindings[] = $row['overall_score'];
            $bindings[] = $row['market_value_cents'];
            $bindings[] = $row['tier'];
        }
        $values = implode(', ', $valueRows);

        DB::update(<<<SQL
            UPDATE game_players AS gp
            SET overall_score      = v.overall_score,
                market_value_cents = v.market_value_cents,
                tier               = v.tier
            FROM (VALUES {$values}) AS v(id, overall_score, market_value_cents, tier)
            WHERE gp.id = v.id
        SQL, $bindings);
    }
}
