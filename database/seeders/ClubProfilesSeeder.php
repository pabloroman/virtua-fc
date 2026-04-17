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

    /**
     * Curated per-club fan_loyalty on a 0-10 editorial scale. Anchor for
     * TeamReputation.base_loyalty at game start. Only clubs whose loyalty
     * differs from the neutral midpoint need an entry; everyone else
     * defaults to ClubProfile::FAN_LOYALTY_DEFAULT (5).
     *
     * With the DemandCurveService formula (0.50 + loyalty/100 × 0.45),
     * each loyalty point shifts the base fill rate by ~4.5 percentage
     * points. Calibrated against real La Liga / La Liga 2 occupancy:
     *
     *   10 → ~95%  iconic / cult (Racing 93.7%)
     *    9 → ~90%  huge passionate (Athletic 89.8%, Valencia 89.9%)
     *    8 → ~86%  strong (Real Madrid 87.5%, Osasuna 87.0%)
     *    7 → ~82%  good (Rayo 81.3%, Sevilla 79.4%)
     *    6 → ~77%  above avg (Villarreal 77.1%, Espanyol 75.7%)
     *    5 → ~73%  avg (default) (Deportivo 71.0%, Sporting 70.8%)
     *    4 → ~68%  below avg (Barcelona 67.7%, Granada 67.8%)
     *    3 → ~64%  small (Cádiz 64.1%, Huesca 63.4%)
     *    2 → ~59%  low (Valladolid 59.0%, Eibar 57.5%)
     *    1 → ~55%  very low (Mirandés 54.2%)
     *    0 → ~50%  minimal (Getafe 48.7%, Andorra 45.2%)
     */
    private const FAN_LOYALTY_OVERRIDES = [
        // ── Spain — La Liga ──────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data.
        'Real Madrid' => 8,              // 87.5%
        'FC Barcelona' => 6,             // 67.7%
        'Atlético de Madrid' => 8,       // 87.2%
        'Athletic Bilbao' => 9,          // 89.8%
        'Real Betis Balompié' => 7,      // 84.1%
        'Villarreal CF' => 5,            // 77.1%
        'Sevilla FC' => 7,              // 79.4%
        'Real Sociedad' => 7,            // 78.7%
        'Valencia CF' => 9,              // 89.9%
        'RCD Espanyol Barcelona' => 7,   // 75.7%
        'Celta de Vigo' => 9,            // 89.0%
        'RCD Mallorca' => 5,             // 66.8%
        'CA Osasuna' => 8,              // 87.0%
        'Getafe CF' => 3,               // 48.7%
        'Rayo Vallecano' => 7,           // 81.3%
        'Girona FC' => 4,               // 79.5%
        'Deportivo Alavés' => 7,         // 83.2%
        'Elche CF' => 6,                // 84.3%
        'Levante UD' => 4,              // 76.1%
        'Real Oviedo' => 7,             // 83.0%

        // ── Spain — La Liga 2 ────────────────────────────────────────
        'Racing Santander' => 7,        // 93.7%
        'Málaga CF' => 7,               // 82.6%
        'Deportivo de La Coruña' => 6,   // 71.0%
        'Sporting Gijón' => 5,          // 70.8%
        'Real Zaragoza' => 5,            // 74.1%
        'Córdoba CF' => 5,              // 72.2%
        'CD Castellón' => 6,             // 75.3%
        'Burgos CF' => 6,               // 74.9%
        'Cultural Leonesa' => 4,         // 74.4%
        'AD Ceuta FC' => 3,             // 72.8%
        'UD Almería' => 4,              // 69.5%
        'Granada CF' => 4,              // 67.8%
        'CD Leganés' => 4,              // 69.1%
        'Albacete Balompié' => 3,        // 64.6%
        'Cádiz CF' => 5,                // 64.1%
        'SD Huesca' => 3,               // 63.4%
        'Real Valladolid CF' => 3,       // 59.0%
        'UD Las Palmas' => 4,            // 57.4%
        'SD Eibar' => 4,                // 57.5%
        'CD Mirandés' => 4,             // 54.2%
        'Real Sociedad B' => 2,          // 65.4%
        'FC Andorra' => 3,              // 45.2%

        // ── England ──────────────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data. English football
        // runs near-capacity across the board — every club in the data
        // set exceeds 91%, so loyalty 10 for all.
        'Nottingham Forest' => 8,       // 100.1%
        'West Ham United' => 8,         // 99.9%
        'Newcastle United' => 9,        // 99.7%
        'Brentford FC' => 7,           // 99.3%
        'Arsenal FC' => 8,             // 99.2%
        'Manchester United' => 8,       // 98.8%
        'AFC Bournemouth' => 7,         // 98.8%
        'Everton FC' => 8,             // 98.7%
        'Liverpool FC' => 8,           // 98.6%
        'Brighton & Hove Albion' => 6,  // 98.4%
        'Crystal Palace' => 7,          // 97.7%
        'Aston Villa' => 8,            // 97.5%
        'Tottenham Hotspur' => 7,       // 97.0%
        'Leeds United' => 6,           // 96.9%
        'Burnley FC' => 6,             // 95.4%
        'Chelsea FC' => 7,             // 95.3%
        'Sunderland AFC' => 7,         // 95.2%
        'Manchester City' => 6,         // 94.8%
        'Wolverhampton Wanderers' => 6, // 94.0%
        'Fulham FC' => 6,               // 91.8%

        // ── Germany ──────────────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data. The Bundesliga's
        // 50+1 rule, standing sections, and cheap tickets produce near-
        // universal sellouts — almost every club sits at loyalty 10.
        'Bayern Munich' => 10,           // 100.0%
        'Borussia Dortmund' => 10,       // 100.0%
        'Hamburger SV' => 9,           // 99.9%
        '1.FC Union Berlin' => 9,       // 99.9%
        'FC St. Pauli' => 9,           // 99.8%
        '1.FC Köln' => 9,              // 99.8%
        'Bayer 04 Leverkusen' => 8,     // 99.4%
        'Eintracht Frankfurt' => 8,     // 99.3%
        'SV Werder Bremen' => 8,        // 98.8%
        'SC Freiburg' => 6,            // 98.8%
        '1.FC Heidenheim 1846' => 7,    // 98.7%
        'VfB Stuttgart' => 5,          // 97.9%
        'FC Augsburg' => 5,            // 96.8%
        '1.FSV Mainz 05' => 6,         // 95.0%
        'Borussia Mönchengladbach' => 7, // 94.0%
        'RB Leipzig' => 5,              // 92.8%
        'TSG 1899 Hoffenheim' => 4,      // 86.6%
        'VfL Wolfsburg' => 4,            // 83.8%

        // ── France ───────────────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data.
        'RC Strasbourg Alsace' => 7,    // 104.7% (standing overfill)
        'RC Lens' => 7,                // 98.2%
        'Paris Saint-Germain' => 8,     // 97.8%
        'Stade Brestois 29' => 8,       // 95.0%
        'Olympique Marseille' => 9,     // 93.2%
        'Stade Rennais FC' => 7,        // 93.2%
        'FC Lorient' => 8,              // 90.6%
        'AJ Auxerre' => 7,             // 88.3%
        'LOSC Lille' => 7,              // 85.5%
        'Paris FC' => 6,                // 84.1%
        'Olympique Lyon' => 6,          // 82.8%
        'FC Metz' => 7,                 // 78.5%
        'FC Nantes' => 6,               // 78.2%
        'Le Havre AC' => 6,             // 75.7%
        'FC Toulouse' => 5,             // 73.8%
        'Angers SCO' => 3,              // 64.4%
        'OGC Nice' => 2,                // 60.1%
        'AS Monaco' => 1,               // 43.8%

        // ── Italy ────────────────────────────────────────────────────
        // Calibrated from real 2024-25 occupancy data.
        'Cagliari Calcio' => 8,         // 98.0%
        'Juventus FC' => 9,             // 96.9%
        'AC Milan' => 9,               // 94.2%
        'SSC Napoli' => 9,             // 93.3%
        'Inter Milan' => 8,              // 92.4%
        'Atalanta BC' => 7,             // 90.9%
        'AS Roma' => 8,                 // 88.4%
        'Genoa CFC' => 7,              // 88.8%
        'Como 1907' => 7,               // 87.4%
        'Udinese Calcio' => 8,          // 86.8%
        'Venezia FC' => 6,              // 86.2%
        'Parma Calcio 1913' => 6,        // 85.6%
        'Torino FC' => 6,               // 82.8%
        'US Lecce' => 6,                // 82.4%
        'Bologna FC 1909' => 5,          // 76.7%
        'AC Monza' => 3,                // 64.9%
        'Hellas Verona' => 3,            // 63.5%
        'SS Lazio' => 3,                // 62.4%
        'FC Empoli' => 2,               // 54.3%
        'ACF Fiorentina' => 3,           // 47.2%
    ];

    public function run(): void
    {
        $allTeams = Team::all();
        $seeded = 0;

        foreach ($allTeams as $team) {
            $reputation = self::CLUB_DATA[$team->name] ?? ClubProfile::REPUTATION_LOCAL;
            $fanLoyalty = self::FAN_LOYALTY_OVERRIDES[$team->name]
                ?? ClubProfile::FAN_LOYALTY_DEFAULT;

            ClubProfile::updateOrCreate(
                ['team_id' => $team->id],
                [
                    'reputation_level' => $reputation,
                    'fan_loyalty' => $fanLoyalty,
                ]
            );

            $seeded++;
        }

        $this->command->info('Club profiles seeded for ' . $seeded . ' teams');
    }
}
