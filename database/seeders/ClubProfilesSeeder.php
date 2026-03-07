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
     *
     * Names must match the database exactly (seeded from Transfermarkt JSON data).
     */
    private const CLUB_DATA = [
        // =============================================
        // Spain - La Liga (ESP1)
        // =============================================

        // Elite - Objetivo: Liga
        'Real Madrid' => ClubProfile::REPUTATION_ELITE,
        'FC Barcelona' => ClubProfile::REPUTATION_ELITE,
        'Atlético de Madrid' => ClubProfile::REPUTATION_ELITE,

        // Continental - Objetivo: Europa League
        'Athletic Bilbao' => ClubProfile::REPUTATION_CONTINENTAL,
        'Villarreal CF' => ClubProfile::REPUTATION_CONTINENTAL,
        'Real Betis Balompié' => ClubProfile::REPUTATION_CONTINENTAL,
        'Sevilla FC' => ClubProfile::REPUTATION_CONTINENTAL,
        'Real Sociedad' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established - Objetivo: Top 10
        'Valencia CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'RCD Espanyol Barcelona' => ClubProfile::REPUTATION_ESTABLISHED,
        'Celta de Vigo' => ClubProfile::REPUTATION_ESTABLISHED,
        'RCD Mallorca' => ClubProfile::REPUTATION_ESTABLISHED,
        'CA Osasuna' => ClubProfile::REPUTATION_ESTABLISHED,
        'Getafe CF' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest - Objetivo: No descender
        'Rayo Vallecano' => ClubProfile::REPUTATION_MODEST,
        'Girona FC' => ClubProfile::REPUTATION_MODEST,
        'Deportivo Alavés' => ClubProfile::REPUTATION_MODEST,
        'Elche CF' => ClubProfile::REPUTATION_MODEST,
        'Levante UD' => ClubProfile::REPUTATION_MODEST,
        'Real Oviedo' => ClubProfile::REPUTATION_MODEST,

        // =============================================
        // Spain - La Liga 2 (ESP2)
        // =============================================

        // Established (historic clubs) - Objetivo: Playoff ascenso
        'Deportivo de La Coruña' => ClubProfile::REPUTATION_ESTABLISHED,
        'Málaga CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Sporting Gijón' => ClubProfile::REPUTATION_ESTABLISHED,
        'UD Las Palmas' => ClubProfile::REPUTATION_ESTABLISHED,
        'Real Valladolid CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Granada CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Cádiz CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Racing Santander' => ClubProfile::REPUTATION_ESTABLISHED,
        'UD Almería' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest - Objetivo: Top 10
        'Real Zaragoza' => ClubProfile::REPUTATION_MODEST,
        'Córdoba CF' => ClubProfile::REPUTATION_MODEST,
        'CD Castellón' => ClubProfile::REPUTATION_MODEST,
        'Albacete Balompié' => ClubProfile::REPUTATION_MODEST,
        'SD Huesca' => ClubProfile::REPUTATION_MODEST,
        'SD Eibar' => ClubProfile::REPUTATION_MODEST,
        'CD Leganés' => ClubProfile::REPUTATION_MODEST,

        // Local - Objetivo: No descender
        'Burgos CF' => ClubProfile::REPUTATION_LOCAL,
        'Cultural Leonesa' => ClubProfile::REPUTATION_LOCAL,
        'CD Mirandés' => ClubProfile::REPUTATION_LOCAL,
        'AD Ceuta FC' => ClubProfile::REPUTATION_LOCAL,
        'FC Andorra' => ClubProfile::REPUTATION_LOCAL,
        'Real Sociedad B' => ClubProfile::REPUTATION_LOCAL,

        // =============================================
        // England - Premier League (ENG1)
        // =============================================

        // Elite
        'Manchester City' => ClubProfile::REPUTATION_ELITE,
        'Liverpool FC' => ClubProfile::REPUTATION_ELITE,
        'Arsenal FC' => ClubProfile::REPUTATION_ELITE,
        'Chelsea FC' => ClubProfile::REPUTATION_ELITE,

        // Continental
        'Manchester United' => ClubProfile::REPUTATION_CONTINENTAL,
        'Tottenham Hotspur' => ClubProfile::REPUTATION_CONTINENTAL,
        'Newcastle United' => ClubProfile::REPUTATION_CONTINENTAL,
        'Aston Villa' => ClubProfile::REPUTATION_CONTINENTAL,
        'West Ham United' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'Everton FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Brighton & Hove Albion' => ClubProfile::REPUTATION_ESTABLISHED,
        'Crystal Palace' => ClubProfile::REPUTATION_ESTABLISHED,
        'Wolverhampton Wanderers' => ClubProfile::REPUTATION_ESTABLISHED,
        'Leeds United' => ClubProfile::REPUTATION_ESTABLISHED,
        'Nottingham Forest' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'Fulham FC' => ClubProfile::REPUTATION_MODEST,
        'Brentford FC' => ClubProfile::REPUTATION_MODEST,
        'AFC Bournemouth' => ClubProfile::REPUTATION_MODEST,
        'Sunderland AFC' => ClubProfile::REPUTATION_MODEST,
        'Burnley FC' => ClubProfile::REPUTATION_MODEST,

        // =============================================
        // Germany - Bundesliga (DEU1)
        // =============================================

        // Elite
        'Bayern Munich' => ClubProfile::REPUTATION_ELITE,

        // Continental
        'Borussia Dortmund' => ClubProfile::REPUTATION_CONTINENTAL,
        'Bayer 04 Leverkusen' => ClubProfile::REPUTATION_CONTINENTAL,
        'Eintracht Frankfurt' => ClubProfile::REPUTATION_CONTINENTAL,
        'RB Leipzig' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'VfB Stuttgart' => ClubProfile::REPUTATION_ESTABLISHED,
        'SC Freiburg' => ClubProfile::REPUTATION_ESTABLISHED,
        'Borussia Mönchengladbach' => ClubProfile::REPUTATION_ESTABLISHED,
        'SV Werder Bremen' => ClubProfile::REPUTATION_ESTABLISHED,
        'VfL Wolfsburg' => ClubProfile::REPUTATION_ESTABLISHED,
        '1.FC Köln' => ClubProfile::REPUTATION_ESTABLISHED,
        'Hamburger SV' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        '1.FC Union Berlin' => ClubProfile::REPUTATION_MODEST,
        '1.FSV Mainz 05' => ClubProfile::REPUTATION_MODEST,
        'TSG 1899 Hoffenheim' => ClubProfile::REPUTATION_MODEST,
        'FC Augsburg' => ClubProfile::REPUTATION_MODEST,
        'FC St. Pauli' => ClubProfile::REPUTATION_MODEST,
        '1.FC Heidenheim 1846' => ClubProfile::REPUTATION_MODEST,

        // =============================================
        // France - Ligue 1 (FRA1)
        // =============================================

        // Elite
        'Paris Saint-Germain' => ClubProfile::REPUTATION_ELITE,

        // Continental
        'Olympique Marseille' => ClubProfile::REPUTATION_CONTINENTAL,
        'AS Monaco' => ClubProfile::REPUTATION_CONTINENTAL,
        'Olympique Lyon' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'LOSC Lille' => ClubProfile::REPUTATION_ESTABLISHED,
        'OGC Nice' => ClubProfile::REPUTATION_ESTABLISHED,
        'Stade Rennais FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'RC Lens' => ClubProfile::REPUTATION_ESTABLISHED,
        'FC Nantes' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'FC Toulouse' => ClubProfile::REPUTATION_MODEST,
        'RC Strasbourg Alsace' => ClubProfile::REPUTATION_MODEST,
        'FC Metz' => ClubProfile::REPUTATION_MODEST,
        'Le Havre AC' => ClubProfile::REPUTATION_MODEST,
        'Stade Brestois 29' => ClubProfile::REPUTATION_MODEST,
        'AJ Auxerre' => ClubProfile::REPUTATION_MODEST,
        'Angers SCO' => ClubProfile::REPUTATION_MODEST,
        'Paris FC' => ClubProfile::REPUTATION_MODEST,
        'FC Lorient' => ClubProfile::REPUTATION_MODEST,

        // =============================================
        // Italy - Serie A (ITA1)
        // =============================================

        // Elite
        'Inter Milan' => ClubProfile::REPUTATION_ELITE,
        'Juventus FC' => ClubProfile::REPUTATION_ELITE,
        'AC Milan' => ClubProfile::REPUTATION_ELITE,

        // Continental
        'SSC Napoli' => ClubProfile::REPUTATION_CONTINENTAL,
        'Atalanta BC' => ClubProfile::REPUTATION_CONTINENTAL,
        'AS Roma' => ClubProfile::REPUTATION_CONTINENTAL,
        'SS Lazio' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'ACF Fiorentina' => ClubProfile::REPUTATION_ESTABLISHED,
        'Bologna FC 1909' => ClubProfile::REPUTATION_ESTABLISHED,
        'Torino FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Genoa CFC' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'Udinese Calcio' => ClubProfile::REPUTATION_MODEST,
        'US Lecce' => ClubProfile::REPUTATION_MODEST,
        'Parma Calcio 1913' => ClubProfile::REPUTATION_MODEST,
        'Cagliari Calcio' => ClubProfile::REPUTATION_MODEST,
        'Hellas Verona' => ClubProfile::REPUTATION_MODEST,
        'US Sassuolo' => ClubProfile::REPUTATION_MODEST,
        'Como 1907' => ClubProfile::REPUTATION_MODEST,
        'US Cremonese' => ClubProfile::REPUTATION_MODEST,
        'Pisa Sporting Club' => ClubProfile::REPUTATION_MODEST,

        // =============================================
        // European transfer pool (EUR)
        // =============================================

        // Continental
        'SL Benfica' => ClubProfile::REPUTATION_CONTINENTAL,
        'FC Porto' => ClubProfile::REPUTATION_CONTINENTAL,
        'Ajax Amsterdam' => ClubProfile::REPUTATION_CONTINENTAL,
        'Galatasaray' => ClubProfile::REPUTATION_CONTINENTAL,
        'Sporting CP' => ClubProfile::REPUTATION_CONTINENTAL,
        'Celtic FC' => ClubProfile::REPUTATION_CONTINENTAL,
        'Fenerbahce' => ClubProfile::REPUTATION_CONTINENTAL,
        'Feyenoord Rotterdam' => ClubProfile::REPUTATION_CONTINENTAL,
        'PSV Eindhoven' => ClubProfile::REPUTATION_CONTINENTAL,
        'Olympiacos Piraeus' => ClubProfile::REPUTATION_CONTINENTAL,
        'Red Bull Salzburg' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established
        'Club Brugge KV' => ClubProfile::REPUTATION_ESTABLISHED,
        'SC Braga' => ClubProfile::REPUTATION_ESTABLISHED,
        'FC Copenhagen' => ClubProfile::REPUTATION_ESTABLISHED,
        'Rangers FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Red Star Belgrade' => ClubProfile::REPUTATION_ESTABLISHED,
        'SK Slavia Prague' => ClubProfile::REPUTATION_ESTABLISHED,
        'Ferencvárosi TC' => ClubProfile::REPUTATION_ESTABLISHED,
        'SK Sturm Graz' => ClubProfile::REPUTATION_ESTABLISHED,
        'FC Basel 1893' => ClubProfile::REPUTATION_ESTABLISHED,
        'PAOK Thessaloniki' => ClubProfile::REPUTATION_ESTABLISHED,
        'Panathinaikos FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'GNK Dinamo Zagreb' => ClubProfile::REPUTATION_ESTABLISHED,
        'BSC Young Boys' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest
        'KRC Genk' => ClubProfile::REPUTATION_MODEST,
        'Union Saint-Gilloise' => ClubProfile::REPUTATION_MODEST,
        'Malmö FF' => ClubProfile::REPUTATION_MODEST,
        'FK Bodø/Glimt' => ClubProfile::REPUTATION_MODEST,
        'FC Midtjylland' => ClubProfile::REPUTATION_MODEST,
        'FCSB' => ClubProfile::REPUTATION_MODEST,
        'FC Viktoria Plzen' => ClubProfile::REPUTATION_MODEST,
        'Ludogorets Razgrad' => ClubProfile::REPUTATION_MODEST,
        'Maccabi Tel Aviv' => ClubProfile::REPUTATION_MODEST,
        'FC Utrecht' => ClubProfile::REPUTATION_MODEST,
        'SK Brann' => ClubProfile::REPUTATION_MODEST,
        'Qarabağ FK' => ClubProfile::REPUTATION_MODEST,
        'Go Ahead Eagles' => ClubProfile::REPUTATION_MODEST,
        'Pafos FC' => ClubProfile::REPUTATION_MODEST,
        'Kairat Almaty' => ClubProfile::REPUTATION_MODEST,
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
