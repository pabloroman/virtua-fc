<?php

namespace Database\Seeders;

use App\Models\ClubProfile;
use App\Models\Team;
use Illuminate\Database\Seeder;

class ClubProfilesSeeder extends Seeder
{
    /**
     * Club profiles with reputation and commercial revenue.
     * Commercial revenue in cents (e.g., 15_000_000_000 = €150M).
     */
    private const CLUB_DATA = [
        // La Liga - Elite
        'Real Madrid' => ['reputation' => ClubProfile::REPUTATION_ELITE, 'commercial' => 35_000_000_000],
        'FC Barcelona' => ['reputation' => ClubProfile::REPUTATION_ELITE, 'commercial' => 32_000_000_000],
        'Atlético de Madrid' => ['reputation' => ClubProfile::REPUTATION_ELITE, 'commercial' => 18_000_000_000],

        // La Liga - Continental
        'Sevilla FC' => ['reputation' => ClubProfile::REPUTATION_CONTINENTAL, 'commercial' => 8_000_000_000],
        'Athletic Bilbao' => ['reputation' => ClubProfile::REPUTATION_CONTINENTAL, 'commercial' => 7_500_000_000],
        'Valencia CF' => ['reputation' => ClubProfile::REPUTATION_CONTINENTAL, 'commercial' => 7_000_000_000],
        'Villarreal CF' => ['reputation' => ClubProfile::REPUTATION_CONTINENTAL, 'commercial' => 6_500_000_000],
        'Real Betis Balompié' => ['reputation' => ClubProfile::REPUTATION_CONTINENTAL, 'commercial' => 6_000_000_000],
        'Real Sociedad' => ['reputation' => ClubProfile::REPUTATION_CONTINENTAL, 'commercial' => 5_500_000_000],

        // La Liga - Established
        'RCD Espanyol Barcelona' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 4_000_000_000],
        'Celta de Vigo' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 3_500_000_000],
        'RCD Mallorca' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 3_200_000_000],
        'CA Osasuna' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 3_000_000_000],
        'Getafe CF' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 2_800_000_000],

        // La Liga - Modest
        'Rayo Vallecano' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 2_000_000_000],
        'Girona FC' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 2_200_000_000],
        'Deportivo Alavés' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 1_800_000_000],
        'Elche CF' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 1_500_000_000],
        'Levante UD' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 2_500_000_000],
        'Real Oviedo' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 2_000_000_000],

        // La Liga 2 - Established (historic clubs)
        'Deportivo de La Coruña' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 3_000_000_000],
        'Málaga CF' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 2_800_000_000],
        'Sporting Gijón' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 2_500_000_000],
        'UD Las Palmas' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 2_500_000_000],
        'Real Valladolid CF' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 2_200_000_000],
        'Real Zaragoza' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 2_000_000_000],
        'Granada CF' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 1_800_000_000],
        'Cádiz CF' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 1_500_000_000],
        'Racing Santander' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 1_400_000_000],
        'UD Almería' => ['reputation' => ClubProfile::REPUTATION_ESTABLISHED, 'commercial' => 1_300_000_000],

        // La Liga 2 - Modest
        'Córdoba CF' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 1_200_000_000],
        'CD Castellón' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 1_100_000_000],
        'Albacete Balompié' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 1_000_000_000],
        'SD Huesca' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 800_000_000],
        'SD Eibar' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 900_000_000],
        'CD Leganés' => ['reputation' => ClubProfile::REPUTATION_MODEST, 'commercial' => 850_000_000],

        // La Liga 2 - Local
        'Burgos CF' => ['reputation' => ClubProfile::REPUTATION_LOCAL, 'commercial' => 600_000_000],
        'Cultural Leonesa' => ['reputation' => ClubProfile::REPUTATION_LOCAL, 'commercial' => 500_000_000],
        'CD Mirandés' => ['reputation' => ClubProfile::REPUTATION_LOCAL, 'commercial' => 450_000_000],
        'AD Ceuta FC' => ['reputation' => ClubProfile::REPUTATION_LOCAL, 'commercial' => 400_000_000],
        'FC Andorra' => ['reputation' => ClubProfile::REPUTATION_LOCAL, 'commercial' => 700_000_000],
        'Real Sociedad B' => ['reputation' => ClubProfile::REPUTATION_LOCAL, 'commercial' => 300_000_000],
    ];

    public function run(): void
    {
        // Seed club profiles for all teams that match known names
        $allTeams = Team::all();
        $seeded = 0;

        foreach ($allTeams as $team) {
            $data = self::CLUB_DATA[$team->name] ?? null;

            if (!$data) {
                // Default for unknown teams
                $data = [
                    'reputation' => ClubProfile::REPUTATION_LOCAL,
                    'commercial' => 300_000_000, // €3M default
                ];
            }

            ClubProfile::updateOrCreate(
                ['team_id' => $team->id],
                [
                    'reputation_level' => $data['reputation'],
                    'commercial_revenue' => $data['commercial'],
                ]
            );

            $seeded++;
        }

        $this->command->info('Club profiles seeded for ' . $seeded . ' teams');
    }
}
