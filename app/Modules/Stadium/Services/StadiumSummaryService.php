<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameMatch;
use App\Models\GameStadium;
use App\Models\MatchAttendance;
use App\Models\SeasonTicketPricing;
use App\Models\TeamReputation;

/**
 * Shapes the data shown on the Club > Stadium page: capacity, fan-loyalty
 * stat, most-recent home-match attendance, current season's projected vs.
 * actual matchday revenue, and the season ticket pricing/preview block.
 * Pure read-side service — no writes, no side effects.
 */
class StadiumSummaryService
{
    public function __construct(
        private readonly SeasonTicketPricingService $seasonTicketPricingService,
        private readonly GameStadiumNameResolver $stadiumNameResolver,
    ) {}

    /**
     * Returns a flat, view-ready payload for the stadium page. Every value
     * is pre-computed here so the Blade template carries no business logic
     * and no @php blocks. The shape mirrors the view's named variables.
     *
     * @return array<string, mixed>
     */
    public function build(Game $game): array
    {
        $team = $game->team;

        $reputation = TeamReputation::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();

        $loyaltyPoints = $reputation?->loyalty_points ?? 0;
        $baseLoyalty = $reputation?->base_loyalty ?? 0;

        // Match the 5-point band used by the reputation-direction hint for
        // consistency across the Club hub. Below that band loyalty is
        // considered "stable" even with small cosmetic drifts.
        $loyaltyDelta = $loyaltyPoints - $baseLoyalty;
        $direction = $loyaltyDelta > 5 ? 'rising' : ($loyaltyDelta < -5 ? 'declining' : 'stable');

        $lastHomeMatch = $this->resolveLastHomeMatch($game);

        // Per-game stadium row captures upgrades (rebuild + supletorias).
        // Falls back to the control-plane baseline for saves that predate
        // the GameStadium backfill migration.
        $stadium = GameStadium::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->first();
        $capacity = $stadium?->effective_capacity ?? (int) ($team->stadium_seats ?? 0);
        $uefaLevel = $stadium?->effective_uefa_level ?? $team->uefa_stadium_category;

        $seasonTickets = $this->resolveSeasonTickets($game, $capacity);

        $finances = $game->currentFinances;
        $projectedMatchday = (int) ($finances?->projected_matchday_revenue ?? 0);
        $actualMatchday = (int) ($finances?->actual_matchday_revenue ?? 0);
        $hasActualMatchday = $actualMatchday > 0;

        $pricing = $seasonTickets['pricing'];
        $ticketAreas = $seasonTickets['areas'];
        $baselineAreas = $seasonTickets['baseline_areas'];
        $minMultiplier = $seasonTickets['min_price_multiplier'];
        $maxMultiplier = $seasonTickets['max_price_multiplier'];

        // Alpine seed for the season-ticket editor — per-area current price
        // + baseline. The component re-fetches predictions from the server,
        // so this only needs to cover the first render. Per-area slider
        // bounds (min/max price in cents) are also stamped onto each area
        // so the template doesn't recompute them at render time.
        $alpinePrices = [];
        $alpineBaselines = [];
        foreach ($ticketAreas as $i => $area) {
            $alpinePrices[$i] = (int) ($area['price_cents'] ?? $area['baseline_price_cents']);
            $alpineBaselines[$i] = (int) ($area['baseline_price_cents'] ?? $alpinePrices[$i]);

            $baselineCents = (int) ($baselineAreas[$i]['baseline_price_cents'] ?? $area['baseline_price_cents']);
            $ticketAreas[$i]['min_price_cents'] = (int) round($baselineCents * $minMultiplier);
            $ticketAreas[$i]['max_price_cents'] = (int) round($baselineCents * $maxMultiplier);
        }

        // Locked-state aggregates (rendered when season tickets are no
        // longer editable for the season).
        $lockedSeasonTicketRevenue = (int) ($pricing?->total_revenue ?? 0);
        $lockedMatchdayRevenue = $hasActualMatchday ? $actualMatchday : $projectedMatchday;
        $lockedTotalRevenue = $lockedSeasonTicketRevenue + $lockedMatchdayRevenue;

        return [
            'stadiumName' => $this->stadiumNameResolver->effectiveName($game->id, $game->team_id, $team->stadium_name),
            'capacity' => $capacity,
            'uefaLevel' => $uefaLevel,
            'loyaltyPoints' => $loyaltyPoints,
            'baseLoyalty' => $baseLoyalty,
            'loyaltyDirection' => $direction,
            'lastHomeMatch' => $lastHomeMatch,
            'finances' => $finances,
            'projectedMatchday' => $projectedMatchday,
            'actualMatchday' => $actualMatchday,
            'hasActualMatchday' => $hasActualMatchday,
            'canEditTickets' => $seasonTickets['can_edit'],
            'pricing' => $pricing,
            'ticketAreas' => $ticketAreas,
            'baselineAreas' => $baselineAreas,
            'overallFill' => $seasonTickets['overall_fill_rate'],
            'minMultiplier' => $seasonTickets['min_price_multiplier'],
            'maxMultiplier' => $seasonTickets['max_price_multiplier'],
            'alpinePrices' => $alpinePrices,
            'alpineBaselines' => $alpineBaselines,
            'lockedSeasonTicketRevenue' => $lockedSeasonTicketRevenue,
            'lockedMatchdayRevenue' => $lockedMatchdayRevenue,
            'lockedTotalRevenue' => $lockedTotalRevenue,
        ];
    }

    /**
     * Most recent played home match that also has a persisted attendance
     * row. Returns null until the team has played its first home fixture of
     * the save (pre-season / new-game state).
     */
    private function resolveLastHomeMatch(Game $game): ?array
    {
        $match = GameMatch::query()
            ->where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', true)
            ->whereIn('id', MatchAttendance::query()->select('game_match_id')->where('game_id', $game->id))
            ->with(['awayTeam', 'competition'])
            ->orderByDesc('scheduled_date')
            ->first();

        if (!$match) {
            return null;
        }

        $attendance = MatchAttendance::where('game_match_id', $match->id)->first();

        if (!$attendance) {
            return null;
        }

        $fillRate = $attendance->fillRatePercent();

        // Pre-computed fill-rate accent so the template doesn't carry the
        // banding logic. Bands roughly match a "great / good / soft / bad"
        // attendance scale.
        $fillColor = $fillRate >= 90 ? 'bg-accent-green'
            : ($fillRate >= 70 ? 'bg-accent-blue'
            : ($fillRate >= 50 ? 'bg-accent-gold' : 'bg-accent-red'));

        return [
            'match' => $match,
            'attendance' => (int) $attendance->attendance,
            'capacity_at_match' => (int) $attendance->capacity_at_match,
            'fill_rate' => $fillRate,
            'fill_color' => $fillColor,
        ];
    }

    /**
     * Build the payload feeding the season ticket pricing UI block.
     * Returns the persisted pricing row when one exists, plus the area
     * layout (so the schematic always has something to render) and the
     * editable flag.
     */
    private function resolveSeasonTickets(Game $game, int $capacity): array
    {
        $pricing = $this->seasonTicketPricingService->getCurrent($game);
        $reputation = TeamReputation::resolveLevel($game->id, $game->team_id);
        $baselineAreas = $this->seasonTicketPricingService->buildAreas($capacity, $reputation);

        // Areas with persisted sold/fill, or computed defaults so the
        // schematic still renders before the first save.
        if ($pricing) {
            $areas = $pricing->areas;
            $overallFill = $pricing->fillRatePercent();
        } else {
            $defaults = $this->seasonTicketPricingService->buildDefaultPricing($game, $game->team);
            $areas = $defaults['areas'];
            $overallFill = SeasonTicketPricing::fillRateFor(
                $defaults['total_sold'],
                $defaults['total_capacity'],
            );
        }

        return [
            'can_edit' => $this->seasonTicketPricingService->canEdit($game),
            'pricing' => $pricing,
            'areas' => $areas,
            'baseline_areas' => $baselineAreas,
            'overall_fill_rate' => $overallFill,
            'min_price_multiplier' => SeasonTicketPricingService::MIN_PRICE_MULTIPLIER,
            'max_price_multiplier' => SeasonTicketPricingService::MAX_PRICE_MULTIPLIER,
        ];
    }
}
