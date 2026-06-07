<?php

namespace App\Support;

use App\Modules\Competition\Services\CountryConfig;

/**
 * Shared primitives for the season data-refresh pipeline (scaffold / validate /
 * normalize / diff commands).
 *
 * Centralizes three things that were drifting between commands:
 *  - transfermarkt id resolution (explicit id / transfermarktId / crest URL),
 *  - the canonical on-disk JSON shape for squad files, and
 *  - the authoritative list of competitions a season folder owns.
 */
class SeasonData
{
    /**
     * Encode a squad data structure to the canonical on-disk form.
     *
     * Squad files (`teams.json` and EUR/INT pool files) are 2-space indented
     * (they originate from the browser scraper's `JSON.stringify(x, null, 2)`),
     * not PHP's 4-space `JSON_PRETTY_PRINT`. We pretty-print then halve the
     * leading indentation so re-encoding existing files is a no-op. Slashes and
     * unicode are left unescaped (crest URLs and € stay readable), with a
     * trailing newline so the file is git-friendly.
     */
    public static function encode(array $data): string
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        // PHP indents pretty-printed JSON 4 spaces per level; squad files use 2.
        $json = preg_replace_callback(
            '/^(?: {4})+/m',
            fn (array $m): string => str_repeat(' ', strlen($m[0]) / 2),
            $json,
        );

        return $json . "\n";
    }

    /**
     * Resolve an entity's transfermarkt id the way the seeder does: an explicit
     * `id` / `transfermarktId`, else parsed from the crest `image` URL.
     *
     * @param  array<string, mixed>  $entity
     */
    public static function resolveTransfermarktId(array $entity): ?string
    {
        if (!empty($entity['id'])) {
            return (string) $entity['id'];
        }
        if (!empty($entity['transfermarktId'])) {
            return (string) $entity['transfermarktId'];
        }

        return self::idFromImage($entity['image'] ?? '');
    }

    /**
     * Extract a transfermarkt id from a crest URL of the form
     * `.../wappen/big/{id}.png`.
     */
    public static function idFromImage(string $imageUrl): ?string
    {
        if (preg_match('/\/(\d+)\.png$/', $imageUrl, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Enumerate every competition that owns a `data/{season}/` folder, paired
     * with how its squad data is shaped:
     *  - 'league'      — round-robin league with a teams.json + full schedule,
     *  - 'cup'         — participant list (teams.json, fixtures drawn per game),
     *  - 'continental' — participant list linking existing teams,
     *  - 'pool'        — per-team {id}.json files (EUR/INT transfer pools),
     *  - 'none'        — bare promotion playoff (schedule only, no squads).
     *
     * Order and de-duplication match the seeder's traversal so every consumer
     * sees the same competition set.
     *
     * @return array<int, array{code: string, type: string}>
     */
    public static function competitions(CountryConfig $countryConfig): array
    {
        $out = [];
        $seen = [];

        $add = function (string $code, string $type) use (&$out, &$seen): void {
            if (isset($seen[$code])) {
                return;
            }
            $seen[$code] = true;
            $out[] = ['code' => $code, 'type' => $type];
        };

        foreach ($countryConfig->playableCountryCodes() as $country) {
            foreach ($countryConfig->flattenedTiers($country) as $tier) {
                $add($tier['competition'], 'league');
            }
            foreach ($countryConfig->promotionPlayoffIds($country) as $playoffId) {
                $add($playoffId, 'none');
            }
            foreach ($countryConfig->domesticCupIds($country) as $cupId) {
                $add($cupId, 'cup');
            }
            $transferPool = $countryConfig->support($country)['transfer_pool'] ?? [];
            foreach ($transferPool as $code => $poolConfig) {
                $add($code, ($poolConfig['role'] ?? 'league') === 'team_pool' ? 'pool' : 'league');
            }
            foreach ($countryConfig->continentalSupportIds($country) as $code) {
                $add($code, 'continental');
            }
        }

        return $out;
    }

    /**
     * Read a competition's clubs as a normalized list, regardless of whether it
     * is stored as a single `teams.json` (league/cup/continental) or a folder of
     * per-team `{id}.json` files (EUR/INT pools). Returns null when the data is
     * absent. Bare playoffs ('none') always return null (no squads).
     *
     * Each club is `['id' => transfermarktId, 'name' => string,
     * 'players' => array<string id, string name>]`.
     *
     * @return array<int, array{id: string, name: string, players: array<string, string>}>|null
     */
    public static function readCompetitionClubs(string $season, string $code, string $type): ?array
    {
        $dir = base_path("data/{$season}/{$code}");

        if ($type === 'none' || !is_dir($dir)) {
            return null;
        }

        if ($type === 'pool') {
            $files = array_filter(glob("{$dir}/*.json") ?: [], fn ($p) => basename($p) !== 'schedule.json');
            $clubs = [];
            foreach ($files as $file) {
                $data = json_decode((string) file_get_contents($file), true);
                if (is_array($data) && ($club = self::club($data)) !== null) {
                    $clubs[] = $club;
                }
            }

            return $clubs;
        }

        $path = "{$dir}/teams.json";
        if (!file_exists($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !is_array($data['clubs'] ?? null)) {
            return null;
        }

        return array_values(array_filter(array_map(
            fn ($club) => is_array($club) ? self::club($club) : null,
            $data['clubs'],
        )));
    }

    /**
     * Normalize a single club entry to {id, name, players[id => name]}.
     *
     * @param  array<string, mixed>  $club
     * @return array{id: string, name: string, players: array<string, string>}|null
     */
    private static function club(array $club): ?array
    {
        $id = self::resolveTransfermarktId($club);
        if ($id === null) {
            return null;
        }

        $players = [];
        foreach ($club['players'] ?? [] as $player) {
            if (is_array($player) && !empty($player['id'])) {
                $players[(string) $player['id']] = (string) ($player['name'] ?? $player['id']);
            }
        }

        return [
            'id' => $id,
            'name' => (string) ($club['name'] ?? "({$id})"),
            'players' => $players,
        ];
    }
}
