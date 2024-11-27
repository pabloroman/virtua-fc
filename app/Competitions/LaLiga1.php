<?php

namespace App\Competitions;

use App\Services\Country;

use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\select;

final class LaLiga1 implements Competition
{
    public const NAME = 'LaLiga';
    public const CODE = 'ESP1';
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
