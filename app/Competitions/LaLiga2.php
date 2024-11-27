<?php

namespace App\Competitions;

use Illuminate\Support\Facades\Storage;

final class LaLiga2 implements Competition
{
    public const NAME = 'LaLiga 2';
    public const CODE = 'ESP2';
    public const COUNTRY_ALPHA_2 = 'ES';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getCountryAlpha2(): string
    {
        return self::COUNTRY_ALPHA_2;
    }

    public function getInitialTeams(): array
    {
        $teams = [];
        $path = '/'.self::CODE.'/2024';
        $seasonTeams = json_decode(Storage::disk('data')->get($path.'/teams.json'), true);

        foreach ($seasonTeams['clubs'] as $club) {
            $importTeam = json_decode(Storage::disk('data')->get($path.'/teams/'.$club['id'].'.json'), true);
            $teams[] = new Team(
                $importTeam['id'],
                $importTeam['name'],
                $importTeam['image'],
            );
        }
        return $teams;
    }
}
