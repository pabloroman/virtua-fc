<?php

namespace App\Modules\Stadium\Services;

use App\Models\Game;
use App\Models\GameFinances;
use App\Models\GameMatch;
use App\Models\GameStadium;
use App\Models\MatchAttendance;
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
        private readonly GameStadiumResolver $stadiumResolver,
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

        $seasonTickets = $this->resolveSeasonTickets($game);

        $finances = $game->currentFinances;
        $projectedMatchday = (int) ($finances?->projected_matchday_revenue ?? 0);
        $actualMatchday = (int) ($finances?->actual_matchday_revenue ?? 0);
        $hasActualMatchday = $actualMatchday > 0;

        $pricing = $seasonTickets['pricing'];

        // Locked-state aggregates (rendered when season tickets are no
        // longer editable for the season).
        $lockedSeasonTicketRevenue = (int) ($pricing?->total_revenue ?? 0);
        $lockedMatchdayRevenue = $hasActualMatchday ? $actualMatchday : $projectedMatchday;
        $lockedTotalRevenue = $lockedSeasonTicketRevenue + $lockedMatchdayRevenue;

        return [
            'stadiumName' => $this->stadiumResolver->effectiveName($game->id, $game->team_id, $team->stadium_name),
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
            'currentPreset' => $seasonTickets['current_preset'],
            'ticketPresets' => $seasonTickets['presets'],
            'overallFill' => $seasonTickets['overall_fill'],
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
     * Build the payload feeding the season ticket pricing UI block. The
     * editor offers a discrete pricing preset, so every preset's aggregate
     * outcome (fill, sold, season-ticket revenue) is precomputed here — the
     * front end toggles between them with no server round-trip. The per-area
     * breakdown is intentionally omitted: prices are a single global preset,
     * so the stand-by-stand schematic carried no user decision.
     *
     * @return array{
     *   can_edit: bool,
     *   pricing: \App\Models\SeasonTicketPricing|null,
     *   current_preset: string,
     *   overall_fill: int,
     *   presets: array<string, array<string, mixed>>,
     * }
     */
    private function resolveSeasonTickets(Game $game): array
    {
        $pricing = $this->seasonTicketPricingService->getCurrent($game);
        $currentPreset = $pricing->pricing_preset ?? SeasonTicketPricingService::DEFAULT_PRESET;

        $presets = [];
        foreach (array_keys($this->seasonTicketPricingService->presets()) as $key) {
            $payload = $this->seasonTicketPricingService->predictForPreset($game, $game->team, $key);

            $presets[$key] = [
                'key' => $key,
                'total_sold' => $payload['total_sold'],
                'total_capacity' => $payload['total_capacity'],
                'total_revenue' => $payload['total_revenue'],
                'overall_fill' => $payload['overall_fill_rate'],
                // Lets the client scale demand per preset so the live occupancy
                // and taquilla figures respond to the pricing stance.
                'occupancy_factor' => $this->seasonTicketPricingService->occupancyFactor($key),
            ];
        }

        // Initial fill reflects the persisted preset (the editor's default
        // selection); the locked view reads the persisted row's own fill.
        $overallFill = $pricing
            ? $pricing->fillRatePercent()
            : ($presets[$currentPreset]['overall_fill'] ?? 0);

        return [
            'can_edit' => $this->seasonTicketPricingService->canEdit($game),
            'pricing' => $pricing,
            'current_preset' => $currentPreset,
            'overall_fill' => $overallFill,
            'presets' => $presets,
        ];
    }
}
