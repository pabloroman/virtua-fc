<?php

namespace App\Modules\Transfer\Services;

use App\Models\Team;

/**
 * Resolves the configured set of AI teams that are excluded from signing players.
 *
 * Excluded teams (listed by slug in config/finances.php `ai_excluded_from_signing`)
 * never buy, sign free agents, or receive loan moves while AI-controlled. They
 * rely exclusively on synthetic youth academy generation for replenishment.
 * The exclusion only matters when the team is not the user's team — user-controlled
 * teams bypass the AI transfer code paths entirely.
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
     * Resolve configured slugs to team UUIDs (one query, memoized per instance).
     *
     * @return array<string, true>
     */
    private function resolve(): array
    {
        if ($this->excludedTeamIds !== null) {
            return $this->excludedTeamIds;
        }

        $slugs = config('finances.ai_excluded_from_signing', []);

        if (empty($slugs)) {
            return $this->excludedTeamIds = [];
        }

        $ids = Team::whereIn('slug', $slugs)->pluck('id')->all();

        return $this->excludedTeamIds = array_fill_keys($ids, true);
    }
}
