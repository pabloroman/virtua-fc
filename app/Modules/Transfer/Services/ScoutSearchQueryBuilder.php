<?php

namespace App\Modules\Transfer\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
use App\Models\Team;
use App\Models\TransferOffer;
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
            ->where('team_id', '!=', $game->team_id)
            ->whereIn('position', $positions);

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
     * Build the query for free agents matching scout filters.
     */
    public function buildFreeAgentQuery(Game $game, array $filters, array $positions): Builder
    {
        $query = GamePlayer::with(['player'])
            ->where('game_id', $game->id)
            ->whereNull('team_id')
            ->whereIn('position', $positions);

        $this->applyAgeFilter($query, $game, $filters);

        return $query;
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

        /** @var \Illuminate\Database\Connection $connection */
        $connection = $query->getQuery()->getConnection();
        $driver = $connection->getDriverName();
        $dobSubquery = '(SELECT date_of_birth FROM players WHERE players.id = game_players.player_id)';
        $gameDate = $game->current_date->toDateString();

        $ageExpr = $driver === 'pgsql'
            ? "EXTRACT(YEAR FROM AGE(?::date, $dobSubquery))"
            : "(strftime('%Y', ?) - strftime('%Y', $dobSubquery))";

        if (! empty($filters['age_min'])) {
            $query->whereRaw("($ageExpr) >= ?", [$gameDate, (int) $filters['age_min']]);
        }
        if (! empty($filters['age_max'])) {
            $query->whereRaw("($ageExpr) <= ?", [$gameDate, (int) $filters['age_max']]);
        }
    }

    private function applyAbilityFilter(Builder $query, array $filters): void
    {
        if (empty($filters['ability_min']) && empty($filters['ability_max'])) {
            return;
        }

        $query->where(function ($q) use ($filters) {
            $abilityExpr = '(COALESCE(game_players.game_technical_ability, (SELECT technical_ability FROM players WHERE players.id = game_players.player_id)) + COALESCE(game_players.game_physical_ability, (SELECT physical_ability FROM players WHERE players.id = game_players.player_id))) / 2';
            if (! empty($filters['ability_min'])) {
                $q->whereRaw("($abilityExpr) >= ?", [(int) $filters['ability_min']]);
            }
            if (! empty($filters['ability_max'])) {
                $q->whereRaw("($abilityExpr) <= ?", [(int) $filters['ability_max']]);
            }
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
