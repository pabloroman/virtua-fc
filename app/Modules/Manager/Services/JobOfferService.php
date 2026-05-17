<?php

namespace App\Modules\Manager\Services;

use App\Models\ClubProfile;
use App\Models\Competition;
use App\Models\CompetitionTeam;
use App\Models\Game;
use App\Models\GameStanding;
use App\Models\ManagerJobOffer;
use App\Models\Team;
use App\Modules\Competition\Promotions\PromotionRelegationFactory;
use App\Modules\Competition\Services\CountryConfig;
use App\Modules\Manager\ManagerReputation;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Services\SeasonGoalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Deepest league tier in any country pyramid (Spain's Primera RFEF at 3).
     * Used as a fixed denominator so prestige ranks are comparable across
     * countries with different pyramid depths. Bump if a deeper tier is added.
     */
    private const MAX_LEAGUE_TIER = 3;

    /** @var array<int, array<int, string>> */
    private array $tierCompetitionIdsCache = [];

    public function __construct(
        private readonly CountryConfig $countryConfig,
        private readonly SeasonGoalService $seasonGoalService,
        private readonly PromotionRelegationFactory $promotionRelegationFactory,
        private readonly NotificationService $notificationService,
        private readonly ManagerReputationService $managerReputationService,
    ) {}

    /**
     * Ensure end-of-season job offers exist for a pro-manager game whose
     * season has just finished. Idempotent — short-circuits if any offer for
     * this (game, season) tuple already exists. Resolves the manager's grade
     * from the final standings + promotion rule, generates the offers, and
     * notifies the user.
     *
     * Called from /season-end's Continue action and from ShowSeasonOffers
     * itself (so the page can be refreshed safely). Runs at view time
     * rather than inside SeasonClosingPipeline because the user needs to
     * act on the offers *before* the pipeline kicks off.
     */
    public function ensureEndOfSeasonOffersGenerated(Game $game): void
    {
        if (!$game->isProManagerMode()) {
            return;
        }

        if ($game->season_offers_generated_for === $game->season) {
            return;
        }

        $evaluation = $this->resolveEvaluation($game);
        $grade = $evaluation['grade'] ?? 'met';

        // Reputation update + offer generation + stamp share one
        // transaction so a crash between steps can't leave manager_reputation_points
        // doubled or offers orphaned without the season marker. The
        // season_offers_generated_for stamp is the resume sentinel — once
        // committed, the early-return at the top of this method makes
        // subsequent calls no-ops.
        $offers = DB::transaction(function () use ($game, $evaluation, $grade) {
            $this->managerReputationService->applySeasonOutcome($game, $evaluation);

            // Re-read so generateEndOfSeasonOffers sees the freshly applied
            // reputation when computing the effective rank floor.
            $game->refresh();

            $created = $this->generateEndOfSeasonOffers($game, $grade);

            // Stamp the season even when the plan was empty (e.g. an
            // isolated "met" season with no interested clubs).
            // hasResolvedOffersFor relies on this marker to distinguish
            // "not yet generated" from "generated and produced zero
            // rows" — without it, the no-offers branch on /season-offers
            // would loop back here forever.
            $game->update(['season_offers_generated_for' => $game->season]);

            return $created;
        });

        if ($offers->isNotEmpty()) {
            $this->notificationService->notifyJobOfferReceived(
                $game,
                $offers->count(),
                $grade === 'disaster',
            );
        }
    }

    /**
     * Has the manager already resolved this season's offers — by accepting
     * one (sets pending_team_switch) or by declining all (every offer is
     * STATUS_REJECTED)? Used by StartNewSeason to know whether to route the
     * user through /season-offers or fall through to the closing pipeline.
     */
    public function hasResolvedOffersFor(Game $game): bool
    {
        // Until ensureEndOfSeasonOffersGenerated has stamped this season,
        // the user hasn't even been shown the offers page yet.
        if ($game->season_offers_generated_for !== $game->season) {
            return false;
        }

        if ($game->pending_team_switch) {
            return true;
        }

        // Generated but nobody's pending: either every offer was declined
        // (rejected status) or the plan produced zero offers in the first
        // place. Either way, the user has nothing left to act on.
        $pendingExists = ManagerJobOffer::where('game_id', $game->id)
            ->where('season', $game->season)
            ->where('status', ManagerJobOffer::STATUS_PENDING)
            ->whereIn('offer_type', [
                ManagerJobOffer::TYPE_END_OF_SEASON,
                ManagerJobOffer::TYPE_POST_FIRING,
            ])
            ->exists();

        return !$pendingExists;
    }

    /**
     * Resolve the full performance evaluation for the season just closed.
     * Mirrors the calculation in SeasonSummaryService::buildSeasonSummary().
     * Returns the structured array so callers can read both the headline
     * grade and the supporting trophy/promotion signals that drive the
     * manager-reputation delta.
     *
     * @return array<string, mixed>
     */
    private function resolveEvaluation(Game $game): array
    {
        $playerStanding = GameStanding::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where('team_id', $game->team_id)
            ->first();

        return $this->seasonGoalService->evaluatePerformance(
            $game,
            $playerStanding->position ?? 20,
            $this->promotionRelegationFactory->wasTeamPromoted($game),
        );
    }

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
     * with no extra interest). A 'disaster' grade emits POST_FIRING offers
     * — the presence of those rows is what Game::wasFiredThisSeason() reads.
     *
     * @return Collection<int, ManagerJobOffer>
     */
    public function generateEndOfSeasonOffers(Game $game, string $grade): Collection
    {
        if (!$game->isProManagerMode()) {
            return collect();
        }

        $currentReputation = $this->currentClubReputation($game);
        $currentLeagueTier = $this->currentLeagueTier($game);
        $clubRank = $this->prestigeRank($currentLeagueTier, $currentReputation);

        // Manager reputation acts as a parallel anchor: a manager whose
        // personal rep has outgrown their club fields offers above the
        // club's prestige band. The effective rank is the higher of the
        // two, so a Lorca-era manager still sees Tier-3 offers (club
        // anchor wins early), while a manager who racked up European
        // trophies at a mid-table club starts seeing top-flight offers.
        $managerRank = $this->managerReputationRank($game);
        $effectiveRank = max($clubRank, $managerRank);

        $plan = match ($grade) {
            'exceptional' => $this->planForExceptional($effectiveRank),
            'exceeded' => [
                ['shift' => 1, 'count' => 1],
                ['shift' => 0, 'count' => 1],
            ],
            'met' => mt_rand(1, 100) <= 50 ? [['shift' => 0, 'count' => 1]] : [],
            // A "below" season at a club above the manager's personal
            // ceiling is the Emery-at-Arsenal moment: no firing, but
            // other clubs lower down the ladder reach out — the soft
            // landing that lets the career bounce back.
            'below' => [['shift' => -1, 'count' => 1]],
            'disaster' => [['shift' => -1, 'count' => self::POST_FIRING_OFFER_COUNT]],
            default => [],
        };

        if (empty($plan)) {
            return collect();
        }

        $offerType = $grade === 'disaster'
            ? ManagerJobOffer::TYPE_POST_FIRING
            : ManagerJobOffer::TYPE_END_OF_SEASON;

        return $this->writeOffers(
            game: $game,
            plan: $plan,
            currentRank: $effectiveRank,
            currentReputation: $currentReputation,
            offerType: $offerType,
            allowAdjacentFallback: $grade === 'disaster' || $grade === 'below',
        );
    }

    /**
     * Compute the manager's personal prestige rank from their accumulated
     * reputation points. Returned on the same scale as prestigeRank() so
     * the two can be compared directly inside generateEndOfSeasonOffers.
     * See ManagerReputation::anchorFor() for the per-tier mapping rationale.
     */
    private function managerReputationRank(Game $game): int
    {
        $level = ManagerReputation::levelFromPoints((int) ($game->manager_reputation_points ?? 0));
        [$tier, $reputation] = ManagerReputation::anchorFor($level);

        return $this->prestigeRank($tier, $reputation);
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

    private function planForExceptional(int $currentRank): array
    {
        $maxRank = self::maxPrestigeRank();

        // At the top of the pyramid (T1 / elite) there is nowhere up to go
        // — emit a single lateral move instead.
        if ($currentRank >= $maxRank) {
            return [['shift' => 0, 'count' => 1]];
        }

        $plan = [['shift' => 1, 'count' => 1]];

        if ($currentRank + 2 <= $maxRank) {
            $plan[] = ['shift' => 2, 'count' => 1];
        }

        return $plan;
    }

    /**
     * @param array<int, array{shift: int, count: int}> $plan
     * @return Collection<int, ManagerJobOffer>
     */
    private function writeOffers(
        Game $game,
        array $plan,
        int $currentRank,
        string $currentReputation,
        string $offerType,
        bool $allowAdjacentFallback,
    ): Collection {
        $maxRank = self::maxPrestigeRank();
        $usedTeamIds = [$game->team_id];
        $created = collect();

        foreach ($plan as $entry) {
            $targetRank = max(0, min($maxRank, $currentRank + $entry['shift']));

            $candidates = $this->eligibleTeamIdsAtRank($targetRank, $usedTeamIds);

            // Fallback: on a disaster the user must end up with offers even
            // if the exact target rank is empty — widen one rank further in
            // the same direction (down for firings, up for lateral failovers
            // when the floor is empty).
            if ($candidates->count() < $entry['count'] && $allowAdjacentFallback) {
                $fallbackRank = max(0, min($maxRank, $targetRank + ($entry['shift'] < 0 ? -1 : 1)));
                if ($fallbackRank !== $targetRank) {
                    $candidates = $candidates->merge(
                        $this->eligibleTeamIdsAtRank(
                            $fallbackRank,
                            array_merge($usedTeamIds, $candidates->all()),
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
                    // The chosen team's actual reputation — may differ from
                    // the target rank's reputation slot if a fallback widened
                    // the pool, so read it off the team rather than the plan.
                    'target_reputation_level' => ClubProfile::where('team_id', $teamId)->value('reputation_level')
                        ?? $currentReputation,
                    'created_on_game_date' => $game->current_date,
                ]);
                $created->push($offer);
                $usedTeamIds[] = $teamId;
            }
        }

        return $created;
    }

    /**
     * Composite prestige rank for a (league_tier, reputation) pair. Higher =
     * more prestigious. League tier dominates: any job in a higher league
     * tier outranks every job in a lower one. Within a tier, reputation
     * orders the ladder.
     *
     *   rank = (MAX_LEAGUE_TIER - league_tier) * |reputation_tiers| + rep_index
     *
     * With MAX_LEAGUE_TIER = 3 and 5 reputations, ranks span 0..14:
     *   0  T3/local        ...  4  T3/elite
     *   5  T2/local        ...  9  T2/elite
     *   10 T1/local        ... 14 T1/elite
     */
    public function prestigeRank(int $leagueTier, string $reputation): int
    {
        $repIndex = ClubProfile::getReputationTierIndex($reputation);
        $tier = max(1, min(self::MAX_LEAGUE_TIER, $leagueTier));
        return (self::MAX_LEAGUE_TIER - $tier) * count(ClubProfile::REPUTATION_TIERS) + $repIndex;
    }

    /**
     * Decompose a prestige rank back into (league_tier, reputation_level).
     *
     * @return array{0: int, 1: string}
     */
    private function decomposePrestigeRank(int $rank): array
    {
        $repCount = count(ClubProfile::REPUTATION_TIERS);
        $rank = max(0, min(self::maxPrestigeRank(), $rank));

        $tier = self::MAX_LEAGUE_TIER - intdiv($rank, $repCount);
        $reputation = ClubProfile::REPUTATION_TIERS[$rank % $repCount];

        return [$tier, $reputation];
    }

    private static function maxPrestigeRank(): int
    {
        return self::MAX_LEAGUE_TIER * count(ClubProfile::REPUTATION_TIERS) - 1;
    }

    /**
     * Read the league tier the manager is currently coaching at from the
     * game's main league competition. Falls back to the deepest tier if the
     * competition row is missing (shouldn't happen in practice but keeps
     * pro-manager bootstrap defensive).
     */
    private function currentLeagueTier(Game $game): int
    {
        return Competition::where('id', $game->competition_id)
            ->value('tier') ?? self::MAX_LEAGUE_TIER;
    }

    /**
     * Every team across all playable countries whose (current league tier,
     * club reputation) matches the given prestige rank. Reserves are
     * filtered out so they can never appear as offers.
     *
     * @param array<int, string> $excludeTeamIds
     * @return Collection<int, string>
     */
    private function eligibleTeamIdsAtRank(int $rank, array $excludeTeamIds): Collection
    {
        [$tier, $reputation] = $this->decomposePrestigeRank($rank);

        $tierCompetitionIds = $this->competitionIdsAtTier($tier);
        if (empty($tierCompetitionIds)) {
            return collect();
        }

        $teamIdsAtTier = CompetitionTeam::whereIn('competition_id', $tierCompetitionIds)
            ->whereIn('team_id', function ($q) {
                $q->select('id')->from('teams')->whereNull('parent_team_id');
            })
            ->pluck('team_id')
            ->unique();

        return ClubProfile::whereIn('team_id', $teamIdsAtTier)
            ->where('reputation_level', $reputation)
            ->whereNotIn('team_id', $excludeTeamIds)
            ->pluck('team_id')
            ->values();
    }

    /**
     * Every league competition ID at a given tier across all playable
     * countries (primary + siblings).
     *
     * @return array<int, string>
     */
    private function competitionIdsAtTier(int $tier): array
    {
        if (isset($this->tierCompetitionIdsCache[$tier])) {
            return $this->tierCompetitionIdsCache[$tier];
        }

        $ids = [];
        foreach ($this->countryConfig->playableCountryCodes() as $code) {
            foreach ($this->countryConfig->tierCompetitionIds($code, $tier) as $id) {
                $ids[] = $id;
            }
        }

        return $this->tierCompetitionIdsCache[$tier] = array_values(array_unique($ids));
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

    private function currentClubReputation(Game $game): string
    {
        return ClubProfile::where('team_id', $game->team_id)->value('reputation_level')
            ?? ClubProfile::REPUTATION_LOCAL;
    }
}
