<?php

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameMatch;
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
    ) {}

    /**
     * @return array{
     *   stadium_name: ?string,
     *   capacity: int,
     *   loyalty_points: int,
     *   base_loyalty: int,
     *   loyalty_direction: 'rising'|'stable'|'declining',
     *   last_home_match: ?array{
     *     match: GameMatch,
     *     attendance: int,
     *     capacity_at_match: int,
     *     fill_rate: int,
     *   },
     *   finances: ?GameFinances,
     *   season_tickets: array{
     *     can_edit: bool,
     *     pricing: ?SeasonTicketPricing,
     *     areas: array<int, array<string, mixed>>,
     *     baseline_areas: array<int, array{slug: string, capacity: int, baseline_price_cents: int, multiplier: float}>,
     *     overall_fill_rate: int,
     *     min_price_multiplier: float,
     *     max_price_multiplier: float,
     *   },
     * }
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
        $seasonTickets = $this->resolveSeasonTickets($game);

        return [
            'stadium_name' => $team->stadium_name,
            'capacity' => (int) ($team->stadium_seats ?? 0),
            'loyalty_points' => $loyaltyPoints,
            'base_loyalty' => $baseLoyalty,
            'loyalty_direction' => $direction,
            'last_home_match' => $lastHomeMatch,
            'finances' => $game->currentFinances,
            'season_tickets' => $seasonTickets,
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

        return [
            'match' => $match,
            'attendance' => (int) $attendance->attendance,
            'capacity_at_match' => (int) $attendance->capacity_at_match,
            'fill_rate' => $attendance->fillRatePercent(),
        ];
    }

    /**
     * Build the payload feeding the season ticket pricing UI block.
     * Returns the persisted pricing row when one exists, plus the area
     * layout (so the schematic always has something to render) and the
     * editable flag.
     */
    private function resolveSeasonTickets(Game $game): array
    {
        $pricing = $this->seasonTicketPricingService->getCurrent($game);
        $reputation = TeamReputation::resolveLevel($game->id, $game->team_id);
        $capacity = (int) ($game->team->stadium_seats ?? 0);
        $baselineAreas = $this->seasonTicketPricingService->buildAreas($capacity, $reputation);

        // Areas with persisted sold/fill, or computed defaults so the
        // schematic still renders before the first save.
        if ($pricing) {
            $areas = $pricing->areas;
            $overallFill = $pricing->fillRatePercent();
        } else {
            $defaults = $this->seasonTicketPricingService->buildDefaultPricing($game, $game->team);
            $areas = $defaults['areas'];
            $overallFill = $defaults['total_capacity'] > 0
                ? (int) round(($defaults['total_sold'] / $defaults['total_capacity']) * 100)
                : 0;
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
