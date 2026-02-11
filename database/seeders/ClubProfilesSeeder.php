<?php

namespace Database\Seeders;

use App\Models\ClubProfile;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ClubProfilesSeeder extends Seeder
{
    /**
     * Club profiles with reputation level.
     * Commercial revenue is now calculated algorithmically from stadium_seats × config rate.
     */
    private const CLUB_DATA = [
        // La Liga - Elite
        'Real Madrid' => ClubProfile::REPUTATION_ELITE,
        'FC Barcelona' => ClubProfile::REPUTATION_ELITE,

        // La Liga - Contenders
        'Atlético de Madrid' => ClubProfile::REPUTATION_CONTENDERS,
        'Athletic Bilbao' => ClubProfile::REPUTATION_CONTENDERS,
        'Real Betis Balompié' => ClubProfile::REPUTATION_CONTENDERS,
        'Villarreal CF' => ClubProfile::REPUTATION_CONTENDERS,

        // La Liga - Continental
        'Sevilla FC' => ClubProfile::REPUTATION_CONTINENTAL,
        'Valencia CF' => ClubProfile::REPUTATION_CONTINENTAL,
        'Real Sociedad' => ClubProfile::REPUTATION_CONTINENTAL,

        // La Liga - Established
        'RCD Espanyol Barcelona' => ClubProfile::REPUTATION_ESTABLISHED,
        'Celta de Vigo' => ClubProfile::REPUTATION_ESTABLISHED,
        'RCD Mallorca' => ClubProfile::REPUTATION_ESTABLISHED,
        'CA Osasuna' => ClubProfile::REPUTATION_ESTABLISHED,
        'Getafe CF' => ClubProfile::REPUTATION_ESTABLISHED,

        // La Liga - Modest
        'Rayo Vallecano' => ClubProfile::REPUTATION_MODEST,
        'Girona FC' => ClubProfile::REPUTATION_MODEST,
        'Deportivo Alavés' => ClubProfile::REPUTATION_MODEST,
        'Elche CF' => ClubProfile::REPUTATION_MODEST,
        'Levante UD' => ClubProfile::REPUTATION_MODEST,
        'Real Oviedo' => ClubProfile::REPUTATION_MODEST,

        // La Liga 2 - Established (historic clubs)
        'Deportivo de La Coruña' => ClubProfile::REPUTATION_ESTABLISHED,
        'Málaga CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Sporting Gijón' => ClubProfile::REPUTATION_ESTABLISHED,
        'UD Las Palmas' => ClubProfile::REPUTATION_ESTABLISHED,
        'Real Valladolid CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Real Zaragoza' => ClubProfile::REPUTATION_ESTABLISHED,
        'Granada CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Cádiz CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Racing Santander' => ClubProfile::REPUTATION_ESTABLISHED,
        'UD Almería' => ClubProfile::REPUTATION_ESTABLISHED,

        // La Liga 2 - Modest
        'Córdoba CF' => ClubProfile::REPUTATION_MODEST,
        'CD Castellón' => ClubProfile::REPUTATION_MODEST,
        'Albacete Balompié' => ClubProfile::REPUTATION_MODEST,
        'SD Huesca' => ClubProfile::REPUTATION_MODEST,
        'SD Eibar' => ClubProfile::REPUTATION_MODEST,
        'CD Leganés' => ClubProfile::REPUTATION_MODEST,

        // La Liga 2 - Professional
        'Burgos CF' => ClubProfile::REPUTATION_PROFESSIONAL,
        'Cultural Leonesa' => ClubProfile::REPUTATION_PROFESSIONAL,
        'CD Mirandés' => ClubProfile::REPUTATION_PROFESSIONAL,
        'AD Ceuta FC' => ClubProfile::REPUTATION_PROFESSIONAL,
        'FC Andorra' => ClubProfile::REPUTATION_PROFESSIONAL,
        'Real Sociedad B' => ClubProfile::REPUTATION_PROFESSIONAL,
    ];

    public function run(): void
    {
        // Seed club profiles for all teams that match known names
        $allTeams = Team::all();
        $seeded = 0;

        foreach ($allTeams as $team) {
            $reputation = self::CLUB_DATA[$team->name] ?? ClubProfile::REPUTATION_LOCAL;

            ClubProfile::updateOrCreate(
                ['team_id' => $team->id],
                [
                    'reputation_level' => $reputation,
                ]
            );

            $seeded++;
        }

        $this->command->info('Club profiles seeded for ' . $seeded . ' teams');
    }
}
