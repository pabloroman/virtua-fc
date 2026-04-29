<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\SeasonTicketPricing;
use App\Models\Team;
use App\Models\TeamReputation;
use Illuminate\Support\Facades\DB;

/**
 * Resolves seating area layouts, baseline prices, and predicted demand for
 * the user's season ticket sale. The schematic on the stadium page reads
 * the same area definitions returned here so labels, capacity proportions,
 * and visual ordering stay in sync between server-rendered defaults and
 * client-side fill predictions.
 *
 * Stand layout scales with capacity. Tiny grounds keep two general zones;
 * mid-size grounds add VIP and split into separate ends; large grounds
 * (≥ 50k) introduce upper/lower deck splits ("alta"/"baja") on the lateral
 * and main stands. The largest grounds (Bernabéu-scale, > 70k) add private
 * boxes ("palco") on top of everything else.
 */
class SeasonTicketPricingService
{
    /**
     * Stadium capacity tiers. Each tier defines the ordered list of areas.
     * Capacity proportions sum to 1.0; the largest area absorbs rounding
     * remainders so per-area capacities always sum to the stadium total.
     *
     * Areas use slugs that map to translation keys under
     * `club.stadium.season_tickets.area.<slug>`.
     */
    public const TIERS = [
        // Tiny ground (Pontevedra-scale)
        [
            'max_capacity' => 15_000,
            'areas' => [
                ['slug' => 'general', 'share' => 0.75, 'multiplier' => 1.00],
                ['slug' => 'tribuna', 'share' => 0.25, 'multiplier' => 2.00],
            ],
        ],
        // Small ground — VIP first appears here.
        [
            'max_capacity' => 30_000,
            'areas' => [
                ['slug' => 'general', 'share' => 0.45, 'multiplier' => 1.00],
                ['slug' => 'lateral', 'share' => 0.28, 'multiplier' => 1.50],
                ['slug' => 'tribuna', 'share' => 0.22, 'multiplier' => 2.10],
                ['slug' => 'vip',     'share' => 0.05, 'multiplier' => 4.00],
            ],
        ],
        // Mid-size ground — split ends.
        [
            'max_capacity' => 50_000,
            'areas' => [
                ['slug' => 'fondo_norte', 'share' => 0.23, 'multiplier' => 1.00],
                ['slug' => 'fondo_sur',   'share' => 0.23, 'multiplier' => 1.00],
                ['slug' => 'lateral',     'share' => 0.30, 'multiplier' => 1.55],
                ['slug' => 'tribuna',     'share' => 0.19, 'multiplier' => 2.20],
                ['slug' => 'vip',         'share' => 0.05, 'multiplier' => 4.30],
            ],
        ],
        // Large ground — stand levels (alta/baja) and palco appear.
        // _alta = larger upper deck, cheaper; _baja = smaller lower deck,
        // closer to the pitch and pricier.
        [
            'max_capacity' => 70_000,
            'areas' => [
                ['slug' => 'fondo_norte',  'share' => 0.20, 'multiplier' => 1.00],
                ['slug' => 'fondo_sur',    'share' => 0.20, 'multiplier' => 1.00],
                ['slug' => 'lateral_alta', 'share' => 0.15, 'multiplier' => 1.35],
                ['slug' => 'lateral_baja', 'share' => 0.13, 'multiplier' => 1.85],
                ['slug' => 'tribuna_alta', 'share' => 0.13, 'multiplier' => 1.95],
                ['slug' => 'tribuna_baja', 'share' => 0.10, 'multiplier' => 2.60],
                ['slug' => 'vip',          'share' => 0.06, 'multiplier' => 4.50],
                ['slug' => 'palco',        'share' => 0.03, 'multiplier' => 8.00],
            ],
        ],
        // Iconic stadium (Bernabéu / Camp Nou).
        [
            'max_capacity' => PHP_INT_MAX,
            'areas' => [
                ['slug' => 'fondo_norte',  'share' => 0.18, 'multiplier' => 1.00],
                ['slug' => 'fondo_sur',    'share' => 0.18, 'multiplier' => 1.00],
                ['slug' => 'lateral_alta', 'share' => 0.15, 'multiplier' => 1.40],
                ['slug' => 'lateral_baja', 'share' => 0.13, 'multiplier' => 1.95],
                ['slug' => 'tribuna_alta', 'share' => 0.13, 'multiplier' => 2.05],
                ['slug' => 'tribuna_baja', 'share' => 0.11, 'multiplier' => 2.70],
                ['slug' => 'vip',          'share' => 0.07, 'multiplier' => 4.80],
                ['slug' => 'palco',        'share' => 0.05, 'multiplier' => 10.00],
            ],
        ],
    ];

    /**
     * Baseline ticket price (in cents) for the cheapest area, by reputation.
     * Multipliers in TIERS scale up from this to derive each area's default.
     */
    public const BASELINE_PRICE_CENTS = [
        ClubProfile::REPUTATION_LOCAL        => 12_000,  // €120
        ClubProfile::REPUTATION_MODEST       => 16_000,  // €160
        ClubProfile::REPUTATION_ESTABLISHED  => 24_000,  // €240
        ClubProfile::REPUTATION_CONTINENTAL  => 38_000,  // €380
        ClubProfile::REPUTATION_ELITE        => 50_000,  // €500
    ];

    /**
     * Allowed price band, expressed as a multiplier of the baseline price.
     * The user can bargain (down to 25%) or hike prices (up to 4x); extreme
     * moves crater fill in the prediction model so the floor and ceiling
     * exist mostly to keep inputs sane and prevent cents-level overflow.
     */
    public const MIN_PRICE_MULTIPLIER = 0.25;
    public const MAX_PRICE_MULTIPLIER = 4.00;

    /**
     * Premium-tier multipliers respond less elastically to price moves —
     * VIP/palco buyers churn less when prices wobble.
     */
    private const PREMIUM_MULTIPLIER_THRESHOLD = 4.0;

    /**
     * Build the seating layout for a stadium of the given capacity.
     * Returns a list of areas with absolute capacity (proportions of the
     * stadium total) and the default price per ticket in cents.
     *
     * @return array<int, array{slug: string, capacity: int, baseline_price_cents: int, multiplier: float}>
     */
    public function buildAreas(int $capacity, string $reputationLevel): array
    {
        $tier = $this->resolveTier($capacity);
        $baselinePrice = self::BASELINE_PRICE_CENTS[$reputationLevel]
            ?? self::BASELINE_PRICE_CENTS[ClubProfile::REPUTATION_LOCAL];

        $areas = [];
        $assignedCapacity = 0;
        $largestIndex = 0;
        $largestShare = 0.0;

        foreach ($tier['areas'] as $i => $def) {
            $areaCapacity = (int) floor($capacity * $def['share']);
            $areas[] = [
                'slug' => $def['slug'],
                'capacity' => $areaCapacity,
                'baseline_price_cents' => (int) round($baselinePrice * $def['multiplier']),
                'multiplier' => (float) $def['multiplier'],
            ];
            $assignedCapacity += $areaCapacity;

            if ($def['share'] > $largestShare) {
                $largestShare = $def['share'];
                $largestIndex = $i;
            }
        }

        // Push rounding remainder onto the biggest area so the seat counts
        // sum to capacity exactly (off-by-one would inflate or shrink revenue).
        $remainder = $capacity - $assignedCapacity;
        if ($remainder !== 0 && isset($areas[$largestIndex])) {
            $areas[$largestIndex]['capacity'] += $remainder;
        }

        return $areas;
    }

    /**
     * Build the default pricing record for a team — used both at season
     * setup and as the form's initial values when the user hasn't priced
     * yet. Each area is priced at the baseline (multiplier 1.0).
     *
     * @return array{areas: array<int, array<string, mixed>>, total_capacity: int, total_sold: int, total_revenue: int}
     */
    public function buildDefaultPricing(Game $game, Team $team): array
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $homeRep = $this->resolveReputation($game->id, $team->id, $reputation);
        $capacity = (int) ($team->stadium_seats ?? 0);

        $areas = $this->buildAreas($capacity, $reputation);

        $userPrices = array_map(fn ($a) => $a['baseline_price_cents'], $areas);

        return $this->predictAndCompose($areas, $userPrices, $homeRep);
    }

    /**
     * Compose a pricing payload from the user's per-area prices. Validates
     * each price against the allowed band, predicts area-level fill, and
     * returns aggregates ready to persist on a SeasonTicketPricing row.
     *
     * @param  array<int, int>  $userPricesByIndex  Price in cents, keyed by area index.
     * @return array{areas: array<int, array<string, mixed>>, total_capacity: int, total_sold: int, total_revenue: int}
     */
    public function buildFromUserPrices(Game $game, Team $team, array $userPricesByIndex): array
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $homeRep = $this->resolveReputation($game->id, $team->id, $reputation);
        $capacity = (int) ($team->stadium_seats ?? 0);

        $areas = $this->buildAreas($capacity, $reputation);

        $userPrices = [];
        foreach ($areas as $i => $area) {
            $raw = $userPricesByIndex[$i] ?? $area['baseline_price_cents'];
            $userPrices[] = $this->clampPrice($area['baseline_price_cents'], (int) $raw);
        }

        return $this->predictAndCompose($areas, $userPrices, $homeRep);
    }

    /**
     * Predict fill per area for a given user pricing, without persisting.
     * Runs the same demand model as buildFromUserPrices() so the live UI
     * preview stays in sync with what gets saved.
     *
     * @param  array<int, int>  $userPricesByIndex
     * @return array{
     *   areas: array<int, array<string, mixed>>,
     *   total_capacity: int,
     *   total_sold: int,
     *   total_revenue: int,
     *   overall_fill_rate: int,
     * }
     */
    public function predict(Game $game, Team $team, array $userPricesByIndex): array
    {
        $payload = $this->buildFromUserPrices($game, $team, $userPricesByIndex);
        $payload['overall_fill_rate'] = $payload['total_capacity'] > 0
            ? (int) round(($payload['total_sold'] / $payload['total_capacity']) * 100)
            : 0;

        return $payload;
    }

    /**
     * Whether the user can still set or change season ticket prices for
     * the current season. Locked once any league fixture for the user's
     * team has been played — cup ties and pre-season friendlies don't
     * count, so the user has the full pre-season window to decide.
     */
    public function canEdit(Game $game): bool
    {
        return ! $this->hasPlayedLeagueMatch($game);
    }

    /**
     * Persist the user's pricing for the current season. Throws if a row
     * already exists and the lock has triggered (defence-in-depth — the
     * UI hides the form, but the action also re-checks).
     *
     * @param  array<int, int>  $userPricesByIndex
     */
    public function apply(Game $game, array $userPricesByIndex, bool $isDefault = false): SeasonTicketPricing
    {
        if (! $isDefault && ! $this->canEdit($game)) {
            throw new \DomainException('season_tickets.locked');
        }

        $payload = $this->buildFromUserPrices($game, $game->team, $userPricesByIndex);

        return DB::transaction(function () use ($game, $payload, $isDefault) {
            $pricing = SeasonTicketPricing::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'season' => (int) $game->season,
                ],
                [
                    'areas' => $payload['areas'],
                    'total_capacity' => $payload['total_capacity'],
                    'total_sold' => $payload['total_sold'],
                    'total_revenue' => $payload['total_revenue'],
                    'is_default' => $isDefault,
                ],
            );

            $this->syncFinances($game, $pricing->total_revenue);

            return $pricing;
        });
    }

    /**
     * Apply default pricing for a game/season, but only if the user hasn't
     * already priced manually. Idempotent — safe to call from the season
     * setup pipeline and as a fallback before the first match.
     */
    public function applyDefaultIfMissing(Game $game): ?SeasonTicketPricing
    {
        $existing = $this->getCurrent($game);
        if ($existing) {
            return $existing;
        }

        $defaults = $this->buildDefaultPricing($game, $game->team);
        $userPrices = array_map(fn ($a) => $a['price_cents'], $defaults['areas']);

        return $this->apply($game, $userPrices, isDefault: true);
    }

    public function getCurrent(Game $game): ?SeasonTicketPricing
    {
        return SeasonTicketPricing::where('game_id', $game->id)
            ->where('season', $game->season)
            ->first();
    }

    /**
     * Total season tickets sold for the user's home fixture, used as the
     * matchday attendance floor. Returns 0 for non-user-team fixtures or
     * when no pricing row exists yet.
     */
    public function soldSeasonTicketsForMatch(Game $game, GameMatch $match): int
    {
        if ($match->home_team_id !== $game->team_id) {
            return 0;
        }

        $pricing = $this->getCurrent($game);

        return $pricing?->total_sold ?? 0;
    }

    /**
     * Number of season ticket holders for the user's team in the current
     * season — used by budget projections and settlement to subtract from
     * per-fixture attendance before the walk-up matchday revenue formula.
     */
    public function soldSeasonTicketsForGame(Game $game): int
    {
        $pricing = $this->getCurrent($game);

        return $pricing?->total_sold ?? 0;
    }

    /**
     * @param  array<int, array{slug: string, capacity: int, baseline_price_cents: int, multiplier: float}>  $areas
     * @param  array<int, int>  $userPrices
     * @return array{areas: array<int, array<string, mixed>>, total_capacity: int, total_sold: int, total_revenue: int}
     */
    private function predictAndCompose(array $areas, array $userPrices, TeamReputation $homeRep): array
    {
        $loyaltyFill = $this->loyaltyFillRate($homeRep);

        $totalCapacity = 0;
        $totalSold = 0;
        $totalRevenue = 0;
        $composed = [];

        foreach ($areas as $i => $area) {
            $price = $userPrices[$i];
            $baseline = $area['baseline_price_cents'];
            $priceRatio = $baseline > 0 ? $price / $baseline : 1.0;

            // Premium tiers (vip/palco) sell less elastically — they have a
            // smaller, more committed audience that doesn't churn over price
            // tweaks the same way the general terraces do.
            $isPremium = $area['multiplier'] >= self::PREMIUM_MULTIPLIER_THRESHOLD;
            $baseFill = $isPremium ? max(0.55, $loyaltyFill - 0.10) : $loyaltyFill;

            $priceFactor = $this->priceFactor($priceRatio, $isPremium);
            $fillRate = max(0.0, min(0.98, $baseFill * $priceFactor));

            $sold = (int) round($area['capacity'] * $fillRate);
            $revenue = $sold * $price;

            $composed[] = [
                'slug' => $area['slug'],
                'capacity' => $area['capacity'],
                'baseline_price_cents' => $baseline,
                'price_cents' => $price,
                'multiplier' => $area['multiplier'],
                'sold' => $sold,
                'fill_rate' => round($fillRate, 4),
                'revenue' => $revenue,
            ];

            $totalCapacity += $area['capacity'];
            $totalSold += $sold;
            $totalRevenue += $revenue;
        }

        return [
            'areas' => $composed,
            'total_capacity' => $totalCapacity,
            'total_sold' => $totalSold,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * Map loyalty (0-100) to a base season-ticket subscription rate. A
     * club with average loyalty (~50) sells roughly 75% of capacity at
     * baseline prices; loyalty 100 pushes near-sellout, loyalty 0 floors
     * at 50%.
     */
    private function loyaltyFillRate(TeamReputation $homeRep): float
    {
        $normalised = max(0, min(100, (int) $homeRep->loyalty_points)) / 100.0;

        return 0.50 + $normalised * 0.45;
    }

    /**
     * Demand response to price moves relative to baseline. Free / heavy
     * discounts give a small bump (capped) while hikes shed fans quickly.
     * Premium tiers respond less sharply to price changes.
     */
    private function priceFactor(float $priceRatio, bool $isPremium): float
    {
        $sensitivity = $isPremium ? 0.6 : 1.0;
        $factor = 1.0 + ($sensitivity * (1.0 - $priceRatio));

        return max(0.20, min(1.10, $factor));
    }

    private function clampPrice(int $baseline, int $proposed): int
    {
        $min = (int) round($baseline * self::MIN_PRICE_MULTIPLIER);
        $max = (int) round($baseline * self::MAX_PRICE_MULTIPLIER);

        return max($min, min($max, $proposed));
    }

    /**
     * @return array{areas: array<int, array<string, mixed>>}
     */
    private function resolveTier(int $capacity): array
    {
        foreach (self::TIERS as $tier) {
            if ($capacity <= $tier['max_capacity']) {
                return $tier;
            }
        }

        return self::TIERS[count(self::TIERS) - 1];
    }

    /**
     * Mirror MatchAttendanceService's reputation-loading fallback so the
     * fill prediction works for newly created games where the per-game
     * reputation row may not exist yet.
     */
    private function resolveReputation(string $gameId, string $teamId, string $reputationLevel): TeamReputation
    {
        $rep = TeamReputation::where('game_id', $gameId)
            ->where('team_id', $teamId)
            ->first();

        if ($rep) {
            return $rep;
        }

        $profile = ClubProfile::where('team_id', $teamId)->first();
        $loyalty = (int) (($profile->fan_loyalty ?? ClubProfile::FAN_LOYALTY_DEFAULT) * 10);

        $synthetic = new TeamReputation();
        $synthetic->game_id = $gameId;
        $synthetic->team_id = $teamId;
        $synthetic->reputation_level = $reputationLevel;
        $synthetic->base_reputation_level = $reputationLevel;
        $synthetic->reputation_points = TeamReputation::pointsForTier($reputationLevel);
        $synthetic->base_loyalty = $loyalty;
        $synthetic->loyalty_points = $loyalty;

        return $synthetic;
    }

    /**
     * Whether any league fixture for the user's team has already been
     * played this season. Cup ties (cup_tie_id IS NOT NULL) and pre-season
     * friendlies don't count — the deadline is the first competitive
     * league round.
     */
    private function hasPlayedLeagueMatch(Game $game): bool
    {
        return GameMatch::where('game_id', $game->id)
            ->where('competition_id', $game->competition_id)
            ->where(function ($q) use ($game) {
                $q->where('home_team_id', $game->team_id)
                    ->orWhere('away_team_id', $game->team_id);
            })
            ->where('played', true)
            ->exists();
    }

    /**
     * Reflect season ticket revenue on the current season's GameFinances.
     * Season tickets are pre-paid at season start, so projected and actual
     * stay locked together — no variance row is generated.
     */
    private function syncFinances(Game $game, int $revenue): void
    {
        $finances = $game->currentFinances;
        if (! $finances) {
            return;
        }

        $previous = (int) ($finances->projected_season_ticket_revenue ?? 0);
        $delta = $revenue - $previous;

        $finances->projected_season_ticket_revenue = $revenue;
        $finances->actual_season_ticket_revenue = $revenue;
        $finances->projected_total_revenue = (int) $finances->projected_total_revenue + $delta;
        if ((int) $finances->actual_total_revenue > 0) {
            $finances->actual_total_revenue = (int) $finances->actual_total_revenue + $delta;
        }
        $finances->projected_surplus = (int) $finances->projected_surplus + $delta;
        $finances->save();
    }
}
