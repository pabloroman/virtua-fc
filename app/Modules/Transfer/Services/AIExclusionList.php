<?php

namespace App\Modules\Transfer\Services;

use App\Models\Team;

/**
 * Resolves the set of AI teams that are excluded from signing players.
 *
 * Two sources of exclusion:
 * 1. Config-driven: slugs listed in `finances.ai_excluded_from_signing`
 * 2. Automatic: B teams / filiales (teams with a non-null `parent_team_id`)
 *
 * Excluded teams never buy, sign free agents, or receive loan moves while
 * AI-controlled. They rely exclusively on synthetic youth academy generation
 * for replenishment. The exclusion only matters when the team is not the
 * user's team — user-controlled teams bypass the AI transfer code paths entirely.
 */
class AIExclusionList
{
    /** @var array<string, true>|null Memoized map of excluded team UUIDs */
    private ?array $excludedTeamIds = null;

    /**
     * Check whether a given team is excluded from signing players.
     */
    public function contains(string $teamId): bool
    {
        return isset($this->resolve()[$teamId]);
    }

    /**
     * Resolve excluded team UUIDs from config slugs and reserve teams (single pass, memoized).
     *
     * @return array<string, true>
     */
    private function resolve(): array
    {
        if ($this->excludedTeamIds !== null) {
            return $this->excludedTeamIds;
        }

        $ids = [];

        // Config-driven exclusions (by slug)
        $slugs = config('finances.ai_excluded_from_signing', []);
        if (! empty($slugs)) {
            $ids = Team::whereIn('slug', $slugs)->pluck('id')->all();
        }

        // B teams / filiales are always excluded
        $reserveIds = Team::whereNotNull('parent_team_id')->pluck('id')->all();

        return $this->excludedTeamIds = array_fill_keys(array_merge($ids, $reserveIds), true);
    }
}
