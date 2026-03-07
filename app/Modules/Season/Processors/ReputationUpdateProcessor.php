<?php

namespace App\Modules\Season\Processors;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\SimulatedSeason;
use App\Models\TeamReputation;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonEndProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Models\GameNotification;

/**
 * Updates reputation points and tiers for all teams at season end.
 *
 * Reads final positions from GameStanding (user's league) and
 * SimulatedSeason (AI leagues), awards/deducts points, applies
 * regression toward base tier, and recalculates effective tiers.
 *
 * Priority: 27 (after PromotionRelegation so we know final positions,
 * before LeagueFixture/BudgetProjection so new tiers affect next season)
 */
class ReputationUpdateProcessor implements SeasonEndProcessor
{
    /** Track user team's tier before updates for notification. */
    private ?string $userTeamOldTier = null;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function priority(): int
    {
        return 27;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        // Capture user's current tier before any updates
        $userReputation = TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();
        $this->userTeamOldTier = $userReputation?->reputation_level;

        // Get all league competitions this game has entries for
        $leagueCompetitions = $this->getLeagueCompetitions($game);

        foreach ($leagueCompetitions as $competition) {
            $this->updateReputationsForLeague($game, $competition);
        }

        // Notify the user if their team's tier changed
        $this->notifyUserIfTierChanged($game);

        return $data;
    }

    /**
     * Get all league competitions (excluding cups, European, Swiss) in this game.
     */
    private function getLeagueCompetitions(Game $game): \Illuminate\Support\Collection
    {
        $competitionIds = CompetitionEntry::where('game_id', $game->id)
            ->pluck('competition_id')
            ->unique();

        return Competition::whereIn('id', $competitionIds)
            ->where('role', Competition::ROLE_LEAGUE)
            ->whereIn('handler_type', ['league', 'league_with_playoff'])
            ->get();
    }

    /**
     * Update reputations for all teams in a league competition.
     */
    private function updateReputationsForLeague(Game $game, Competition $competition): void
    {
        $positions = $this->getTeamPositions($game, $competition);

        if (empty($positions)) {
            return;
        }

        $tier = $competition->tier ?? 1;
        $deltas = config("reputation.position_deltas.{$tier}", config('reputation.position_deltas.1'));
        $regressionRate = config('reputation.regression_rate', 5);

        foreach ($positions as $teamId => $position) {
            $reputation = TeamReputation::where('game_id', $game->id)
                ->where('team_id', $teamId)
                ->first();

            if (!$reputation) {
                continue;
            }

            // Calculate points delta based on final position
            $pointsDelta = $this->getPointsDelta($position, $deltas);

            // Apply regression toward base tier
            $basePoints = TeamReputation::pointsForTier($reputation->base_reputation_level);
            $currentPoints = $reputation->reputation_points;

            if ($currentPoints > $basePoints) {
                $pointsDelta -= $regressionRate;
            } elseif ($currentPoints < $basePoints) {
                $pointsDelta += $regressionRate;
            }

            // Update points and recalculate tier
            $reputation->reputation_points = max(0, $currentPoints + $pointsDelta);
            $reputation->recalculateTier();
            $reputation->save();
        }
    }

    /**
     * Get final positions for all teams in a competition.
     * Uses GameStanding for the played league, SimulatedSeason for AI leagues.
     *
     * @return array<string, int> team_id => position
     */
    private function getTeamPositions(Game $game, Competition $competition): array
    {
        $positions = [];

        // Check for real standings first (user's played league)
        $standings = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $competition->id)
            ->where('played', '>', 0)
            ->get();

        if ($standings->isNotEmpty()) {
            foreach ($standings as $standing) {
                $positions[$standing->team_id] = $standing->position;
            }

            return $positions;
        }

        // Fall back to simulated season results for AI leagues
        $simulated = SimulatedSeason::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('competition_id', $competition->id)
            ->first();

        if ($simulated && !empty($simulated->results)) {
            foreach ($simulated->results as $index => $teamId) {
                $positions[$teamId] = $index + 1; // Convert 0-indexed to 1-indexed
            }
        }

        return $positions;
    }

    /**
     * Look up the points delta for a given final position.
     */
    private function getPointsDelta(int $position, array $deltas): int
    {
        foreach ($deltas as $maxPosition => $delta) {
            if ($position <= $maxPosition) {
                return $delta;
            }
        }

        // Fallback: use the last (largest) position bracket
        return end($deltas) ?: 0;
    }

    /**
     * Check if the user's team tier changed and send a notification.
     */
    private function notifyUserIfTierChanged(Game $game): void
    {
        if (!$this->userTeamOldTier) {
            return;
        }

        $reputation = TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        if (!$reputation || $this->userTeamOldTier === $reputation->reputation_level) {
            return;
        }

        $oldIndex = ClubProfile::getReputationTierIndex($this->userTeamOldTier);
        $newIndex = ClubProfile::getReputationTierIndex($reputation->reputation_level);
        $improved = $newIndex > $oldIndex;

        $this->notificationService->create(
                game: $game,
                type: 'reputation_change',
                title: __('notifications.reputation_change_title'),
                message: $improved
                    ? __('notifications.reputation_improved', [
                        'tier' => __('finances.reputation.' . $reputation->reputation_level),
                    ])
                    : __('notifications.reputation_declined', [
                        'tier' => __('finances.reputation.' . $reputation->reputation_level),
                    ]),
                priority: $improved
                    ? GameNotification::PRIORITY_SUCCESS
                    : GameNotification::PRIORITY_WARNING,
                icon: $improved ? '📈' : '📉',
            );
    }
}
