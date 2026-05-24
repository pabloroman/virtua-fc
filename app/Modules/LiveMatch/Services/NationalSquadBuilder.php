<?php

namespace App\Modules\LiveMatch\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use App\Modules\LiveMatch\Exceptions\NoEligibleSquadException;
use Illuminate\Support\Collection;

class NationalSquadBuilder
{
    public const SQUAD_SIZE = 23;

    public const MIN_FOR_VIABLE_SQUAD = 11;

    /**
     * Build a frozen national-team squad for the given user and ISO code.
     *
     * Pulls from the user's most recent active Game on the tenant plane —
     * top SQUAD_SIZE players whose nationality JSON array contains $iso. This
     * is the single explicit cross-plane read; everything downstream operates
     * on the snapshot.
     *
     * @return array{game_id: string, players: array<int, array<string, mixed>>}
     */
    public function buildFor(User $user, string $iso): array
    {
        $game = Game::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('updated_at')
            ->first();

        if ($game === null) {
            throw NoEligibleSquadException::noActiveGame($iso);
        }

        $players = GamePlayer::where('game_id', $game->id)
            ->whereJsonContains('nationality', $iso)
            ->orderByDesc('overall_score')
            ->limit(self::SQUAD_SIZE)
            ->get();

        if ($players->count() < self::MIN_FOR_VIABLE_SQUAD) {
            throw NoEligibleSquadException::tooFewPlayers($iso, $players->count());
        }

        return [
            'game_id' => $game->id,
            'players' => $players->map(fn (GamePlayer $p) => $this->serializePlayer($p))->all(),
        ];
    }

    /**
     * Count of eligible players in the user's current save for a given ISO.
     * Cheap query for the lobby's "X eligible in your save" hint.
     */
    public function eligibleCountFor(User $user, string $iso): int
    {
        $game = Game::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('updated_at')
            ->first();

        if ($game === null) {
            return 0;
        }

        return GamePlayer::where('game_id', $game->id)
            ->whereJsonContains('nationality', $iso)
            ->count();
    }

    /**
     * Rehydrate stored player records as unsaved GamePlayer instances. The
     * simulator only reads attributes, never relationships, so unsaved
     * instances are fine.
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

    private function serializePlayer(GamePlayer $p): array
    {
        return [
            'id' => $p->id,
            'game_id' => $p->game_id,
            'team_id' => $p->team_id,
            'name' => $p->name,
            'position' => $p->position,
            'secondary_positions' => $p->secondary_positions ?? [],
            'nationality' => $p->nationality ?? [],
            'overall_score' => $p->overall_score,
            'durability' => $p->durability,
            'number' => $p->number,
            'foot' => $p->foot,
            'height' => $p->height,
            'date_of_birth' => $p->date_of_birth?->toDateString(),
            'tier' => $p->tier,
            'potential' => $p->potential,
            'potential_low' => $p->potential_low,
            'potential_high' => $p->potential_high,
        ];
    }
}
