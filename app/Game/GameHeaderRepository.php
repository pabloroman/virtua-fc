<?php

namespace App\Game;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class GameHeaderRepository
{
    public function store()
    {

    }

    public function getById(UuidInterface $id)
    {
        $data = DB::table('games')
            ->join('teams', 'games.team_id', '=', 'teams.id')
            ->where('games.uuid', $id->toString())
            ->select([
                'games.uuid as game_id',
                'games.player_name as player_name',
                'teams.name as team_name',
                'teams.image as team_image',
            ])
            ->sole();

        return new GameHeader(
            Uuid::fromString($data->game_id),
            $data->player_name,
            new Team($data->team_name, $data->team_image),
        );
    }

    public function getAllByUser(int $userId): Collection
    {
        $games = collect();

        DB::table('games')
            ->join('teams', 'games.team_id', '=', 'teams.id')
            ->where('games.user_id', $userId)
            ->select([
                'games.uuid as game_id',
                'games.player_name as player_name',
                'teams.name as team_name',
                'teams.image as team_image',
            ])
            ->get()->each(function ($data) use($games) {
                $games->push(new GameHeader(
                    Uuid::fromString($data->game_id),
                    $data->player_name,
                    new Team($data->team_name, $data->team_image),
                ));
            });

        return $games;
    }
}
