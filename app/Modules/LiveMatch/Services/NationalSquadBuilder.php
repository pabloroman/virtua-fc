<?php

namespace App\Modules\LiveMatch\Services;

use App\Models\GamePlayer;
use App\Models\GamePlayerTemplate;
use App\Models\User;
use App\Modules\LiveMatch\Exceptions\NoEligibleSquadException;
use Illuminate\Support\Collection;

class NationalSquadBuilder
{
    public const SQUAD_SIZE = 23;

    public const MIN_FOR_VIABLE_SQUAD = 11;

    /**
     * Build a frozen national-team squad for the given user and nationality.
     *
     * For the prototype the data source is `GamePlayerTemplate` (the canonical
     * real-world roster, control-plane, shared across every user). This lets
     * the duel run between any two logged-in users without each of them having
     * their own active save with eligible players.
     *
     * A future iteration can switch this to per-save player pools when the
     * tournament feature lands and we want "your career develops your national
     * team" semantics. The User param stays in the signature for that.
     *
     * @return array{game_id: ?string, players: array<int, array<string, mixed>>}
     */
    public function buildFor(User $user, string $nationality): array
    {
        $templates = $this->queryTemplates($nationality)
            ->orderByDesc('overall_score')
            ->limit(self::SQUAD_SIZE)
            ->get();

        if ($templates->count() < self::MIN_FOR_VIABLE_SQUAD) {
            throw NoEligibleSquadException::tooFewPlayers($nationality, $templates->count());
        }

        return [
            'game_id' => null,
            'players' => $templates->map(fn (GamePlayerTemplate $t) => $this->serializeTemplate($t))->all(),
        ];
    }

    /**
     * Eligible-player count for a nation. Used to surface the "X eligible"
     * hint on the team picker.
     */
    public function eligibleCountFor(User $user, string $nationality): int
    {
        return $this->queryTemplates($nationality)->count();
    }

    /**
     * Rehydrate stored player records as unsaved GamePlayer instances. The
     * simulator only reads attributes (never relationships), so unsaved
     * instances work fine.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function rehydrate(array $records): Collection
    {
        return collect($records)->map(function (array $r): GamePlayer {
            $player = new GamePlayer;
            $player->forceFill([
                'id' => $r['id'],
                'game_id' => $r['game_id'] ?? null,
                'team_id' => $r['team_id'] ?? null,
                'name' => $r['name'],
                'position' => $r['position'],
                'secondary_positions' => $r['secondary_positions'] ?? [],
                'nationality' => $r['nationality'] ?? [],
                'overall_score' => $r['overall_score'],
                'durability' => $r['durability'] ?? 70,
                'number' => $r['number'] ?? null,
                'foot' => $r['foot'] ?? 'right',
                'height' => $r['height'] ?? null,
                'date_of_birth' => $r['date_of_birth'] ?? null,
                'tier' => $r['tier'] ?? 3,
                'potential' => $r['potential'] ?? $r['overall_score'],
                'potential_low' => $r['potential_low'] ?? $r['overall_score'],
                'potential_high' => $r['potential_high'] ?? $r['overall_score'],
            ]);
            $player->exists = true;

            return $player;
        });
    }

    /**
     * Shared query: latest season of GamePlayerTemplate filtered by
     * nationality. `season` is a string column on the templates table —
     * pulling only the latest avoids mixing multiple Spains across years.
     */
    private function queryTemplates(string $nationality)
    {
        $latestSeason = GamePlayerTemplate::max('season');
        $query = GamePlayerTemplate::query()->whereJsonContains('nationality', $nationality);
        if ($latestSeason !== null) {
            $query->where('season', $latestSeason);
        }

        return $query;
    }

    private function serializeTemplate(GamePlayerTemplate $t): array
    {
        // GamePlayerTemplate doesn't cast `secondary_positions` to array
        // even though the column exists (the cast lives on GamePlayer only),
        // so reading the attribute hands back a raw JSON string. Decode here
        // before we hand the record off to the rehydrate → forceFill chain
        // — otherwise GamePlayer's array cast double-encodes the string and
        // every read downstream blows up `foreach (... as $secondary)`.
        $secondary = $t->getRawOriginal('secondary_positions');
        if (is_string($secondary)) {
            $secondary = json_decode($secondary, true) ?: [];
        }

        return [
            // GamePlayer has UUID PKs; templates have bigint id + a stable
            // UUID `player_id`. Use the UUID so rehydrated instances carry
            // the same identifier shape the simulator expects.
            'id' => $t->player_id,
            'game_id' => null,
            'team_id' => $t->team_id,
            'name' => $t->name,
            'position' => $t->position,
            'secondary_positions' => is_array($secondary) ? $secondary : [],
            'nationality' => $t->nationality ?? [],
            'overall_score' => $t->overall_score,
            'durability' => $t->durability,
            'number' => $t->number,
            'foot' => $t->foot,
            'height' => $t->height,
            'date_of_birth' => $t->date_of_birth?->toDateString(),
            'tier' => $t->tier,
            'potential' => $t->potential,
            'potential_low' => $t->potential_low,
            'potential_high' => $t->potential_high,
        ];
    }
}
