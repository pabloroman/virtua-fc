<?php

namespace App\Modules\Manager\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Modules\Competition\Services\CountryConfig;
use Illuminate\Support\Collection;

/**
 * Generates manager job offers and the starter team pool that drive
 * pro-manager mode: the three Local-tier Primera RFEF teams shown inline
 * on /new-game when a user starts a Pro Manager career, and the
 * end-of-season offers sent to a manager based on how the season just
 * played out.
 *
 * Cross-country offers are emitted as a matter of course — by the time a
 * career manager is doing well enough to attract foreign attention, the
 * universe already has rosters for every configured country (seeded once in
 * SetupNewGame), so an accepted offer only requires per-game reputation rows
 * for the destination country to be backfilled.
 */
class JobOfferService
{
    /** Country used as the entry point for every new pro-manager career. */
    private const STARTING_COUNTRY = 'ES';

    /** Primera RFEF (Spanish third division) — the floor of the pyramid. */
    private const STARTING_TIER = 3;

    // Four to fill the 4-column /new-game grid at lg without leaving gaps.
    private const INITIAL_OFFER_COUNT = 4;
    private const POST_FIRING_OFFER_COUNT = 3;

    public function __construct(
        private readonly CountryConfig $countryConfig,
    ) {}

    /**
     * Sample N random Local-tier Primera RFEF Team models for the inline
     * Pro Manager onboarding picker on /new-game. Returns hydrated Team
     * models with clubProfile eager-loaded so the Blade can render crest +
     * name without N+1. No persistence — the chosen team is materialized
     * via GameCreationService when InitGame fires.
     *
     * @return Collection<int, Team>
     */
    public function sampleInitialProManagerTeams(int $count = self::INITIAL_OFFER_COUNT): Collection
    {
        $picks = $this->eligibleProManagerStartingTeamIds()->shuffle()->take($count);

        return Team::with('clubProfile')
            ->whereIn('id', $picks)
            ->get();
    }

    /**
     * The full pool of teams eligible to be picked as the starting club of
     * a brand-new Pro Manager career. Used by SelectTeam for sampling and
     * by InitGame to validate that a submitted team_id wasn't tampered.
     *
     * @return Collection<int, string>
     */
    public function eligibleProManagerStartingTeamIds(): Collection
    {
        return $this->eligibleTeamIdsForTier(
            countryCode: self::STARTING_COUNTRY,
            tier: self::STARTING_TIER,
            reputationLevel: ClubProfile::REPUTATION_LOCAL,
            excludeTeamId: null,
        );
    }

    /**
     * Generate end-of-season offers for a pro-manager game based on the
     * manager's performance grade and current club reputation.
     *
     * Returns the new offers (empty when the manager has merely survived
     * with no extra interest). Sets Game.fired_at_season_end = true when
     * the season was a disaster.
     *
     * @return Collection<int, ManagerJobOffer>
     */
    public function generateEndOfSeasonOffers(Game $game, string $grade): Collection
    {
        if (!$game->isProManagerMode()) {
            return collect();
        }

        $currentReputation = $this->currentClubReputation($game);
        $currentTierIndex = ClubProfile::getReputationTierIndex($currentReputation);

        $plan = match ($grade) {
            'exceptional' => $this->planForExceptional($currentTierIndex),
            'exceeded' => [
                ['shift' => 1, 'count' => 1],
                ['shift' => 0, 'count' => 1],
            ],
            'met' => mt_rand(1, 100) <= 50 ? [['shift' => 0, 'count' => 1]] : [],
            'below' => [],
            'disaster' => $this->planForDisaster($currentTierIndex),
            default => [],
        };

        if ($grade === 'disaster') {
            $game->update(['fired_at_season_end' => true]);
        }

        if (empty($plan)) {
            return collect();
        }

        $offerType = $grade === 'disaster'
            ? ManagerJobOffer::TYPE_POST_FIRING
            : ManagerJobOffer::TYPE_END_OF_SEASON;

        return $this->writeOffers(
            game: $game,
            plan: $plan,
            currentTierIndex: $currentTierIndex,
            currentReputation: $currentReputation,
            offerType: $offerType,
            allowAdjacentFallback: $grade === 'disaster',
        );
    }

    /**
     * Resolve which competition a team is currently registered in for the
     * starting country at the starting tier. Falls back to a tier-1 league
     * for the team's country if Spanish reference data is unavailable.
     */
    public function resolveCompetitionId(string $teamId, string $countryCode, int $tier): ?string
    {
        $tierIds = $this->countryConfig->tierCompetitionIds($countryCode, $tier);
        if (empty($tierIds)) {
            return null;
        }

        return CompetitionTeam::whereIn('competition_id', $tierIds)
            ->where('team_id', $teamId)
            ->value('competition_id');
    }

    /**
     * Resolve the competition_id the manager will be installed at when an
     * offer is accepted: tier-1 league of the team's country, falling back
     * to any league the team is registered in.
     */
    public function resolveAcceptedCompetitionId(string $teamId): ?string
    {
        $team = Team::find($teamId);
        if (!$team) {
            return null;
        }

        $competitionTeam = CompetitionTeam::where('team_id', $teamId)
            ->whereHas('competition', fn ($q) => $q
                ->where('role', Competition::ROLE_LEAGUE)
                ->where('country', $team->country)
                ->where('tier', 1))
            ->first()
            ?? CompetitionTeam::where('team_id', $teamId)
                ->whereHas('competition', fn ($q) => $q
                    ->where('role', Competition::ROLE_LEAGUE)
                    ->where('country', $team->country))
                ->orderBy('competition_id')
                ->first()
            ?? CompetitionTeam::where('team_id', $teamId)->first();

        return $competitionTeam?->competition_id
            ?? $this->countryConfig->competitionForTier($team->country, 1);
    }

    private function planForExceptional(int $currentTierIndex): array
    {
        // At the top of the pyramid (Elite, index 4) there is nowhere up to
        // go — emit a single lateral move instead.
        if ($currentTierIndex >= ClubProfile::getReputationTierIndex(ClubProfile::REPUTATION_ELITE)) {
            return [['shift' => 0, 'count' => 1]];
        }

        $plan = [
            ['shift' => 1, 'count' => 1],
        ];

        if ($currentTierIndex + 2 <= ClubProfile::getReputationTierIndex(ClubProfile::REPUTATION_ELITE)) {
            $plan[] = ['shift' => 2, 'count' => 1];
        }

        return $plan;
    }

    private function planForDisaster(int $currentTierIndex): array
    {
        // Sacked managers are placed back in the market one tier lower (or
        // at Local if already there) with a healthy three offers so they're
        // not stuck choosing between just one or two clubs.
        $targetTier = max(0, $currentTierIndex - 1);

        return [['shift' => $targetTier - $currentTierIndex, 'count' => self::POST_FIRING_OFFER_COUNT]];
    }

    /**
     * @param array<int, array{shift: int, count: int}> $plan
     * @return Collection<int, ManagerJobOffer>
     */
    private function writeOffers(
        Game $game,
        array $plan,
        int $currentTierIndex,
        string $currentReputation,
        string $offerType,
        bool $allowAdjacentFallback,
    ): Collection {
        $tiers = ClubProfile::REPUTATION_TIERS;
        $maxIndex = count($tiers) - 1;
        $usedTeamIds = [$game->team_id];
        $created = collect();

        foreach ($plan as $entry) {
            $targetIndex = max(0, min($maxIndex, $currentTierIndex + $entry['shift']));
            $targetReputation = $tiers[$targetIndex];

            $candidates = $this->eligibleTeamIdsAnyPlayableCountry(
                reputationLevel: $targetReputation,
                excludeTeamIds: $usedTeamIds,
            );

            // Fallback: on a disaster the user must end up with offers even
            // if the exact target tier has nothing — widen to the adjacent
            // tier in the same direction (down for firings, up for lateral
            // failovers when 'local' is already empty).
            if ($candidates->count() < $entry['count'] && $allowAdjacentFallback) {
                $fallbackIndex = max(0, min($maxIndex, $targetIndex + ($entry['shift'] < 0 ? 1 : -1)));
                if ($fallbackIndex !== $targetIndex) {
                    $candidates = $candidates->merge(
                        $this->eligibleTeamIdsAnyPlayableCountry(
                            reputationLevel: $tiers[$fallbackIndex],
                            excludeTeamIds: array_merge($usedTeamIds, $candidates->all()),
                        )
                    );
                }
            }

            $picks = $candidates->shuffle()->take($entry['count']);

            foreach ($picks as $teamId) {
                $offer = ManagerJobOffer::create([
                    'user_id' => $game->user_id,
                    'game_id' => $game->id,
                    'team_id' => $teamId,
                    'competition_id' => $this->resolveAcceptedCompetitionId($teamId),
                    'season' => $game->season,
                    'offer_type' => $offerType,
                    'status' => ManagerJobOffer::STATUS_PENDING,
                    'source_reputation_level' => $currentReputation,
                    'target_reputation_level' => $targetReputation,
                    'created_on_game_date' => $game->current_date,
                ]);
                $created->push($offer);
                $usedTeamIds[] = $teamId;
            }
        }

        return $created;
    }

    /**
     * Find every team in $countryCode whose ClubProfile sits at
     * $reputationLevel and that participates at the given tier. Reserve
     * (B) teams are filtered out — they're never selectable as a managed
     * club, neither for the Pro Manager starting pick nor for any other
     * offer the manager could accept.
     *
     * @return Collection<int, string>
     */
    private function eligibleTeamIdsForTier(
        string $countryCode,
        int $tier,
        string $reputationLevel,
        ?string $excludeTeamId,
    ): Collection {
        $tierIds = $this->countryConfig->tierCompetitionIds($countryCode, $tier);
        if (empty($tierIds)) {
            return collect();
        }

        $teamIds = CompetitionTeam::whereIn('competition_id', $tierIds)
            ->whereIn('team_id', function ($q) {
                $q->select('id')->from('teams')->whereNull('parent_team_id');
            })
            ->pluck('team_id')
            ->unique();

        $query = ClubProfile::whereIn('team_id', $teamIds)
            ->where('reputation_level', $reputationLevel);

        if ($excludeTeamId !== null) {
            $query->where('team_id', '!=', $excludeTeamId);
        }

        return $query->pluck('team_id')->values();
    }

    /**
     * Find every team in any playable country whose ClubProfile is at
     * $reputationLevel. Used for end-of-season cross-country offers.
     * Reserve teams are excluded — see eligibleTeamIdsForTier.
     *
     * @param array<int, string> $excludeTeamIds
     * @return Collection<int, string>
     */
    private function eligibleTeamIdsAnyPlayableCountry(
        string $reputationLevel,
        array $excludeTeamIds,
    ): Collection {
        $countryCodes = $this->countryConfig->playableCountryCodes();

        return ClubProfile::whereIn('team_id', function ($query) use ($countryCodes) {
                $query->select('id')
                    ->from('teams')
                    ->whereIn('country', $countryCodes)
                    ->whereNull('parent_team_id');
            })
            ->where('reputation_level', $reputationLevel)
            ->whereNotIn('team_id', $excludeTeamIds)
            ->pluck('team_id')
            ->values();
    }

    private function currentClubReputation(Game $game): string
    {
        return ClubProfile::where('team_id', $game->team_id)->value('reputation_level')
            ?? ClubProfile::REPUTATION_LOCAL;
    }
}
