<?php

namespace App\Modules\LiveMatch\Services;

use App\Models\GamePlayer;
use App\Models\GamePlayerTemplate;
use App\Models\Team;
use App\Models\User;
use App\Modules\LiveMatch\Exceptions\NoEligibleSquadException;
use Illuminate\Support\Collection;

class NationalSquadBuilder
{
    public const SQUAD_SIZE = 23;

    public const MIN_FOR_VIABLE_SQUAD = 11;

    /**
     * Build a frozen national-team squad for the given Team.
     *
     * Data source is `GamePlayerTemplate` (the canonical real-world roster,
     * shared across every user) — the same source tournament mode uses (see
     * SetupTournamentGame). Players are linked to their national team via
     * game_player_templates.team_id.
     *
     * The User param stays in the signature for the eventual per-save switch
     * when the live duel feature wraps into tournament play.
     *
     * @return array{team_id: string, players: array<int, array<string, mixed>>}
     */
    public function buildFor(User $user, Team $team): array
    {
        $templates = $this->queryTemplates($team->id)
            ->orderByDesc('overall_score')
            ->limit(self::SQUAD_SIZE)
            ->get();

        if ($templates->count() < self::MIN_FOR_VIABLE_SQUAD) {
            throw NoEligibleSquadException::tooFewPlayers($team->name, $templates->count());
        }

        return [
            'team_id' => $team->id,
            'players' => $templates->map(fn (GamePlayerTemplate $t) => $this->serializeTemplate($t))->all(),
        ];
    }

    /**
     * Eligible-player count for a team in the squad pool. Used to surface
     * the "X eligible" hint on the team picker.
     */
    public function eligibleCountFor(User $user, Team $team): int
    {
        return $this->queryTemplates($team->id)->count();
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
     * Shared query: latest season of GamePlayerTemplate filtered by the
     * picked national team. `season` is a string column on templates —
     * pulling only the latest avoids mixing seasons.
     */
    private function queryTemplates(string $teamId)
    {
        $latestSeason = GamePlayerTemplate::where('team_id', $teamId)->max('season');
        $query = GamePlayerTemplate::query()->where('team_id', $teamId);
        if ($latestSeason !== null) {
            $query->where('season', $latestSeason);
        }

        return $query;
    }

    private function serializeTemplate(GamePlayerTemplate $t): array
    {
        // GamePlayerTemplate doesn't cast `secondary_positions` to array
        // (the cast lives on GamePlayer only). Reading the attribute hands
        // back the raw JSON string — decode here so the rehydrate →
        // forceFill chain doesn't double-encode through GamePlayer's cast.
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
