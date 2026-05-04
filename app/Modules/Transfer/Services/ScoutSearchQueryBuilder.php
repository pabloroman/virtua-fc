<?php

namespace App\Modules\Transfer\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Player;
use App\Models\Team;
use App\Models\TransferOffer;
use App\Modules\Player\PlayerAge;
use App\Support\PositionMapper;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds queries for scouting search candidates.
 *
 * Extracted from ScoutingService::generateResults() to eliminate
 * duplicated age-filter SQL and isolate query construction.
 */
class ScoutSearchQueryBuilder
{
    /**
     * Build the query for team-based candidates matching scout filters.
     */
    public function buildCandidateQuery(Game $game, array $filters, array $positions): Builder
    {
        $query = GamePlayer::with(['player', 'team'])
            ->where('game_id', $game->id)
            ->whereNotNull('team_id')
            ->whereNotIn('team_id', $game->userTeamIds());

        $this->applyPositionFilter($query, $positions);

        $this->applyScopeFilter($query, $game, $filters);
        $this->applyAgeFilter($query, $game, $filters);
        $this->applyAbilityFilter($query, $filters);
        $this->applyValueFilter($query, $filters);
        $this->applyContractFilter($query, $game, $filters);
        $this->excludeLoaned($query, $game);
        $this->excludeAgreed($query, $game);

        return $query;
    }

    /**
     * Match candidates whose primary position is in $positions OR whose
     * secondary_positions JSON array contains any of $positions.
     *
     * Uses PostgreSQL jsonb_exists_any for the secondary match.
     */
    private function applyPositionFilter(Builder $query, array $positions): void
    {
        if (empty($positions)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $placeholders = implode(',', array_fill(0, count($positions), '?'));

        $query->where(function (Builder $inner) use ($positions, $placeholders) {
            $inner->whereIn('position', $positions)
                ->orWhereRaw(
                    "(secondary_positions IS NOT NULL AND jsonb_exists_any(secondary_positions::jsonb, ARRAY[$placeholders]::text[]))",
                    $positions
                );
        });
    }

    private function applyScopeFilter(Builder $query, Game $game, array $filters): void
    {
        $scope = $filters['scope'] ?? ['domestic', 'international'];
        if (count($scope) !== 1) {
            return;
        }

        $teamCountry = $game->country;
        $scopeCompetitionIds = Competition::where('country', in_array('domestic', $scope) ? '=' : '!=', $teamCountry)
            ->pluck('id');
        $scopeTeamIds = Team::transferMarketEligible()
            ->whereHas('competitions', function ($q) use ($scopeCompetitionIds) {
                $q->whereIn('competitions.id', $scopeCompetitionIds);
            })->pluck('id');
        $query->whereIn('team_id', $scopeTeamIds);
    }

    private function applyAgeFilter(Builder $query, Game $game, array $filters): void
    {
        if (empty($filters['age_min']) && empty($filters['age_max'])) {
            return;
        }

        // Resolve qualifying biographical player ids on the control plane and
        // intersect with the GamePlayer query via whereIn. Replaces a
        // correlated subquery against the players table that would cross the
        // control/tenant boundary.
        $playerQuery = Player::query();

        if (! empty($filters['age_min'])) {
            // age >= N → date_of_birth <= today − N years
            $playerQuery->where(
                'date_of_birth',
                '<=',
                PlayerAge::dateOfBirthCutoff((int) $filters['age_min'], $game->current_date),
            );
        }
        if (! empty($filters['age_max'])) {
            // age <= N → date_of_birth > today − (N+1) years
            $playerQuery->where(
                'date_of_birth',
                '>',
                PlayerAge::dateOfBirthCutoff((int) $filters['age_max'] + 1, $game->current_date),
            );
        }

        $query->whereIn('player_id', $playerQuery->pluck('id'));
    }

    private function applyAbilityFilter(Builder $query, array $filters): void
    {
        $min = ! empty($filters['ability_min']) ? (int) $filters['ability_min'] : null;
        $max = ! empty($filters['ability_max']) ? (int) $filters['ability_max'] : null;
        if ($min === null && $max === null) {
            return;
        }

        // The effective ability is COALESCE(game_players.overall_score,
        // players.overall_score) — game-specific value if set, biographical
        // baseline otherwise. Players sits on the control plane, so the
        // fallback half can't be a correlated subquery; resolve qualifying
        // biographical ids up front and intersect via whereIn.
        $playerQuery = Player::query();
        if ($min !== null) {
            $playerQuery->where('overall_score', '>=', $min);
        }
        if ($max !== null) {
            $playerQuery->where('overall_score', '<=', $max);
        }
        $qualifyingPlayerIds = $playerQuery->pluck('id');

        $query->where(function ($outer) use ($min, $max, $qualifyingPlayerIds) {
            $outer->where(function ($gpQ) use ($min, $max) {
                $gpQ->whereNotNull('game_players.overall_score');
                if ($min !== null) {
                    $gpQ->where('game_players.overall_score', '>=', $min);
                }
                if ($max !== null) {
                    $gpQ->where('game_players.overall_score', '<=', $max);
                }
            })->orWhere(function ($pQ) use ($qualifyingPlayerIds) {
                $pQ->whereNull('game_players.overall_score')
                    ->whereIn('game_players.player_id', $qualifyingPlayerIds);
            });
        });
    }

    private function applyValueFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['value_min'])) {
            $query->where('market_value_cents', '>=', $filters['value_min'] * 100);
        }
        if (! empty($filters['value_max'])) {
            $query->where('market_value_cents', '<=', $filters['value_max'] * 100);
        }
    }

    private function applyContractFilter(Builder $query, Game $game, array $filters): void
    {
        $seasonEnd = $game->getSeasonEndDate();
        if (! empty($filters['expiring_contract'])) {
            $query->whereNotNull('contract_until')
                ->where('contract_until', '<=', $seasonEnd);
        } else {
            $query->where(function ($q) use ($seasonEnd) {
                $q->whereNull('contract_until')
                    ->orWhere('contract_until', '>', $seasonEnd);
            });
        }
    }

    private function excludeLoaned(Builder $query, Game $game): void
    {
        $loanedPlayerIds = Loan::where('game_id', $game->id)
            ->where('status', Loan::STATUS_ACTIVE)
            ->pluck('game_player_id');

        $query->whereNotIn('id', $loanedPlayerIds);
    }

    private function excludeAgreed(Builder $query, Game $game): void
    {
        $agreedPlayerIds = TransferOffer::where('game_id', $game->id)
            ->where('status', TransferOffer::STATUS_AGREED)
            ->pluck('game_player_id');

        $query->whereNotIn('id', $agreedPlayerIds);
    }
}
