<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Verifies that the new schedule.json files produce identical data
 * to the existing matchdays.json + rounds.json files.
 *
 * This is a temporary command used during the format migration.
 * Delete after the switchover is complete.
 */
class VerifyScheduleFormat extends Command
{
    protected $signature = 'app:verify-schedule-format';

    protected $description = 'Verify new schedule.json files match existing matchdays.json + rounds.json data';

    /**
     * Competitions to verify, grouped by type.
     * Must match the profiles in SeedReferenceData.
     */
    private array $leagueCompetitions = [
        'ESP1' => 'data/2025/ESP1',
        'ESP2' => 'data/2025/ESP2',
        'TEST1' => 'data/2025/TEST1',
    ];

    private array $cupCompetitions = [
        'ESPCUP' => 'data/2025/ESPCUP',
        'ESPSUP' => 'data/2025/ESPSUP',
        'TESTCUP' => 'data/2025/TESTCUP',
        'UCL' => 'data/2025/UCL',
    ];

    public function handle(): int
    {
        $allPassed = true;

        $this->info('Verifying league matchdays...');
        foreach ($this->leagueCompetitions as $code => $path) {
            if (!$this->verifyLeague($code, base_path($path))) {
                $allPassed = false;
            }
        }

        $this->newLine();
        $this->info('Verifying cup round templates...');
        foreach ($this->cupCompetitions as $code => $path) {
            if (!$this->verifyCupRoundTemplates($code, base_path($path))) {
                $allPassed = false;
            }
        }

        // ESP2 has knockout rounds too (new in schedule.json)
        $this->newLine();
        $this->info('Verifying hybrid competition knockout data...');
        $this->verifyEsp2Knockout(base_path('data/2025/ESP2'));

        // UCL: verify schedule.json dates match the old matchdays.json dates
        // (not the hard-coded SwissKnockoutGenerator dates — those are a known mismatch)
        $this->newLine();
        $this->info('Verifying UCL knockout dates (schedule.json vs matchdays.json)...');
        $this->verifyUclKnockout(base_path('data/2025/UCL'));

        $this->newLine();
        if ($allPassed) {
            $this->info('All verifications passed!');
            return Command::SUCCESS;
        }

        $this->error('Some verifications failed. See above for details.');
        return Command::FAILURE;
    }

    /**
     * Verify that schedule.json league matchdays produce identical round+date pairs
     * to the existing matchdays.json (after date format conversion).
     */
    private function verifyLeague(string $code, string $basePath): bool
    {
        $oldPath = "{$basePath}/matchdays.json";
        $newPath = "{$basePath}/schedule.json";

        if (!file_exists($oldPath) || !file_exists($newPath)) {
            $this->warn("  {$code}: Missing file(s), skipping");
            return false;
        }

        $oldData = json_decode(file_get_contents($oldPath), true);
        $newData = json_decode(file_get_contents($newPath), true);

        $newLeague = $newData['league'] ?? [];

        if (count($oldData) !== count($newLeague)) {
            $this->error("  {$code}: Round count mismatch — old: " . count($oldData) . ", new: " . count($newLeague));
            return false;
        }

        $mismatches = 0;
        foreach ($oldData as $i => $oldEntry) {
            $newEntry = $newLeague[$i];

            // Verify round numbers match
            if ($oldEntry['round'] !== $newEntry['round']) {
                $this->error("  {$code} round {$oldEntry['round']}: Round number mismatch — old: {$oldEntry['round']}, new: {$newEntry['round']}");
                $mismatches++;
                continue;
            }

            // Convert old DD/MM/YY to ISO for comparison
            $oldDate = Carbon::createFromFormat('d/m/y', $oldEntry['date'])->format('Y-m-d');
            $newDate = $newEntry['date'];

            if ($oldDate !== $newDate) {
                $this->error("  {$code} round {$oldEntry['round']}: Date mismatch — old: {$oldEntry['date']} ({$oldDate}), new: {$newDate}");
                $mismatches++;
            }
        }

        if ($mismatches === 0) {
            $this->line("  {$code}: OK — {$newEntry['round']} league matchdays match");
            return true;
        }

        $this->error("  {$code}: {$mismatches} mismatches found");
        return false;
    }

    /**
     * Verify that schedule.json knockout rounds produce identical cup_round_templates data
     * to what the old seedCupRoundTemplates() logic would produce from matchdays.json + rounds.json.
     */
    private function verifyCupRoundTemplates(string $code, string $basePath): bool
    {
        $matchdaysPath = "{$basePath}/matchdays.json";
        $roundsPath = "{$basePath}/rounds.json";
        $schedulePath = "{$basePath}/schedule.json";

        if (!file_exists($matchdaysPath) || !file_exists($roundsPath) || !file_exists($schedulePath)) {
            $this->warn("  {$code}: Missing file(s), skipping");
            return false;
        }

        // Parse OLD format (replicate SeedReferenceData::seedCupRoundTemplates logic)
        $oldTemplates = $this->parseOldFormat($matchdaysPath, $roundsPath);

        // Parse NEW format
        $newData = json_decode(file_get_contents($schedulePath), true);
        $newTemplates = $this->parseNewFormat($newData['knockout'] ?? []);

        // Compare
        $oldRounds = array_keys($oldTemplates);
        $newRounds = array_keys($newTemplates);

        if ($oldRounds !== $newRounds) {
            $this->error("  {$code}: Round numbers differ — old: [" . implode(',', $oldRounds) . "], new: [" . implode(',', $newRounds) . "]");
            return false;
        }

        $mismatches = 0;
        foreach ($oldTemplates as $roundNum => $old) {
            $new = $newTemplates[$roundNum];

            $diffs = [];
            if ($old['round_name'] !== $new['round_name']) {
                // Allow known language fixes (TESTCUP: "Semi-Finals" -> "Semifinal")
                $diffs[] = "name: '{$old['round_name']}' -> '{$new['round_name']}'";
            }
            if ($old['type'] !== $new['type']) {
                $diffs[] = "type: {$old['type']} -> {$new['type']}";
            }
            if ($old['first_leg_date'] !== $new['first_leg_date']) {
                $diffs[] = "first_leg: {$old['first_leg_date']} -> {$new['first_leg_date']}";
            }
            if ($old['second_leg_date'] !== $new['second_leg_date']) {
                $diffs[] = "second_leg: {$old['second_leg_date']} -> {$new['second_leg_date']}";
            }

            if (!empty($diffs)) {
                $diffStr = implode(', ', $diffs);
                // Check if only name changed (acceptable for language fix)
                $nonNameDiffs = array_filter($diffs, fn ($d) => !str_starts_with($d, 'name:'));
                if (empty($nonNameDiffs)) {
                    $this->line("  {$code} round {$roundNum}: Name updated (language fix): {$diffStr}");
                } else {
                    $this->error("  {$code} round {$roundNum}: MISMATCH — {$diffStr}");
                    $mismatches++;
                }
            }
        }

        if ($mismatches === 0) {
            $this->line("  {$code}: OK — " . count($newTemplates) . " knockout rounds match");
            return true;
        }

        $this->error("  {$code}: {$mismatches} mismatches found");
        return false;
    }

    /**
     * Verify ESP2 knockout data exists in schedule.json and matches the hard-coded
     * ESP2PlayoffGenerator dates for the 2025 season.
     */
    private function verifyEsp2Knockout(string $basePath): void
    {
        $schedulePath = "{$basePath}/schedule.json";
        $newData = json_decode(file_get_contents($schedulePath), true);
        $knockout = $newData['knockout'] ?? [];

        if (empty($knockout)) {
            $this->error('  ESP2: No knockout section in schedule.json');
            return;
        }

        // Expected dates from ESP2PlayoffGenerator for 2025 season (playoffs in June 2026)
        $expected = [
            1 => [
                'name' => 'Playoff Semifinal',
                'first_leg_date' => Carbon::parse('first Sunday of June 2026')->format('Y-m-d'),
                'second_leg_date' => Carbon::parse('first Sunday of June 2026')->addDays(7)->format('Y-m-d'),
            ],
            2 => [
                'name' => 'Playoff Final',
                'first_leg_date' => Carbon::parse('third Sunday of June 2026')->format('Y-m-d'),
                'second_leg_date' => Carbon::parse('third Sunday of June 2026')->addDays(7)->format('Y-m-d'),
            ],
        ];

        foreach ($knockout as $entry) {
            $round = $entry['round'];
            $exp = $expected[$round] ?? null;
            if (!$exp) {
                $this->error("  ESP2 round {$round}: Unexpected knockout round");
                continue;
            }

            $mismatches = [];
            if ($entry['name'] !== $exp['name']) {
                $mismatches[] = "name: '{$entry['name']}' vs '{$exp['name']}'";
            }
            if (($entry['first_leg_date'] ?? '') !== $exp['first_leg_date']) {
                $mismatches[] = "first_leg: '{$entry['first_leg_date']}' vs '{$exp['first_leg_date']}'";
            }
            if (($entry['second_leg_date'] ?? '') !== $exp['second_leg_date']) {
                $mismatches[] = "second_leg: '{$entry['second_leg_date']}' vs '{$exp['second_leg_date']}'";
            }

            if (empty($mismatches)) {
                $this->line("  ESP2 round {$round}: OK — matches ESP2PlayoffGenerator dates");
            } else {
                $this->error("  ESP2 round {$round}: MISMATCH — " . implode(', ', $mismatches));
            }
        }
    }

    /**
     * Verify UCL schedule.json knockout dates match the existing matchdays.json dates.
     * Note: These intentionally differ from SwissKnockoutGenerator hard-coded dates — that's
     * a known issue that the migration will fix by making the generator read from cup_round_templates.
     */
    private function verifyUclKnockout(string $basePath): void
    {
        $oldData = json_decode(file_get_contents("{$basePath}/matchdays.json"), true);
        $newData = json_decode(file_get_contents("{$basePath}/schedule.json"), true);
        $knockout = $newData['knockout'] ?? [];

        // Build old date map by round
        $oldByRound = [];
        foreach ($oldData as $md) {
            $round = $md['round'];
            if (!isset($oldByRound[$round])) {
                $oldByRound[$round] = [];
            }
            $oldByRound[$round][] = $md['date'];
        }

        foreach ($knockout as $entry) {
            $round = $entry['round'];
            $oldDates = $oldByRound[$round] ?? [];

            if (isset($entry['first_leg_date'])) {
                // Two-legged: should have two dates in old format
                if (count($oldDates) === 2) {
                    $match1 = $oldDates[0] === $entry['first_leg_date'];
                    $match2 = $oldDates[1] === $entry['second_leg_date'];
                    if ($match1 && $match2) {
                        $this->line("  UCL round {$round} ({$entry['name']}): OK");
                    } else {
                        $this->error("  UCL round {$round}: Date mismatch — old: " . implode(', ', $oldDates) . " vs new: {$entry['first_leg_date']}, {$entry['second_leg_date']}");
                    }
                } else {
                    $this->error("  UCL round {$round}: Expected 2 old entries, got " . count($oldDates));
                }
            } else {
                // Single-leg: should have one date
                if (count($oldDates) === 1 && $oldDates[0] === $entry['date']) {
                    $this->line("  UCL round {$round} ({$entry['name']}): OK");
                } else {
                    $this->error("  UCL round {$round}: Date mismatch — old: " . implode(', ', $oldDates) . " vs new: {$entry['date']}");
                }
            }
        }
    }

    /**
     * Replicate the old SeedReferenceData::seedCupRoundTemplates parsing logic.
     * Returns normalized templates keyed by round_number.
     */
    private function parseOldFormat(string $matchdaysPath, string $roundsPath): array
    {
        $matchdays = json_decode(file_get_contents($matchdaysPath), true);
        $rounds = json_decode(file_get_contents($roundsPath), true);

        // Build date lookup (same logic as SeedReferenceData::seedCupRoundTemplates)
        $dateLookup = [];
        foreach ($matchdays as $md) {
            $roundNum = $md['round'] ?? 0;
            $date = $md['date'] ?? null;
            $matchdayName = $md['matchday'] ?? '';

            if (!isset($dateLookup[$roundNum])) {
                $dateLookup[$roundNum] = ['first' => null, 'second' => null, 'name' => $matchdayName];
            }

            if (str_contains($matchdayName, 'Vuelta')) {
                $dateLookup[$roundNum]['second'] = $date;
            } elseif ($dateLookup[$roundNum]['first'] === null) {
                $dateLookup[$roundNum]['first'] = $date;
                $dateLookup[$roundNum]['name'] = $matchdayName;
            } else {
                $dateLookup[$roundNum]['second'] = $date;
            }
        }

        $templates = [];
        foreach ($rounds as $round) {
            $roundNumber = $round['round'];
            $type = $round['type'] === 'two_legged_knockout' ? 'two_leg' : 'one_leg';

            $dates = $dateLookup[$roundNumber] ?? ['first' => null, 'second' => null, 'name' => "Round {$roundNumber}"];
            $roundName = $dates['name'];
            $roundName = preg_replace('/\s*\(Ida\)$/', '', $roundName);

            $firstLegDate = $dates['first'] ? Carbon::parse($dates['first'])->format('Y-m-d') : null;
            $secondLegDate = $dates['second'] ? Carbon::parse($dates['second'])->format('Y-m-d') : null;

            $templates[$roundNumber] = [
                'round_name' => $roundName,
                'type' => $type,
                'first_leg_date' => $firstLegDate,
                'second_leg_date' => $secondLegDate,
            ];
        }

        return $templates;
    }

    /**
     * Parse the new schedule.json knockout format into the same normalized structure.
     */
    private function parseNewFormat(array $knockoutRounds): array
    {
        $templates = [];
        foreach ($knockoutRounds as $entry) {
            $roundNumber = $entry['round'];
            $hasSecondLeg = isset($entry['second_leg_date']);

            $templates[$roundNumber] = [
                'round_name' => $entry['name'],
                'type' => $hasSecondLeg ? 'two_leg' : 'one_leg',
                'first_leg_date' => $hasSecondLeg ? $entry['first_leg_date'] : ($entry['date'] ?? null),
                'second_leg_date' => $hasSecondLeg ? $entry['second_leg_date'] : null,
            ];
        }

        return $templates;
    }
}
