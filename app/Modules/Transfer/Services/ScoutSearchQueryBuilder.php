<?php

namespace App\Modules\Transfer\Services;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Loan;
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
        $query = GamePlayer::with(['team'])
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
     * Uses Eloquent's whereJsonContains so the query stays portable across
     * Postgres and SQLite drivers.
     */
    private function applyPositionFilter(Builder $query, array $positions): void
    {
        if (empty($positions)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $inner) use ($positions) {
            $inner->whereIn('position', $positions);
            foreach ($positions as $position) {
                $inner->orWhereJsonContains('secondary_positions', $position);
            }
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

        // Convert age boundaries to date-of-birth cutoffs so the query stays
        // portable (no Postgres-only EXTRACT/AGE). A player is at least
        // age_min if dob <= current_date - age_min years; at most age_max if
        // dob > current_date - (age_max + 1) years.
        $gameDate = $game->current_date;

        if (! empty($filters['age_min'])) {
            $query->where(
                'date_of_birth',
                '<=',
                PlayerAge::dateOfBirthCutoff((int) $filters['age_min'], $gameDate)
            );
        }
        if (! empty($filters['age_max'])) {
            $query->where(
                'date_of_birth',
                '>',
                PlayerAge::dateOfBirthCutoff((int) $filters['age_max'] + 1, $gameDate)
            );
        }
    }

    private function applyAbilityFilter(Builder $query, array $filters): void
    {
        if (empty($filters['ability_min']) && empty($filters['ability_max'])) {
            return;
        }

        if (! empty($filters['ability_min'])) {
            $query->where('overall_score', '>=', (int) $filters['ability_min']);
        }
        if (! empty($filters['ability_max'])) {
            $query->where('overall_score', '<=', (int) $filters['ability_max']);
        }
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
