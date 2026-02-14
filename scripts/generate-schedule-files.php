<?php

/**
 * Generates the new-format schedule.json files alongside existing matchdays.json/rounds.json.
 * Run with: php scripts/generate-schedule-files.php
 */

function convertLeague(string $basePath): array
{
    $data = json_decode(file_get_contents("{$basePath}/matchdays.json"), true);
    $league = [];
    foreach ($data as $entry) {
        $date = DateTime::createFromFormat('d/m/y', $entry['date']);
        $league[] = ['round' => $entry['round'], 'date' => $date->format('Y-m-d')];
    }
    return ['league' => $league];
}

function convertCup(string $basePath): array
{
    $matchdays = json_decode(file_get_contents("{$basePath}/matchdays.json"), true);
    $rounds = json_decode(file_get_contents("{$basePath}/rounds.json"), true);

    // Build type lookup from rounds.json
    $typeLookup = [];
    foreach ($rounds as $r) {
        $typeLookup[$r['round']] = $r['type'];
    }

    // Group matchday entries by round number
    $byRound = [];
    foreach ($matchdays as $md) {
        $round = $md['round'];
        if (!isset($byRound[$round])) {
            $byRound[$round] = [];
        }
        $byRound[$round][] = $md;
    }

    $knockout = [];
    foreach ($byRound as $roundNum => $entries) {
        $type = $typeLookup[$roundNum] ?? 'one_legged_knockout';
        $isTwoLeg = ($type === 'two_legged_knockout');

        // Clean the round name: remove (Ida) / (Vuelta) suffixes
        $name = $entries[0]['matchday'];
        $name = preg_replace('/\s*\(Ida\)$/', '', $name);
        $name = preg_replace('/\s*\(Vuelta\)$/', '', $name);

        if ($isTwoLeg && count($entries) === 2) {
            $firstDate = $entries[0]['date'];
            $secondDate = $entries[1]['date'];

            // If first entry is Vuelta, swap
            if (strpos($entries[0]['matchday'], 'Vuelta') !== false) {
                $firstDate = $entries[1]['date'];
                $secondDate = $entries[0]['date'];
            }

            $knockout[] = [
                'round' => $roundNum,
                'name' => $name,
                'first_leg_date' => $firstDate,
                'second_leg_date' => $secondDate,
            ];
        } else {
            $knockout[] = [
                'round' => $roundNum,
                'name' => $name,
                'date' => $entries[0]['date'],
            ];
        }
    }

    usort($knockout, function ($a, $b) {
        return $a['round'] - $b['round'];
    });

    return ['knockout' => $knockout];
}

function writeSchedule(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    file_put_contents($path, $json);
}

function printKnockout(array $result): void
{
    foreach ($result['knockout'] as $r) {
        $dates = isset($r['date']) ? $r['date'] : $r['first_leg_date'] . ' / ' . $r['second_leg_date'];
        $type = isset($r['date']) ? 'one_leg' : 'two_leg';
        echo "  Round {$r['round']}: {$r['name']} ({$type}) - {$dates}\n";
    }
}

// --- League competitions ---

// ESP1
$result = convertLeague('data/2025/ESP1');
writeSchedule('data/2025/ESP1/schedule.json', $result);
echo "ESP1: " . count($result['league']) . " league rounds\n";

// ESP2 - league + knockout
$result = convertLeague('data/2025/ESP2');
$result['knockout'] = [
    ['round' => 1, 'name' => 'Playoff Semifinal', 'first_leg_date' => '2026-06-07', 'second_leg_date' => '2026-06-14'],
    ['round' => 2, 'name' => 'Playoff Final', 'first_leg_date' => '2026-06-21', 'second_leg_date' => '2026-06-28'],
];
writeSchedule('data/2025/ESP2/schedule.json', $result);
echo "ESP2: " . count($result['league']) . " league rounds + " . count($result['knockout']) . " knockout rounds\n";
printKnockout($result);

// TEST1
$result = convertLeague('data/2025/TEST1');
writeSchedule('data/2025/TEST1/schedule.json', $result);
echo "TEST1: " . count($result['league']) . " league rounds\n";

echo "\n";

// --- Cup competitions ---

// ESPCUP
$result = convertCup('data/2025/ESPCUP');
writeSchedule('data/2025/ESPCUP/schedule.json', $result);
echo "ESPCUP: " . count($result['knockout']) . " knockout rounds\n";
printKnockout($result);
echo "\n";

// ESPSUP
$result = convertCup('data/2025/ESPSUP');
writeSchedule('data/2025/ESPSUP/schedule.json', $result);
echo "ESPSUP: " . count($result['knockout']) . " knockout rounds\n";
printKnockout($result);
echo "\n";

// TESTCUP - fix English -> Spanish
$result = convertCup('data/2025/TESTCUP');
foreach ($result['knockout'] as &$r) {
    if ($r['name'] === 'Semi-Finals') {
        $r['name'] = 'Semifinal';
    }
}
unset($r);
writeSchedule('data/2025/TESTCUP/schedule.json', $result);
echo "TESTCUP: " . count($result['knockout']) . " knockout rounds\n";
printKnockout($result);
echo "\n";

// UCL
$result = convertCup('data/2025/UCL');
writeSchedule('data/2025/UCL/schedule.json', $result);
echo "UCL: " . count($result['knockout']) . " knockout rounds\n";
printKnockout($result);

echo "\nDone! All schedule.json files generated.\n";
