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
        'Athletic Club' => ClubProfile::REPUTATION_CONTINENTAL,
        'Villarreal CF' => ClubProfile::REPUTATION_CONTINENTAL,
        'Real Betis Balompié' => ClubProfile::REPUTATION_CONTINENTAL,
        'Sevilla FC' => ClubProfile::REPUTATION_CONTINENTAL,
        'Real Sociedad' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established - Objetivo: Top 10
        'Valencia CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'RCD Espanyol Barcelona' => ClubProfile::REPUTATION_ESTABLISHED,
        'RC Celta' => ClubProfile::REPUTATION_ESTABLISHED,
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
        // Spain - Primera RFEF (ESP3A + ESP3B)
        // =============================================

        // Modest (mid-profile, recently in La Liga 2 or solid tier-3 veterans) - Objetivo: Top 10
        'CD Tenerife' => ClubProfile::REPUTATION_MODEST,
        'Real Murcia CF' => ClubProfile::REPUTATION_MODEST,
        'Hércules CF' => ClubProfile::REPUTATION_MODEST,
        'Racing Ferrol' => ClubProfile::REPUTATION_MODEST,
        'SD Ponferradina' => ClubProfile::REPUTATION_MODEST,
        'CD Lugo' => ClubProfile::REPUTATION_MODEST,
        'FC Cartagena' => ClubProfile::REPUTATION_MODEST,
        'Gimnàstic de Tarragona' => ClubProfile::REPUTATION_MODEST,
        'CE Sabadell FC' => ClubProfile::REPUTATION_MODEST,
        'Algeciras CF' => ClubProfile::REPUTATION_MODEST,
        'UD Ibiza' => ClubProfile::REPUTATION_MODEST,
        'CD Eldense' => ClubProfile::REPUTATION_MODEST,
        'AD Alcorcón' => ClubProfile::REPUTATION_MODEST,

        // Local (small regional clubs and B-teams) - Objetivo: No descender.
        // B-teams (Castilla, Bilbao Athletic, Villarreal B, Sevilla Atlético,
        // Atlético Madrileño, Celta Fortuna, Betis Deportivo, Osasuna Promesas)
        // stay LOCAL since they cannot promote to La Liga 2 — their ambition
        // is developmental, not sporting.
        'Mérida AD' => ClubProfile::REPUTATION_LOCAL,
        'Pontevedra CF' => ClubProfile::REPUTATION_LOCAL,
        'Real Madrid Castilla' => ClubProfile::REPUTATION_LOCAL,
        'Barakaldo CF' => ClubProfile::REPUTATION_LOCAL,
        'Zamora CF' => ClubProfile::REPUTATION_LOCAL,
        'CD Guadalajara' => ClubProfile::REPUTATION_LOCAL,
        'CP Cacereño' => ClubProfile::REPUTATION_LOCAL,
        'Ourense CF' => ClubProfile::REPUTATION_LOCAL,
        'Real Avilés Industrial' => ClubProfile::REPUTATION_LOCAL,
        'Unionistas CF' => ClubProfile::REPUTATION_LOCAL,
        'CD Arenteiro' => ClubProfile::REPUTATION_LOCAL,
        'CF Talavera de la Reina' => ClubProfile::REPUTATION_LOCAL,
        'CA Osasuna Promesas' => ClubProfile::REPUTATION_LOCAL,
        'Bilbao Athletic' => ClubProfile::REPUTATION_LOCAL,
        'Arenas Club' => ClubProfile::REPUTATION_LOCAL,
        'RC Celta Fortuna' => ClubProfile::REPUTATION_LOCAL,
        'Villarreal CF B' => ClubProfile::REPUTATION_LOCAL,
        'Marbella FC' => ClubProfile::REPUTATION_LOCAL,
        'Sevilla Atlético' => ClubProfile::REPUTATION_LOCAL,
        'Antequera CF' => ClubProfile::REPUTATION_LOCAL,
        'CD Teruel' => ClubProfile::REPUTATION_LOCAL,
        'Atlético Sanluqueño CF' => ClubProfile::REPUTATION_LOCAL,
        'CE Europa' => ClubProfile::REPUTATION_LOCAL,
        'Atlético Madrileño' => ClubProfile::REPUTATION_LOCAL,
        'Juventud Torremolinos CF' => ClubProfile::REPUTATION_LOCAL,
        'SD Tarazona' => ClubProfile::REPUTATION_LOCAL,
        'Betis Deportivo Balompié' => ClubProfile::REPUTATION_LOCAL,

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

        // =============================================
        // International transfer pool (INT)
        // Non-European clubs available for transfers/loans only.
        // =============================================

        // Continental — South American giants with global reach
        'CA Boca Juniors' => ClubProfile::REPUTATION_CONTINENTAL,
        'CA River Plate' => ClubProfile::REPUTATION_CONTINENTAL,
        'CR Flamengo' => ClubProfile::REPUTATION_CONTINENTAL,
        'SE Palmeiras' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established — Saudi Pro League powerhouses with global star rosters
        'Al-Hilal SFC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Al-Nassr FC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Al-Ittihad Club' => ClubProfile::REPUTATION_ESTABLISHED,
        'Al-Ahli SFC' => ClubProfile::REPUTATION_ESTABLISHED,

        // Established — strong domestic clubs and high-profile MLS sides
        'SC Corinthians Paulista' => ClubProfile::REPUTATION_ESTABLISHED,
        'Botafogo FR' => ClubProfile::REPUTATION_ESTABLISHED,
        'Cruzeiro EC' => ClubProfile::REPUTATION_ESTABLISHED,
        'Racing Club' => ClubProfile::REPUTATION_ESTABLISHED,
        'Club Estudiantes de La Plata' => ClubProfile::REPUTATION_ESTABLISHED,
        'CA Rosario Central' => ClubProfile::REPUTATION_ESTABLISHED,
        'Inter Miami CF' => ClubProfile::REPUTATION_ESTABLISHED,
        'Los Angeles FC' => ClubProfile::REPUTATION_ESTABLISHED,
    ];

    /**
     * Curated reputation tier for national teams keyed by FIFA code.
     * Reputation drives AI mentality, instructions, and the formation-bias
     * fallback pool for any national-team match (currently World Cup
     * tournament mode). Without this, every national team would default to
     * REPUTATION_LOCAL and behave like a small-club squad regardless of
     * stature.
     *
     * Calibrated against current strength (FIFA ranking + recent tournament
     * performance) rather than historic prestige alone — Hungary is not on
     * this list because they aren't at WC2026; Morocco sits at CONTINENTAL
     * after their 2022 semifinal run, etc.
     */
    private const NATIONAL_TEAM_REPUTATION = [
        // Elite — title contenders
        'ARG' => ClubProfile::REPUTATION_ELITE,        // World champions
        'BRA' => ClubProfile::REPUTATION_ELITE,
        'FRA' => ClubProfile::REPUTATION_ELITE,
        'ESP' => ClubProfile::REPUTATION_ELITE,        // Euro 2024 champions
        'ENG' => ClubProfile::REPUTATION_ELITE,
        'GER' => ClubProfile::REPUTATION_ELITE,
        'POR' => ClubProfile::REPUTATION_ELITE,
        'NED' => ClubProfile::REPUTATION_ELITE,

        // Continental — strong regular contenders
        'BEL' => ClubProfile::REPUTATION_CONTINENTAL,
        'CRO' => ClubProfile::REPUTATION_CONTINENTAL, // 2022 semifinalists
        'URU' => ClubProfile::REPUTATION_CONTINENTAL,
        'COL' => ClubProfile::REPUTATION_CONTINENTAL, // Copa America 2024 finalist
        'MAR' => ClubProfile::REPUTATION_CONTINENTAL, // 2022 semifinalists
        'SEN' => ClubProfile::REPUTATION_CONTINENTAL,
        'SUI' => ClubProfile::REPUTATION_CONTINENTAL,
        'MEX' => ClubProfile::REPUTATION_CONTINENTAL,
        'USA' => ClubProfile::REPUTATION_CONTINENTAL,
        'JPN' => ClubProfile::REPUTATION_CONTINENTAL,
        'TUR' => ClubProfile::REPUTATION_CONTINENTAL,

        // Established — qualified regularly, mid-tier
        'SWE' => ClubProfile::REPUTATION_ESTABLISHED,
        'NOR' => ClubProfile::REPUTATION_ESTABLISHED, // Haaland-led generation
        'AUT' => ClubProfile::REPUTATION_ESTABLISHED,
        'EGY' => ClubProfile::REPUTATION_ESTABLISHED,
        'CIV' => ClubProfile::REPUTATION_ESTABLISHED, // AFCON 2023 winners
        'IRN' => ClubProfile::REPUTATION_ESTABLISHED,
        'KOR' => ClubProfile::REPUTATION_ESTABLISHED,
        'AUS' => ClubProfile::REPUTATION_ESTABLISHED,
        'CZE' => ClubProfile::REPUTATION_ESTABLISHED,
        'SCO' => ClubProfile::REPUTATION_ESTABLISHED,
        'ECU' => ClubProfile::REPUTATION_ESTABLISHED,
        'PAR' => ClubProfile::REPUTATION_ESTABLISHED,
        'ALG' => ClubProfile::REPUTATION_ESTABLISHED,
        'TUN' => ClubProfile::REPUTATION_ESTABLISHED,
        'GHA' => ClubProfile::REPUTATION_ESTABLISHED,
        'KSA' => ClubProfile::REPUTATION_ESTABLISHED,

        // Modest — first-time or sporadic qualifiers
        'QAT' => ClubProfile::REPUTATION_MODEST,
        'BIH' => ClubProfile::REPUTATION_MODEST,
        'CAN' => ClubProfile::REPUTATION_MODEST,
        'NZL' => ClubProfile::REPUTATION_MODEST,
        'CPV' => ClubProfile::REPUTATION_MODEST,
        'COD' => ClubProfile::REPUTATION_MODEST,
        'IRQ' => ClubProfile::REPUTATION_MODEST,
        'JOR' => ClubProfile::REPUTATION_MODEST,
        'UZB' => ClubProfile::REPUTATION_MODEST,
        'PAN' => ClubProfile::REPUTATION_MODEST,
        'CUR' => ClubProfile::REPUTATION_MODEST,

        // Local — outsiders / debutants
        'RSA' => ClubProfile::REPUTATION_LOCAL,
        'HAI' => ClubProfile::REPUTATION_LOCAL,
    ];

    /**
     * Curated preferred formation for national teams keyed by FIFA code.
     * Reflects current managerial identity. Unlisted national teams fall
     * back to the reputation-tier formation pool in FormationBiasResolver.
     */
    private const NATIONAL_TEAM_PREFERRED_FORMATION = [
        'ARG' => '4-1-2-3',
        'BRA' => '4-2-1-3',
        'FRA' => '4-1-2-3',
        'ESP' => '4-2-1-3',
        'ENG' => '3-4-3',
        'GER' => '4-2-3-1',
        'POR' => '4-3-3',
        'NED' => '4-3-3',
        'BEL' => '3-4-3',
        'CRO' => '4-3-3',
        'URU' => '4-4-2',
        'COL' => '4-2-3-1',
        'MAR' => '4-3-3',
        'SEN' => '4-3-3',
        'SUI' => '4-2-3-1',
        'MEX' => '4-3-3',
        'USA' => '4-3-3',
        'JPN' => '4-2-3-1',
        'TUR' => '4-2-3-1',
        'NOR' => '4-3-3',
        'AUT' => '4-3-3',
        'KOR' => '4-2-3-1',
        'AUS' => '4-2-3-1',
        'IRN' => '5-4-1',
        'KSA' => '4-2-3-1',
        'QAT' => '5-3-2',
        'PAR' => '4-4-2',
    ];

    /**
     * Curated tactical aggression (-2..+2) for national teams keyed by FIFA
     * code. International football skews more conservative than club football
     * (cup format, less drilled-in pressing), so most teams sit at 0; only
     * sides with a pronounced identity shift one or two notches.
     */
    private const NATIONAL_TEAM_TACTICAL_AGGRESSION = [
        'ESP' => 1,
        'BRA' => 1,
        'POR' => 1,
        'NED' => 1,
        'AUT' => 1,
        'JPN' => 1,
        'SEN' => 1,
        'MAR' => -1,
        'URU' => -1,
        'SUI' => -1,
        'IRN' => -2,
        'KSA' => -1,
        'QAT' => -1,
        'NZL' => -1,
        'JOR' => -1,
        'PAN' => -1,
        'HAI' => -1,
        'RSA' => -1,
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
        'Athletic Club' => 9,            // 89.8%
        'Real Betis Balompié' => 7,      // 84.1%
        'Villarreal CF' => 5,            // 77.1%
        'Sevilla FC' => 7,              // 79.4%
        'Real Sociedad' => 7,            // 78.7%
        'Valencia CF' => 9,              // 89.9%
        'RCD Espanyol Barcelona' => 7,   // 75.7%
        'RC Celta' => 9,                 // 89.0%
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

    /**
     * Curated per-club preferred formation. Captures real-world tactical
     * identity so AI opponents feel distinct rather than every club
     * defaulting to 4-3-3. Used by FormationRecommender as a positive
     * bias on formation scoring; the recommender still overrides when
     * squad makeup makes the preferred shape genuinely unviable.
     *
     * Only clubs with a clear tactical identity are listed. Unlisted
     * clubs fall back to a reputation-tier formation pool (see
     * FormationBiasResolver).
     */
    private const PREFERRED_FORMATION_OVERRIDES = [
        // ── Spain — La Liga ──────────────────────────────────────────
        'Real Madrid' => '4-3-1-2',
        'FC Barcelona' => '4-2-1-3',
        'Atlético de Madrid' => '4-4-2',
        'Athletic Club' => '4-2-1-3',
        'Villarreal CF' => '4-4-2',
        'Real Betis Balompié' => '4-1-2-3',
        'Sevilla FC' => '3-4-3',
        'Real Sociedad' => '4-2-1-3',
        'Valencia CF' => '4-2-1-3',
        'RCD Espanyol Barcelona' => '4-1-2-3',
        'RC Celta' => '3-4-3',
        'RCD Mallorca' => '4-3-1-2',
        'CA Osasuna' => '4-2-1-3',
        'Getafe CF' => '5-3-2',
        'Rayo Vallecano' => '4-2-1-3',
        'Girona FC' => '4-2-1-3',
        'Deportivo Alavés' => '3-5-2',
        'Elche CF' => '5-3-2',
        'Levante UD' => '4-1-2-3',
        'Real Oviedo' => '4-2-1-3',

        // ── Spain — La Liga 2 ────────────────────────────────────────
        'Deportivo de La Coruña' => '4-2-3-1',
        'Málaga CF' => '4-2-3-1',
        'Sporting Gijón' => '4-2-3-1',
        'UD Las Palmas' => '4-3-3',
        'Real Valladolid CF' => '4-4-2',
        'Granada CF' => '4-2-3-1',
        'Cádiz CF' => '5-4-1',
        'Racing Santander' => '4-3-3',
        'UD Almería' => '4-2-3-1',
        'Real Zaragoza' => '4-4-2',
        'Córdoba CF' => '4-2-3-1',
        'CD Castellón' => '4-3-3',
        'Albacete Balompié' => '4-2-3-1',
        'SD Huesca' => '4-2-3-1',
        'SD Eibar' => '4-4-2',
        'CD Leganés' => '4-2-3-1',
        'Burgos CF' => '4-4-2',
        'Cultural Leonesa' => '4-4-2',
        'CD Mirandés' => '4-2-3-1',
        'AD Ceuta FC' => '4-4-2',
        'FC Andorra' => '4-3-3',
        'Real Sociedad B' => '4-3-3',

        // ── England ──────────────────────────────────────────────────
        'Manchester City' => '4-3-3',             // Guardiola
        'Liverpool FC' => '4-3-3',
        'Arsenal FC' => '4-3-3',                  // Arteta
        'Chelsea FC' => '4-2-3-1',
        'Manchester United' => '3-4-3',           // Amorim
        'Tottenham Hotspur' => '4-3-3',
        'Newcastle United' => '4-3-3',
        'Aston Villa' => '4-2-3-1',               // Emery
        'West Ham United' => '4-2-3-1',
        'Everton FC' => '4-2-3-1',
        'Brighton & Hove Albion' => '4-2-3-1',
        'Crystal Palace' => '3-4-3',              // Glasner
        'Wolverhampton Wanderers' => '3-4-3',
        'Leeds United' => '4-2-3-1',
        'Nottingham Forest' => '4-2-3-1',
        'Fulham FC' => '4-2-3-1',
        'Brentford FC' => '4-3-3',
        'AFC Bournemouth' => '4-2-3-1',
        'Sunderland AFC' => '4-2-3-1',
        'Burnley FC' => '4-4-2',

        // ── Germany ──────────────────────────────────────────────────
        'Bayern Munich' => '4-2-3-1',
        'Borussia Dortmund' => '4-2-3-1',
        'Bayer 04 Leverkusen' => '3-4-3',         // Alonso shape
        'Eintracht Frankfurt' => '3-4-3',
        'RB Leipzig' => '4-2-3-1',
        '1.FC Union Berlin' => '5-3-2',           // Compact block
        'FC St. Pauli' => '3-4-3',
        '1.FC Heidenheim 1846' => '4-4-2',

        // ── France ───────────────────────────────────────────────────
        'Paris Saint-Germain' => '4-3-3',         // Luis Enrique
        'Olympique Marseille' => '4-2-3-1',       // De Zerbi
        'RC Lens' => '3-4-3',
        'Stade Brestois 29' => '4-4-2',

        // ── Italy ────────────────────────────────────────────────────
        'Inter Milan' => '3-5-2',                 // Inzaghi trademark
        'Juventus FC' => '4-2-3-1',
        'AC Milan' => '4-2-3-1',
        'SSC Napoli' => '4-3-3',                  // Conte 4-3-3 base
        'Atalanta BC' => '3-4-3',                 // Gasperini
        'AS Roma' => '3-4-3',
        'SS Lazio' => '4-3-3',
        'ACF Fiorentina' => '4-2-3-1',
        'Bologna FC 1909' => '4-2-3-1',
        'Torino FC' => '3-5-2',
        'Genoa CFC' => '3-5-2',
        'Udinese Calcio' => '3-5-2',
        'Cagliari Calcio' => '4-4-2',
        'Hellas Verona' => '3-4-3',
        'US Cremonese' => '3-5-2',

        // ── European pool ────────────────────────────────────────────
        'SL Benfica' => '4-3-3',
        'FC Porto' => '4-2-3-1',
        'Ajax Amsterdam' => '4-3-3',
        'Sporting CP' => '3-4-3',                 // Amorim legacy shape
        'Celtic FC' => '4-3-3',
        'Feyenoord Rotterdam' => '4-3-3',
        'PSV Eindhoven' => '4-3-3',

        // ── International pool ───────────────────────────────────────
        'CA Boca Juniors' => '4-3-3',
        'CA River Plate' => '4-3-3',
        'CR Flamengo' => '4-2-3-1',
        'SE Palmeiras' => '4-3-3',
        'Al-Hilal SFC' => '4-3-3',
    ];

    /**
     * Curated per-club tactical aggression on a -2..+2 scale. Captures
     * how much more attacking (or more cautious) a club is than its
     * reputation tier alone would suggest. Used by LineupService to
     * shift mentality, pressing, defensive line, and playing style
     * one or two notches up/down the ladder.
     *
     *   +2 — extreme front-foot (Gasperini's Atalanta, peak Pep)
     *   +1 — high-press, attacking by default
     *    0 — tier-typical (the implicit default)
     *   -1 — pragmatic / cautious
     *   -2 — deep low-block (Simeone, Bordalás)
     *
     * Only clubs whose tactical identity meaningfully diverges from
     * the tier-typical baseline need an entry; everyone else stays at 0.
     */
    private const TACTICAL_AGGRESSION_OVERRIDES = [
        // ── Spain — La Liga ──────────────────────────────────────────
        'FC Barcelona' => 1,                      // Flick possession-press
        'Atlético de Madrid' => -2,               // Cholismo trademark
        'Athletic Club' => 1,                     // Valverde aggressive press
        'Real Sociedad' => 1,                     // Imanol high-tempo
        'Valencia CF' => -1,
        'RCD Espanyol Barcelona' => -1,
        'RC Celta' => 1,                          // Giráldez attacking
        'RCD Mallorca' => -2,                     // Aguirre block
        'CA Osasuna' => -1,
        'Getafe CF' => -2,                        // Bordalás compact
        'Rayo Vallecano' => 1,                    // Iñigo Pérez attacking
        'Girona FC' => 1,                         // Míchel possession
        'Deportivo Alavés' => -1,
        'Real Oviedo' => -1,

        // ── Spain — La Liga 2 ────────────────────────────────────────
        'UD Las Palmas' => 1,                     // Possession identity
        'Cádiz CF' => -2,                         // Survival deep block
        'Racing Santander' => 1,
        'Córdoba CF' => 1,
        'CD Castellón' => 1,
        'SD Eibar' => -1,                         // Compact identity
        'CD Leganés' => -1,
        'AD Ceuta FC' => -1,
        'FC Andorra' => 1,                        // Eder Sarabia possession
        'Real Sociedad B' => 1,                   // Mirrors first team

        // ── England ──────────────────────────────────────────────────
        'Manchester City' => 2,                   // Pep extreme press
        'Liverpool FC' => 1,
        'Arsenal FC' => 1,                        // Arteta front-foot
        'Tottenham Hotspur' => 1,
        'Newcastle United' => 1,                  // Howe pressing
        'Brighton & Hove Albion' => 1,            // Progressive system
        'Everton FC' => -1,
        'Nottingham Forest' => -1,

        // ── Germany ──────────────────────────────────────────────────
        'Bayern Munich' => 1,
        'Borussia Dortmund' => 1,
        'Bayer 04 Leverkusen' => 1,               // Alonso possession-press
        'RB Leipzig' => 1,                        // Red Bull press
        '1.FC Union Berlin' => -2,                // Compact 5-3-2
        'FC Augsburg' => -1,
        '1.FC Heidenheim 1846' => -1,

        // ── France ───────────────────────────────────────────────────
        'Paris Saint-Germain' => 1,               // Luis Enrique press
        'Olympique Marseille' => 1,               // De Zerbi
        'RC Lens' => 1,
        'Angers SCO' => -1,

        // ── Italy ────────────────────────────────────────────────────
        'SSC Napoli' => 1,                        // Conte intense
        'Atalanta BC' => 2,                       // Gasperini all-out
        'Bologna FC 1909' => 1,                   // Italiano
        'Cagliari Calcio' => -1,
        'Como 1907' => 1,                         // Fabregas progressive

        // ── European pool ────────────────────────────────────────────
        'SL Benfica' => 1,
        'Ajax Amsterdam' => 1,
        'Sporting CP' => 1,
        'Celtic FC' => 1,
        'Feyenoord Rotterdam' => 1,
        'PSV Eindhoven' => 1,
        'Red Bull Salzburg' => 1,                 // Red Bull press

        // ── International pool ───────────────────────────────────────
        'CA River Plate' => 1,                    // Gallardo attacking
        'Al-Hilal SFC' => 1,
        'Inter Miami CF' => 1,
    ];

    public function run(): void
    {
        $allTeams = Team::all();
        $seeded = 0;

        foreach ($allTeams as $team) {
            // National teams key on fifa_code (locale-independent and stable),
            // because Team::name applies the countries.* translation accessor
            // for type='national' rows and would otherwise miss the lookup.
            $isNational = $team->getRawOriginal('type') === 'national';
            $rawName = $team->getRawOriginal('name');
            $fifaCode = $team->fifa_code;

            if ($isNational && $fifaCode) {
                $reputation = self::NATIONAL_TEAM_REPUTATION[$fifaCode] ?? ClubProfile::REPUTATION_LOCAL;
                $preferredFormation = self::NATIONAL_TEAM_PREFERRED_FORMATION[$fifaCode] ?? null;
                $tacticalAggression = self::NATIONAL_TEAM_TACTICAL_AGGRESSION[$fifaCode] ?? 0;
                // Fan loyalty doesn't apply to national teams (no club fanbase
                // demand curve), so we leave it at the neutral default.
                $fanLoyalty = ClubProfile::FAN_LOYALTY_DEFAULT;
            } else {
                $reputation = self::CLUB_DATA[$rawName] ?? ClubProfile::REPUTATION_LOCAL;
                $fanLoyalty = self::FAN_LOYALTY_OVERRIDES[$rawName]
                    ?? ClubProfile::FAN_LOYALTY_DEFAULT;
                $preferredFormation = self::PREFERRED_FORMATION_OVERRIDES[$rawName] ?? null;
                $tacticalAggression = self::TACTICAL_AGGRESSION_OVERRIDES[$rawName] ?? 0;
            }

            ClubProfile::updateOrCreate(
                ['team_id' => $team->id],
                [
                    'reputation_level' => $reputation,
                    'fan_loyalty' => $fanLoyalty,
                    'preferred_formation' => $preferredFormation,
                    'tactical_aggression' => $tacticalAggression,
                ]
            );

            $seeded++;
        }

        $this->command->info('Club profiles seeded for ' . $seeded . ' teams');
    }
}
