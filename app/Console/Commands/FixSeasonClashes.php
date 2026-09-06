<?php

namespace App\Console\Commands;

use App\Modules\Competition\Services\CountryConfig;
use App\Support\FixtureCalendar;
use App\Support\SeasonData;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Move the rounds that book a club twice on one date.
 *
 * `app:validate-season` reports these; this resolves them. It exists because
 * the alternative is what happened last time — hand-editing `schedule.json`
 * until the one clash you were looking at went away, with nothing checking
 * whether the new date collided with something else.
 *
 * Only *certain* collisions are moved: two fields both fully known from the
 * files. The possible ones depend on a draw, so a fix would be guesswork.
 *
 * The move policy, in order:
 *   1. Never move a continental date. The UEFA calendar is real, and it is
 *      shared by every country's data — shifting it to suit one league breaks
 *      the others.
 *   2. Prefer moving a knockout round. Cups have weeks between rounds; a
 *      league round sits in a weekly rhythm with nine other fixtures on it.
 *   3. Otherwise move the league round, which is what `177c77d` did by hand.
 *
 * A candidate date has to be free for every club in the round being moved,
 * stay strictly between that round's neighbours, and ideally keep the
 * competition's usual weekday. After each move the whole season is re-checked,
 * so a fix cannot quietly create the next clash.
 */
class FixSeasonClashes extends Command
{
    protected $signature = 'app:fix-season-clashes
                            {season : Season to repair (e.g. 2026)}
                            {--apply : Write the moves to disk (otherwise only reported)}';

    protected $description = 'Reschedule rounds that book a club for two matches on the same date';

    /** How far either side of a clashing date to look for a free one. */
    private const SEARCH_DAYS = 7;

    /** Guards against a pathological data set looping forever. */
    private const MAX_MOVES = 100;

    public function handle(CountryConfig $countryConfig): int
    {
        $season = (string) $this->argument('season');

        if (!is_dir(base_path("data/{$season}"))) {
            $this->error("Season folder not found: " . base_path("data/{$season}"));

            return self::FAILURE;
        }

        /** @var array<string, array<string, mixed>> $schedules */
        $schedules = [];
        $moves = [];
        $unresolved = [];

        for ($i = 0; $i < self::MAX_MOVES; $i++) {
            $collision = $this->nextCertainCollision($season, $countryConfig, $schedules, $unresolved);
            if ($collision === null) {
                break;
            }

            $move = $this->planMove($season, $countryConfig, $collision, $schedules);
            if ($move === null) {
                $unresolved[$this->key($collision)] = $collision;

                continue;
            }

            $schedules[$move['competition']] = $this->withDate(
                $schedules[$move['competition']] ?? FixtureCalendar::schedule($season, $move['competition']) ?? [],
                $move,
            );
            $moves[] = $move;
        }

        return $this->report($season, $moves, $unresolved, $schedules);
    }

    /**
     * The next certain collision that hasn't already defeated us.
     *
     * @param  array<string, array<string, mixed>>  $schedules
     * @param  array<string, array<string, mixed>>  $unresolved
     * @return array<string, mixed>|null
     */
    private function nextCertainCollision(string $season, CountryConfig $countryConfig, array $schedules, array $unresolved): ?array
    {
        foreach (FixtureCalendar::collisions($season, $countryConfig, $schedules) as $collision) {
            if ($collision['certain'] && !isset($unresolved[$this->key($collision)])) {
                return $collision;
            }
        }

        return null;
    }

    /**
     * Decide which round to move and where to, or null when nothing can be.
     *
     * @param  array<string, mixed>  $collision
     * @param  array<string, array<string, mixed>>  $schedules
     * @return array{competition: string, section: string, round: int, leg: string, from: string, to: string, clubs: int}|null
     */
    private function planMove(string $season, CountryConfig $countryConfig, array $collision, array $schedules): ?array
    {
        $types = $this->competitionTypes($season, $countryConfig);
        $bookings = FixtureCalendar::bookings($season, $countryConfig, $schedules);

        $candidates = array_filter(
            $collision['rounds'],
            fn (array $r): bool => ($types[$r['competition']] ?? '') !== 'continental',
        );

        // Knockout rounds first: they have the slack, and moving one disturbs
        // a single tie-round rather than a whole matchweek.
        usort($candidates, function (array $a, array $b) use ($types): int {
            $rank = fn (array $r): int => ($types[$r['competition']] ?? '') === 'cup' ? 0 : 1;

            return [$rank($a), $a['competition']] <=> [$rank($b), $b['competition']];
        });

        foreach ($candidates as $round) {
            $schedule = $schedules[$round['competition']]
                ?? FixtureCalendar::schedule($season, $round['competition'])
                ?? [];

            $to = $this->findFreeDate($schedule, $round, $collision['date'], $bookings);
            if ($to !== null) {
                return [
                    'competition' => $round['competition'],
                    'section' => $this->sectionOf($schedule, $round),
                    'round' => $round['round'],
                    'leg' => $round['leg'],
                    'from' => $collision['date'],
                    'to' => $to,
                    'clubs' => count($collision['clubs']),
                ];
            }
        }

        return null;
    }

    /**
     * The nearest date that is free for every club in this round, keeps the
     * round between its neighbours, and preferably falls on the weekday the
     * competition usually plays.
     *
     * @param  array<string, mixed>  $schedule
     * @param  array{competition: string, round: int, leg: string}  $round
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $bookings
     */
    private function findFreeDate(array $schedule, array $round, string $from, array $bookings): ?string
    {
        $field = $this->clubsPlaying($bookings, $from, $round);
        if ($field === []) {
            return null;
        }

        [$after, $before] = $this->neighbours($schedule, $round, $from);
        $preferredWeekday = $this->modalWeekday($schedule, $this->sectionOf($schedule, $round));
        $origin = CarbonImmutable::parse($from);

        $offsets = range(1, self::SEARCH_DAYS);
        $candidates = [];
        foreach ($offsets as $offset) {
            foreach ([$offset, -$offset] as $signed) {
                $candidates[] = $origin->addDays($signed);
            }
        }

        // Nearest first, but a date on the competition's usual weekday beats a
        // marginally nearer one — a Ligue 1 round belongs on a weekend.
        usort($candidates, function (CarbonImmutable $a, CarbonImmutable $b) use ($origin, $preferredWeekday): int {
            $rank = fn (CarbonImmutable $d): array => [
                $preferredWeekday !== null && $d->dayOfWeek === $preferredWeekday ? 0 : 1,
                abs($d->diffInDays($origin)),
            ];

            return $rank($a) <=> $rank($b);
        });

        foreach ($candidates as $candidate) {
            $date = $candidate->toDateString();

            if (($after !== null && $date <= $after) || ($before !== null && $date >= $before)) {
                continue;
            }

            if ($this->anyBooked($bookings, $date, $field)) {
                continue;
            }

            return $date;
        }

        return null;
    }

    /**
     * The clubs this round has booked on the clashing date.
     *
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $bookings
     * @param  array{competition: string, round: int, leg: string}  $round
     * @return array<int, string>
     */
    private function clubsPlaying(array $bookings, string $date, array $round): array
    {
        $clubs = [];
        foreach ($bookings[$date] ?? [] as $clubId => $entries) {
            foreach ($entries as $entry) {
                if ($entry['competition'] === $round['competition']
                    && $entry['round'] === $round['round']
                    && $entry['leg'] === $round['leg']) {
                    $clubs[] = (string) $clubId;
                    break;
                }
            }
        }

        return $clubs;
    }

    /**
     * @param  array<string, array<string, array<int, array<string, mixed>>>>  $bookings
     * @param  array<int, string>  $clubs
     */
    private function anyBooked(array $bookings, string $date, array $clubs): bool
    {
        foreach ($clubs as $clubId) {
            if (!empty($bookings[$date][$clubId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The dates this round has to stay between: the latest date before it and
     * the earliest after it, within the same section of the same competition.
     * A second leg must also stay behind its first.
     *
     * @param  array<string, mixed>  $schedule
     * @param  array{round: int, leg: string}  $round
     * @return array{0: string|null, 1: string|null}
     */
    private function neighbours(array $schedule, array $round, string $from): array
    {
        $section = $this->sectionOf($schedule, $round);
        $after = null;
        $before = null;

        foreach ($schedule[$section] ?? [] as $entry) {
            foreach (['date', 'first_leg_date', 'second_leg_date'] as $leg) {
                $date = $entry[$leg] ?? null;
                if (!$date) {
                    continue;
                }

                $isSelf = (int) ($entry['round'] ?? 0) === $round['round'] && $leg === $round['leg'];
                if ($isSelf) {
                    continue;
                }

                $sameRound = (int) ($entry['round'] ?? 0) === $round['round'];
                $isEarlier = $sameRound ? $leg === 'first_leg_date' : (int) ($entry['round'] ?? 0) < $round['round'];

                if ($isEarlier) {
                    $after = $after === null ? $date : max($after, $date);
                } else {
                    $before = $before === null ? $date : min($before, $date);
                }
            }
        }

        return [$after, $before];
    }

    /**
     * The weekday a competition's section usually plays on, so a moved round
     * lands back in its rhythm rather than on whichever day was free first.
     *
     * @param  array<string, mixed>  $schedule
     */
    private function modalWeekday(array $schedule, string $section): ?int
    {
        $counts = [];
        foreach ($schedule[$section] ?? [] as $entry) {
            foreach (['date', 'first_leg_date', 'second_leg_date'] as $leg) {
                if (!empty($entry[$leg])) {
                    $day = CarbonImmutable::parse($entry[$leg])->dayOfWeek;
                    $counts[$day] = ($counts[$day] ?? 0) + 1;
                }
            }
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return (int) array_key_first($counts);
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array{round: int, leg: string}  $round
     */
    private function sectionOf(array $schedule, array $round): string
    {
        foreach (['league', 'knockout'] as $section) {
            foreach ($schedule[$section] ?? [] as $entry) {
                if ((int) ($entry['round'] ?? 0) === $round['round'] && isset($entry[$round['leg']])) {
                    return $section;
                }
            }
        }

        return 'knockout';
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array{section: string, round: int, leg: string, to: string}  $move
     * @return array<string, mixed>
     */
    private function withDate(array $schedule, array $move): array
    {
        foreach ($schedule[$move['section']] ?? [] as $index => $entry) {
            if ((int) ($entry['round'] ?? 0) === $move['round'] && isset($entry[$move['leg']])) {
                $schedule[$move['section']][$index][$move['leg']] = $move['to'];
                break;
            }
        }

        return $schedule;
    }

    /**
     * Competition code => the type SeasonData assigned it, so the move policy
     * can tell a cup from a league from an untouchable continental calendar.
     *
     * @return array<string, string>
     */
    private function competitionTypes(string $season, CountryConfig $countryConfig): array
    {
        return array_column(SeasonData::competitions($countryConfig, $season), 'type', 'code');
    }

    /** @param  array<string, mixed>  $collision */
    private function key(array $collision): string
    {
        return $collision['date'] . '|' . implode('|', array_map(
            fn (array $r): string => "{$r['competition']}#{$r['round']}#{$r['leg']}",
            $collision['rounds'],
        ));
    }

    /**
     * @param  array<int, array{competition: string, round: int, leg: string, from: string, to: string, clubs: int}>  $moves
     * @param  array<string, array<string, mixed>>  $unresolved
     * @param  array<string, array<string, mixed>>  $schedules
     */
    private function report(string $season, array $moves, array $unresolved, array $schedules): int
    {
        if ($moves === [] && $unresolved === []) {
            $this->info("No club is booked twice on one date in data/{$season}.");

            return self::SUCCESS;
        }

        if ($moves !== []) {
            $this->info(($this->option('apply') ? 'Applied' : 'Proposed') . ' ' . count($moves) . ' move(s):');
            $this->table(
                ['competition', 'round', 'leg', 'from', 'to', 'clubs freed'],
                array_map(fn (array $m): array => [
                    $m['competition'],
                    $m['round'],
                    $m['leg'] === 'second_leg_date' ? '2nd' : ($m['leg'] === 'first_leg_date' ? '1st' : '—'),
                    $m['from'],
                    $m['to'],
                    $m['clubs'],
                ], $moves),
            );
        }

        foreach ($unresolved as $collision) {
            $rounds = implode(' and ', array_map(
                fn (array $r): string => "{$r['competition']} round {$r['round']}",
                $collision['rounds'],
            ));
            $this->warn("  ⚠ {$collision['date']}: {$rounds} — no free date within "
                . self::SEARCH_DAYS . ' days that keeps the round between its neighbours. Move it by hand.');
        }

        if (!$this->option('apply')) {
            $this->newLine();
            $this->line('Re-run with --apply to write these to data/' . $season . '.');

            return $unresolved === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($schedules as $code => $schedule) {
            $path = base_path("data/{$season}/{$code}/schedule.json");
            file_put_contents($path, json_encode(
                $schedule,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n");
            $this->line("  wrote data/{$season}/{$code}/schedule.json");
        }

        return $unresolved === [] ? self::SUCCESS : self::FAILURE;
    }
}
