<?php

namespace App\Modules\Stadium\Services;

use App\Models\Game;
use App\Models\GameStadiumNamingDeal;

/**
 * Mints naming-rights offers: fresh sponsor bids (createOffer), incumbent
 * renewals (createRenewalOffer), and the deterministic fee a pre-existing
 * deal starts on (tierMidpointValue).
 *
 * Each fresh offer is priced independently — an annual base drawn within the
 * club's reputation band, scaled by a term multiplier so a longer deal pays
 * less per season. Offers are plausible standalone sponsor bids rather than
 * points engineered onto one shared tradeoff curve.
 */
class NamingOfferFactory
{
    /**
     * Mint one pending sponsor offer: a brand eligible for the club's tier and
     * market that isn't already on the board, an annual fee drawn within the
     * tier band, and a term within the configured bounds (longer term ⇒ lower
     * annual fee). Returns null when every eligible brand is already pending.
     */
    public function createOffer(Game $game, string $tier): ?GameStadiumNamingDeal
    {
        $season = (int) $game->season;

        $sponsor = $this->pickAvailableSponsor($game, $season, $tier);
        if ($sponsor === null) {
            return null;
        }

        [$min, $max] = $this->tierBand($tier);
        $term = $this->pickTerm();

        return GameStadiumNamingDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'sponsor_name' => $sponsor['name'],
            'proposed_stadium_name' => $sponsor['stadium'],
            'annual_value_cents' => $this->annualForTerm(random_int($min, $max), $term),
            'contract_seasons' => $term,
            'is_renewal' => false,
            'status' => GameStadiumNamingDeal::STATUS_PENDING,
            'offered_season' => $season,
        ]);
    }

    /**
     * Mint a free renewal offer for the incumbent sponsor when a deal expires:
     * same sponsor and stadium name, a fresh fee re-priced from the club's
     * current reputation tier, and a one-season term so the decision returns
     * each pre-season. Flagged is_renewal so acceptance skips the loyalty shock
     * (the name does not change). No agency fee or cooldown — keeping a sponsor
     * you already have costs nothing; shopping for a different one still goes
     * through the paid seek flow.
     */
    public function createRenewalOffer(Game $game, GameStadiumNamingDeal $expired, string $tier): GameStadiumNamingDeal
    {
        $season = (int) $game->season;

        [$minValue, $maxValue] = $this->tierBand($tier);

        $seasons = (int) config('commercial.naming_rights.renewal_seasons', 1);

        return GameStadiumNamingDeal::create([
            'game_id' => $game->id,
            'team_id' => $game->team_id,
            'sponsor_name' => $expired->sponsor_name,
            'proposed_stadium_name' => $expired->proposed_stadium_name,
            'annual_value_cents' => random_int((int) $minValue, (int) $maxValue),
            'contract_seasons' => $seasons,
            'is_renewal' => true,
            'status' => GameStadiumNamingDeal::STATUS_PENDING,
            'offered_season' => $season,
        ]);
    }

    /**
     * Deterministic fee for a tier: the midpoint of the tier's annual-value
     * band. Used to price pre-existing deals a club starts the game with — no
     * per-club value is authored, so the fee simply tracks club stature.
     */
    public function tierMidpointValue(string $tier): int
    {
        [$min, $max] = $this->tierBand($tier);

        return intdiv($min + $max, 2);
    }

    /**
     * The [min, max] annual-value band for a tier (cents), falling back to the
     * local band for an unmapped tier so callers never read an empty range.
     *
     * @return array{0: int, 1: int}
     */
    private function tierBand(string $tier): array
    {
        $range = config("commercial.naming_rights.annual_value.{$tier}")
            ?? config('commercial.naming_rights.annual_value.local', [50_000_00, 200_000_00]);

        return [(int) $range[0], (int) $range[1]];
    }

    /**
     * The annual-fee multiplier for a contract length: 1.0 at one season (the
     * band is the headline rate), discounting toward longer terms so a multi-year
     * deal pays less per season. An unmapped term defaults to 1.0 (no discount).
     */
    private function termMultiplier(int $seasons): float
    {
        $curve = (array) config('commercial.naming_rights.term_value_multiplier', []);

        return (float) ($curve[$seasons] ?? 1.0);
    }

    /**
     * The annual fee for a term: the drawn base scaled by the term's
     * multiplier. Longer term ⇒ lower annual fee.
     */
    private function annualForTerm(int $base, int $seasons): int
    {
        return (int) round($base * $this->termMultiplier($seasons));
    }

    /**
     * A random contract length within the configured bounds.
     */
    private function pickTerm(): int
    {
        $min = (int) config('commercial.naming_rights.min_contract_seasons', 1);
        $max = (int) config('commercial.naming_rights.max_contract_seasons', 5);

        return random_int($min, $max);
    }

    /**
     * Pick a sponsor brand eligible for this club that isn't already pending
     * this pre-season, so competing offers never duplicate the same name. Two
     * gates narrow the pool:
     *   1. `reach` must bid for the club's tier (a regional brewer won't chase
     *      a superclub; a global giant won't bother with a third-tier ground).
     *      An unmapped tier skips this gate so the board never silently starves.
     *   2. a non-global brand only operates in its home market, so it can only
     *      name a ground in its own country; global brands name grounds anywhere
     *      (Emirates, Coca-Cola) and carry no `country`.
     * Null when no eligible brand is left.
     *
     * @return array{name: string, reach: string, country?: string, stadium: string}|null
     */
    private function pickAvailableSponsor(Game $game, int $season, string $tier): ?array
    {
        $sponsors = config('commercial.naming_rights.sponsors', []);
        if (empty($sponsors)) {
            return null;
        }

        $eligibleReaches = (array) config("commercial.naming_rights.sponsor_reach_by_tier.{$tier}", []);
        $country = $game->country;
        $sponsors = array_filter($sponsors, function (array $sponsor) use ($eligibleReaches, $country) {
            $reach = $sponsor['reach'] ?? null;

            if (! empty($eligibleReaches) && ! in_array($reach, $eligibleReaches, true)) {
                return false;
            }

            // Global brands are country-agnostic; everyone else is home-market only.
            return $reach === 'global' || ($sponsor['country'] ?? null) === $country;
        });

        $taken = GameStadiumNamingDeal::query()
            ->where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->where('status', GameStadiumNamingDeal::STATUS_PENDING)
            ->where('offered_season', $season)
            ->pluck('sponsor_name')
            ->all();

        $available = array_values(array_filter(
            $sponsors,
            fn (array $sponsor) => ! in_array($sponsor['name'], $taken, true),
        ));

        if (empty($available)) {
            return null;
        }

        return $available[random_int(0, count($available) - 1)];
    }
}
