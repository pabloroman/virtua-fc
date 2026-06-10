<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameMatch;
use App\Models\SeasonTicketPricing;
use App\Models\Team;
use App\Models\TeamReputation;

/**
 * Resolves seating area layouts, baseline prices, and predicted demand for
 * the user's season ticket sale. The schematic on the stadium page reads
 * the same area definitions returned here so labels, capacity proportions,
 * and visual ordering stay in sync.
 *
 * Pricing is a single per-season decision: the manager picks one global
 * pricing preset (Accessible / Standard / Premium) that scales every area's
 * baseline price uniformly. Discrete presets — not free-number per-area
 * sliders — keep the choice legible and the demand model un-gameable, and
 * let the page precompute every preset's outcome server-side so the UI needs
 * no live preview round-trip.
 *
 * This service only persists the season-ticket sale (the pricing row). It
 * never writes finances — the budget projection reads the persisted holder
 * count one-directionally (BudgetProjectionService::refreshTicketingProjection),
 * so Stadium never depends on Finance.
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
     * Area slugs identify premium tiers (vip/palco) and are persisted on the
     * pricing row as an audit trail; they are no longer surfaced in the UI.
     */
    public const TIERS = [
        // Tiny ground (Pontevedra-scale)
        [
            'max_capacity' => 15_000,
            'areas' => [
                ['slug' => 'general', 'share' => 0.75, 'multiplier' => 1.00, 'premium' => false],
                ['slug' => 'tribuna', 'share' => 0.25, 'multiplier' => 2.00, 'premium' => false],
            ],
        ],
        // Small ground — VIP first appears here.
        [
            'max_capacity' => 30_000,
            'areas' => [
                ['slug' => 'general', 'share' => 0.45, 'multiplier' => 1.00, 'premium' => false],
                ['slug' => 'lateral', 'share' => 0.28, 'multiplier' => 1.50, 'premium' => false],
                ['slug' => 'tribuna', 'share' => 0.22, 'multiplier' => 2.10, 'premium' => false],
                ['slug' => 'vip',     'share' => 0.05, 'multiplier' => 4.00, 'premium' => true],
            ],
        ],
        // Mid-size ground — split ends.
        [
            'max_capacity' => 50_000,
            'areas' => [
                ['slug' => 'fondo_norte', 'share' => 0.23, 'multiplier' => 1.00, 'premium' => false],
                ['slug' => 'fondo_sur',   'share' => 0.23, 'multiplier' => 1.00, 'premium' => false],
                ['slug' => 'lateral',     'share' => 0.30, 'multiplier' => 1.55, 'premium' => false],
                ['slug' => 'tribuna',     'share' => 0.19, 'multiplier' => 2.20, 'premium' => false],
                ['slug' => 'vip',         'share' => 0.05, 'multiplier' => 4.30, 'premium' => true],
            ],
        ],
        // Large ground — stand levels (alta/baja) and palco appear.
        // _alta = larger upper deck, cheaper; _baja = smaller lower deck,
        // closer to the pitch and pricier.
        [
            'max_capacity' => 70_000,
            'areas' => [
                ['slug' => 'fondo_norte',  'share' => 0.20, 'multiplier' => 1.00, 'premium' => false],
                ['slug' => 'fondo_sur',    'share' => 0.20, 'multiplier' => 1.00, 'premium' => false],
                ['slug' => 'lateral_alta', 'share' => 0.15, 'multiplier' => 1.35, 'premium' => false],
                ['slug' => 'lateral_baja', 'share' => 0.13, 'multiplier' => 1.85, 'premium' => false],
                ['slug' => 'tribuna_alta', 'share' => 0.13, 'multiplier' => 1.95, 'premium' => false],
                ['slug' => 'tribuna_baja', 'share' => 0.10, 'multiplier' => 2.60, 'premium' => false],
                ['slug' => 'vip',          'share' => 0.06, 'multiplier' => 4.50, 'premium' => true],
                ['slug' => 'palco',        'share' => 0.03, 'multiplier' => 8.00, 'premium' => true],
            ],
        ],
        // Iconic stadium (Bernabéu / Camp Nou).
        [
            'max_capacity' => PHP_INT_MAX,
            'areas' => [
                ['slug' => 'fondo_norte',  'share' => 0.18, 'multiplier' => 1.00, 'premium' => false],
                ['slug' => 'fondo_sur',    'share' => 0.18, 'multiplier' => 1.00, 'premium' => false],
                ['slug' => 'lateral_alta', 'share' => 0.15, 'multiplier' => 1.40, 'premium' => false],
                ['slug' => 'lateral_baja', 'share' => 0.13, 'multiplier' => 1.95, 'premium' => false],
                ['slug' => 'tribuna_alta', 'share' => 0.13, 'multiplier' => 2.05, 'premium' => false],
                ['slug' => 'tribuna_baja', 'share' => 0.11, 'multiplier' => 2.70, 'premium' => false],
                ['slug' => 'vip',          'share' => 0.07, 'multiplier' => 4.80, 'premium' => true],
                ['slug' => 'palco',        'share' => 0.05, 'multiplier' => 10.00, 'premium' => true],
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

    /** The preset applied when the user hasn't chosen one. */
    public const DEFAULT_PRESET = 'standard';

    /** Per-request memoisation of getCurrent(). */
    private array $currentCache = [];

    public function __construct(
        private readonly GameStadiumResolver $stadiumResolver,
    ) {}

    /**
     * The available pricing presets as `key => global price multiplier`.
     *
     * @return array<string, float>
     */
    public function presets(): array
    {
        $presets = (array) config('stadium.season_ticket_presets', [self::DEFAULT_PRESET => 1.0]);

        return array_map(fn ($m) => (float) $m, $presets);
    }

    /**
     * The global price multiplier for a preset, falling back to the default
     * preset (then 1.0) for an unknown key.
     */
    public function presetMultiplier(string $preset): float
    {
        $presets = $this->presets();

        return $presets[$preset] ?? $presets[self::DEFAULT_PRESET] ?? 1.0;
    }

    /**
     * Build the seating layout for a stadium of the given capacity.
     * Returns a list of areas with absolute capacity (proportions of the
     * stadium total) and the default price per ticket in cents.
     *
     * @return array<int, array{slug: string, capacity: int, baseline_price_cents: int, multiplier: float, is_premium: bool}>
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
                'is_premium' => (bool) ($def['premium'] ?? false),
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
     * Compose the pricing payload for a team at a given preset. Used at season
     * setup (default preset), to precompute every preset's outcome for the UI,
     * and as the persisted payload.
     *
     * @return array{areas: array<int, array<string, mixed>>, total_capacity: int, total_sold: int, total_revenue: int}
     */
    public function buildFromPreset(Game $game, Team $team, string $preset): array
    {
        $reputation = TeamReputation::resolveLevel($game->id, $team->id);
        $homeRep = $this->resolveReputation($game->id, $team->id, $reputation);
        $capacity = $this->stadiumResolver->effectiveCapacity(
            $game->id,
            $team->id,
            (int) ($team->stadium_seats ?? 0),
        );

        $areas = $this->buildAreas($capacity, $reputation);

        return $this->predictAndCompose($areas, $this->presetMultiplier($preset), $homeRep);
    }

    /**
     * The default (Standard preset) pricing payload — used at season setup and
     * as the form's initial values when the user hasn't priced yet.
     *
     * @return array{areas: array<int, array<string, mixed>>, total_capacity: int, total_sold: int, total_revenue: int}
     */
    public function buildDefaultPricing(Game $game, Team $team): array
    {
        return $this->buildFromPreset($game, $team, self::DEFAULT_PRESET);
    }

    /**
     * Predicted payload for a preset including the overall fill rate. Used by
     * the read side to precompute every preset's outcome for the selector.
     *
     * @return array{
     *   areas: array<int, array<string, mixed>>,
     *   total_capacity: int,
     *   total_sold: int,
     *   total_revenue: int,
     *   overall_fill_rate: int,
     * }
     */
    public function predictForPreset(Game $game, Team $team, string $preset): array
    {
        $payload = $this->buildFromPreset($game, $team, $this->normalisePreset($preset));
        $payload['overall_fill_rate'] = SeasonTicketPricing::fillRateFor(
            $payload['total_sold'],
            $payload['total_capacity'],
        );

        return $payload;
    }

    /**
     * Whether the user can still set or change the season-ticket preset for
     * the current season. Locked once any league fixture for the user's team
     * has been played — cup ties and pre-season friendlies don't count, so
     * the user has the full pre-season window to decide.
     */
    public function canEdit(Game $game): bool
    {
        return ! $this->hasPlayedLeagueMatch($game);
    }

    /**
     * Persist the user's chosen preset for the current season. Throws if the
     * lock has triggered (defence-in-depth — the UI hides the form, but the
     * action also re-checks). Persists only the season-ticket sale; the budget
     * is refreshed separately by the caller via
     * BudgetProjectionService::refreshTicketingProjection.
     */
    public function apply(Game $game, string $preset, bool $isDefault = false): SeasonTicketPricing
    {
        if (! $isDefault && ! $this->canEdit($game)) {
            throw new \DomainException('season_tickets.locked');
        }

        $preset = $this->normalisePreset($preset);
        $payload = $this->buildFromPreset($game, $game->team, $preset);

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
                'pricing_preset' => $preset,
                'is_default' => $isDefault,
            ],
        );

        $this->currentCache[$game->id . ':' . (int) $game->season] = $pricing;

        return $pricing;
    }

    /**
     * Apply the default preset for a game/season, but only if the user hasn't
     * already priced. Idempotent — safe to call from the season setup pipeline
     * and as a fallback before the first match.
     */
    public function applyDefaultIfMissing(Game $game): ?SeasonTicketPricing
    {
        $existing = $this->getCurrent($game);
        if ($existing) {
            return $existing;
        }

        return $this->apply($game, self::DEFAULT_PRESET, isDefault: true);
    }

    public function getCurrent(Game $game): ?SeasonTicketPricing
    {
        $key = $game->id . ':' . (int) $game->season;

        if (! array_key_exists($key, $this->currentCache)) {
            $this->currentCache[$key] = SeasonTicketPricing::where('game_id', $game->id)
                ->where('season', $game->season)
                ->first();
        }

        return $this->currentCache[$key];
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
     * season — subtracted from per-fixture attendance before the walk-up
     * matchday revenue formula by both budget projection and settlement, so
     * holders (who paid up front) are never counted twice.
     */
    public function soldSeasonTicketsForGame(Game $game): int
    {
        $pricing = $this->getCurrent($game);

        return $pricing?->total_sold ?? 0;
    }

    /**
     * @param  array<int, array{slug: string, capacity: int, baseline_price_cents: int, multiplier: float, is_premium: bool}>  $areas
     * @return array{areas: array<int, array<string, mixed>>, total_capacity: int, total_sold: int, total_revenue: int}
     */
    private function predictAndCompose(array $areas, float $priceMultiplier, TeamReputation $homeRep): array
    {
        $loyaltyFill = $this->loyaltyFillRate($homeRep);
        $penetrationRatio = $this->penetrationRatio($homeRep);

        $totalCapacity = 0;
        $totalSold = 0;
        $totalRevenue = 0;
        $composed = [];

        foreach ($areas as $area) {
            $baseline = $area['baseline_price_cents'];
            $price = (int) round($baseline * $priceMultiplier);

            // Premium tiers (vip/palco) sell less elastically — they have a
            // smaller, more committed audience that doesn't churn over price
            // tweaks the same way the general terraces do.
            $isPremium = $this->isPremiumArea($area);

            // Hold abono penetration BELOW attendance demand so a walk-up gate
            // survives — the gap is widest at low loyalty and tapers to ~0 for
            // elites (who sell out via abonos). See penetrationRatio().
            $baseFill = ($isPremium ? max(0.55, $loyaltyFill - 0.10) : $loyaltyFill) * $penetrationRatio;

            $priceFactor = $this->priceFactor($priceMultiplier, $isPremium);
            $fillRate = max(0.0, min(0.98, $baseFill * $priceFactor));

            $sold = (int) round($area['capacity'] * $fillRate);
            $revenue = $sold * $price;

            $composed[] = [
                'slug' => $area['slug'],
                'capacity' => $area['capacity'],
                'baseline_price_cents' => $baseline,
                'price_cents' => $price,
                'multiplier' => $area['multiplier'],
                'is_premium' => $isPremium,
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
     * Map loyalty (0-100) to the base season-ticket capture rate. This curve
     * intentionally mirrors attendance demand (DemandCurveService::baseFillRate
     * uses the same 0.50 + loyalty/100 × 0.45 shape); penetrationRatio() then
     * scales it DOWN so abonos sit below total match demand and leave a walk-up
     * gate. A club with average loyalty (~50) captures ~75% before that
     * scaling; loyalty 100 → near-sellout, loyalty 0 → 50%.
     */
    private function loyaltyFillRate(TeamReputation $homeRep): float
    {
        $normalised = max(0, min(100, (int) $homeRep->loyalty_points)) / 100.0;

        return 0.50 + $normalised * 0.45;
    }

    /**
     * Fraction of the loyalty fill that converts to season tickets, holding
     * abono penetration BELOW attendance demand so a walk-up gate survives.
     * The reserved gap is widest at zero loyalty (config
     * stadium.season_ticket_walkup_reserve_max) and tapers linearly to 0 at
     * full loyalty — elite clubs saturate via abonos and draw little walk-up,
     * exactly as a sold-out marquee ground behaves.
     *
     * Without this, season-ticket demand and attendance demand share the same
     * loyalty curve, so holders ≈ demand for every club and the projected
     * walk-up gate (max(0, demand − holders)) collapses to ~0 across the board.
     */
    private function penetrationRatio(TeamReputation $homeRep): float
    {
        $normalised = max(0, min(100, (int) $homeRep->loyalty_points)) / 100.0;
        $reserveMax = (float) config('stadium.season_ticket_walkup_reserve_max', 0.15);

        return 1.0 - $reserveMax * (1.0 - $normalised);
    }

    /**
     * Demand response to the chosen preset relative to baseline. Accessible
     * pricing (multiplier < 1) gives a small fill bump (capped); premium
     * pricing (> 1) sheds fans. Premium tiers respond less sharply.
     */
    private function priceFactor(float $priceRatio, bool $isPremium): float
    {
        $sensitivity = $isPremium ? 0.6 : 1.0;
        $factor = 1.0 + ($sensitivity * (1.0 - $priceRatio));

        return max(0.20, min(1.10, $factor));
    }

    /**
     * Whether an area is a premium tier (VIP/palco). Reads the persisted
     * `is_premium` flag set at composition time. Falls back to checking
     * the slug for legacy rows written before the flag existed.
     *
     * @param  array<string, mixed>  $area
     */
    private function isPremiumArea(array $area): bool
    {
        if (array_key_exists('is_premium', $area)) {
            return (bool) $area['is_premium'];
        }

        return in_array($area['slug'] ?? null, ['vip', 'palco'], true);
    }

    /**
     * Coerce an arbitrary preset key to a known one, falling back to the
     * default. Keeps a stale or hand-crafted form submission from persisting
     * an unknown preset.
     */
    private function normalisePreset(string $preset): string
    {
        return array_key_exists($preset, $this->presets()) ? $preset : self::DEFAULT_PRESET;
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
}
