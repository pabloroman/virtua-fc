<?php

namespace App\Support;

use App\Modules\Competition\Services\CountryConfig;

/**
 * Reads a season folder as a calendar of "which clubs are booked on which
 * date", so a club scheduled twice on one day can be found before the data is
 * ever seeded.
 *
 * This is the bug class `177c77d` fixed by hand: ESP1 and ENG1 both had a
 * midweek round on 2025-09-24, which is matchday 1 of the Europa League and
 * the Conference League, so every Spanish or English club in either was booked
 * twice that day. Nothing catches it at runtime — MatchdayService collects
 * every unplayed match on the earliest date and the orchestrator takes the
 * first as the user's, so the second is simulated in the same batch on the
 * same legs, with no warning anywhere.
 *
 * Database-free by construction: the season-data workflow runs the validator
 * against an unmigrated database, so everything here comes from `data/**` and
 * `config/countries.php`.
 *
 * How confidently a booking is known splits three ways, and only the first is
 * worth failing a build over:
 *
 *  - CERTAIN. A round-robin league round pairs every club exactly once, and a
 *    Swiss league phase plays all 36 on every matchday, so the whole
 *    `teams.json` is booked on that date. A domestic cup's *opening* round is
 *    equally certain: its field is whoever declares that entry round.
 *  - POSSIBLE. From a cup's second round on, the field is last round's winners
 *    — decided by a draw at runtime, not by the file. Continental knockout
 *    rounds are seeded from league-phase standings, so not even their first
 *    round is known. The file gives a superset, so a shared date is a risk
 *    rather than a fact.
 *  - UNKNOWABLE, and deliberately not modelled: a supercup from season 2 on
 *    (its field is last season's champion and cup winner), promotion playoffs
 *    (ESP3PO has no `teams.json` at all), the Coppa Italia's byes once they
 *    are re-derived from a final table, and pre-season friendlies (dates live
 *    in PreseasonOpponentService, and the opponent is the user's choice).
 *    Catching those needs a simulated save, not a data gate.
 */
class FixtureCalendar
{
    /**
     * Every booking this season folder declares, keyed by date then club id.
     *
     * `$scheduleOverrides` replaces a competition's schedule.json with an
     * in-memory one, keyed by competition code, so app:fix-season-clashes can
     * try a move and re-check without writing to disk.
     *
     * @param  array<string, array<string, mixed>>  $scheduleOverrides
     * @return array<string, array<string, array<int, array{competition: string, round: int, name: string, leg: string, certain: bool, slots: int, club: string}>>>
     */
    public static function bookings(
        string $season,
        CountryConfig $countryConfig,
        array $scheduleOverrides = [],
    ): array
    {
        $bookings = [];

        foreach (SeasonData::competitions($countryConfig, $season) as ['code' => $code, 'type' => $type]) {
            // Pools hold no fixtures, and a bare playoff has a schedule but no
            // teams.json to say who turns up for it.
            if (!in_array($type, ['league', 'cup', 'continental'], true)) {
                continue;
            }

            $clubs = SeasonData::readCompetitionClubs($season, $code, $type);
            $schedule = $scheduleOverrides[$code] ?? self::schedule($season, $code);
            if (empty($clubs) || $schedule === null) {
                continue;
            }

            $skipAhead = self::supercupSkipAhead($season, $code, $countryConfig);
            $slots = self::knockoutSlots($clubs, $schedule, $skipAhead);

            foreach (self::rounds($schedule, $type) as $round) {
                foreach (self::fieldFor($clubs, $round, $skipAhead) as $club) {
                    foreach ($round['dates'] as $leg => $date) {
                        $bookings[$date][$club['id']][] = [
                            'competition' => $code,
                            'round' => $round['round'],
                            'name' => $round['name'],
                            'leg' => $leg,
                            'certain' => $round['certain'],
                            'slots' => $round['section'] === 'league'
                                ? count($clubs)
                                : ($slots[$round['round']] ?? count($clubs)),
                            'club' => $club['name'],
                        ];
                    }
                }
            }
        }

        return $bookings;
    }

    /**
     * Clubs booked twice on one date, grouped into the collision that caused
     * it — one league round meeting one cup round is a single scheduling
     * mistake however many clubs it catches, and reporting it per club buries
     * the signal.
     *
     * A collision is `certain` when at least two of its bookings are: two
     * fields that are both fully known cannot avoid each other.
     *
     * @param  array<string, array<string, mixed>>  $scheduleOverrides
     * @return array<int, array{date: string, certain: bool, rounds: array<int, array{competition: string, round: int, name: string, leg: string, certain: bool, slots: int}>, slots: int, clubs: array<int, string>}>
     */
    public static function collisions(
        string $season,
        CountryConfig $countryConfig,
        array $scheduleOverrides = [],
    ): array
    {
        $collisions = [];

        foreach (self::bookings($season, $countryConfig, $scheduleOverrides) as $date => $clubs) {
            foreach ($clubs as $entries) {
                if (count($entries) < 2) {
                    continue;
                }

                // Key on the rounds involved so every club caught by the same
                // pair of rounds folds into one collision.
                $key = $date . '|' . implode('|', array_map(
                    fn (array $e): string => "{$e['competition']}#{$e['round']}#{$e['leg']}",
                    $entries,
                ));

                $collisions[$key] ??= [
                    'date' => $date,
                    'certain' => count(array_filter($entries, fn (array $e): bool => $e['certain'])) >= 2,
                    'rounds' => array_map(
                        fn (array $e): array => array_diff_key($e, ['club' => null]),
                        $entries,
                    ),
                    // The narrowest round bounds the damage: a cup final and a
                    // league round share a date, but only two clubs can be in
                    // both however many could have got there.
                    'slots' => min(array_column($entries, 'slots')),
                    'clubs' => [],
                ];
                $collisions[$key]['clubs'][] = $entries[0]['club'];
            }
        }

        // Certain collisions first, then chronologically: the ones that have
        // to be fixed lead the report.
        uasort($collisions, function (array $a, array $b): int {
            return [$b['certain'], $a['date']] <=> [$a['certain'], $b['date']];
        });

        return array_values($collisions);
    }

    /**
     * Read a competition's schedule.json, or null when it has none.
     *
     * @return array<string, mixed>|null
     */
    public static function schedule(string $season, string $code): ?array
    {
        $path = base_path("data/{$season}/{$code}/schedule.json");
        if (!file_exists($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Flatten a schedule into rounds carrying every date they occupy — a
     * league round is one date, a knockout round may be two legs on two.
     *
     * A knockout round is only `certain` when it is a domestic cup's opening
     * round, where the declared field is exactly who turns up. Later rounds
     * are last round's winners, and a continental knockout is seeded from
     * league-phase standings, so neither is known from the file.
     *
     * @param  array<string, mixed>  $schedule
     * @return array<int, array{section: string, round: int, name: string, dates: array<string, string>, certain: bool}>
     */
    public static function rounds(array $schedule, string $type = 'cup'): array
    {
        $out = [];
        $openingRound = self::openingKnockoutRound($schedule);

        foreach (['league', 'knockout'] as $section) {
            foreach ($schedule[$section] ?? [] as $round) {
                if (!is_array($round)) {
                    continue;
                }

                $dates = [];
                foreach (['date', 'first_leg_date', 'second_leg_date'] as $key) {
                    if (!empty($round[$key])) {
                        $dates[$key] = (string) $round[$key];
                    }
                }

                if ($dates === []) {
                    continue;
                }

                $number = (int) ($round['round'] ?? 0);

                $out[] = [
                    'section' => $section,
                    'round' => $number,
                    'name' => (string) ($round['name'] ?? ''),
                    'dates' => $dates,
                    'certain' => $section === 'league'
                        || ($type === 'cup' && $number === $openingRound),
                ];
            }
        }

        return $out;
    }

    /**
     * The lowest knockout round in a schedule — the one a cup's declared field
     * actually turns up for.
     *
     * @param  array<string, mixed>  $schedule
     */
    private static function openingKnockoutRound(array $schedule): ?int
    {
        $rounds = array_map(
            fn (array $r): int => (int) ($r['round'] ?? 0),
            array_filter($schedule['knockout'] ?? [], 'is_array'),
        );

        return $rounds === [] ? null : min($rounds);
    }

    /**
     * How many clubs each knockout round actually fields, by walking the
     * halvings: a round starts with the last one's winners plus whoever enters
     * at it. The same arithmetic validateBracketParity uses, for the same
     * reason — it is the only way to know that a final is two clubs and not
     * the eighty that could have reached it.
     *
     * @param  array<int, array{id: string, entryRound: int}>  $clubs
     * @param  array<string, mixed>  $schedule
     * @param  array<string, int>  $skipAhead
     * @return array<int, int>
     */
    private static function knockoutSlots(array $clubs, array $schedule, array $skipAhead): array
    {
        $entrants = [];
        foreach ($clubs as $club) {
            $round = $skipAhead[$club['id']] ?? $club['entryRound'];
            $entrants[$round] = ($entrants[$round] ?? 0) + 1;
        }

        $rounds = array_map(
            fn (array $r): int => (int) ($r['round'] ?? 0),
            array_filter($schedule['knockout'] ?? [], 'is_array'),
        );
        sort($rounds);

        $slots = [];
        $survivors = 0;
        foreach ($rounds as $round) {
            $field = $survivors + ($entrants[$round] ?? 0);
            $slots[$round] = $field;
            $survivors = intdiv($field, 2);
        }

        return $slots;
    }

    /**
     * Which clubs a round books. A league round or Swiss matchday books
     * everyone; a knockout round books whoever could still be alive for it,
     * which from the file is everyone who entered at or before it.
     *
     * @param  array<int, array{id: string, name: string, entryRound: int}>  $clubs
     * @param  array{section: string, round: int}  $round
     * @param  array<string, int>  $skipAhead
     * @return array<int, array{id: string, name: string}>
     */
    private static function fieldFor(array $clubs, array $round, array $skipAhead): array
    {
        if ($round['section'] === 'league') {
            return $clubs;
        }

        return array_values(array_filter(
            $clubs,
            fn (array $club): bool => ($skipAhead[$club['id']] ?? $club['entryRound']) <= $round['round'],
        ));
    }

    /**
     * Where a country's supercup field joins its main cup. Spain's four
     * Supercopa clubs skip to the Copa's round of 32, so they are not in its
     * opening round at all — CupEntryRoundService applies the same bump at
     * season setup, and without it a correctly built cup reads as a clash.
     *
     * @return array<string, int> club id => the round it really enters at
     */
    private static function supercupSkipAhead(string $season, string $cupCode, CountryConfig $countryConfig): array
    {
        foreach ($countryConfig->allCountryCodes() as $countryCode) {
            $supercup = $countryConfig->supercup($countryCode, $season);
            if (($supercup['cup'] ?? null) !== $cupCode) {
                continue;
            }

            $skipToRound = (int) ($supercup['cup_entry_round'] ?? 0);
            if ($skipToRound < 2) {
                return [];
            }

            $field = SeasonData::readCompetitionClubs($season, $supercup['competition'], 'cup') ?? [];

            return array_fill_keys(array_column($field, 'id'), $skipToRound);
        }

        return [];
    }
}
