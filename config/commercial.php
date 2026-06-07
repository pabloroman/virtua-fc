<?php

// Commercial revenue levers — proactive, manager-facing income streams that
// lift the salary cap because they feed projected_total_revenue. Split out of
// config/finances.php (which stays focused on the core economic model) so the
// growing commercial surface has a home of its own. Stadium naming rights is
// the first lever; shirt/kit sponsorship and other deals will join it here.

return [

    // ── Stadium Naming Rights ──────────────────────────────────────────
    // Selling the stadium name to a sponsor: recurring income that settles
    // proportional to attendance, paid for in a one-time fan-loyalty shock.
    // Offers are generated each pre-season and can only be signed in the
    // pre-season window (through the first league matchday).
    'naming_rights' => [
        // How many offers a single "seek sponsors" search puts on the table —
        // also the cap on how many can sit pending at once. The manager seeks
        // proactively from the Commercial page (there is no random arrival);
        // each search tops the board up to this many reputation-weighted offers.
        'max_pending_offers' => 3,

        // Cost of engaging a commercial agency to canvass sponsors, charged
        // per "seek sponsors" search by reputation tier (cents). Together with
        // the cooldown this is the friction that stops sponsor income from
        // becoming free money on tap — a club can't endlessly re-roll the
        // board for the top headline value at no cost.
        'search_fee' => [
            'elite'        => 500_000_00, // €500K
            'continental'  => 250_000_00, // €250K
            'established'  =>  80_000_00, // €80K
            'modest'       =>  25_000_00, // €25K
            'local'        =>  10_000_00, // €10K
        ],

        // Minimum game-calendar days between two searches. The pre-season
        // identity window is short, so burning days to re-seek is a real cost
        // on top of the fee.
        'search_cooldown_days' => 14,

        // One-time loyalty hit at signing = round(base_loyalty × factor),
        // floored by the existing base_loyalty − 15 loyalty floor. Scaling by
        // base_loyalty makes cult clubs (high base) pay the steepest price.
        'loyalty_shock_factor' => 0.12,

        // [min, max] annual headline value by reputation tier (in cents),
        // calibrated to real-world stadium naming-rights benchmarks. The
        // realised payout scales down from here with stadium fill, so a half-
        // empty ground pays the sponsor proportionally less than the headline.
        //
        // The curve is deliberately non-linear: it compresses sharply below the
        // continental elite (Barça's Spotify deal ≈ €20M/yr, Bayern's Allianz
        // ≈ €13M, Atlético/Dortmund/Juve ≈ €10M, RC Lens ≈ €1.6M), then flattens
        // into the low hundreds of thousands at the local level (a third-tier
        // club like Peterborough ≈ €200K/yr).
        'annual_value' => [
            'elite'        => [15_000_000_00, 30_000_000_00], // €15M–€30M — global superclubs
            'continental'  => [ 5_000_000_00, 13_000_000_00], // €5M–€13M — continental regulars
            'established'  => [ 2_000_000_00,  6_000_000_00], // €2M–€6M — solid top-flight pedigree
            'modest'       => [   500_000_00,  2_000_000_00], // €500K–€2M — lower-half top-flight / strong second tier
            'local'        => [    50_000_00,    400_000_00], // €50K–€400K — third tier and below
        ],

        // Contract length bounds (seasons), chosen per offer.
        'min_contract_seasons' => 1,
        'max_contract_seasons' => 5,

        // Which sponsor reaches bid for each club tier. A brand chases the club
        // stature it can realistically back: global names go after the elite and
        // continental sides, national names after established/modest clubs, and
        // regional names after modest/local sides. This keeps the brand on the
        // stand plausible next to the tiered headline values above — and it is
        // why the `reach` tag on each sponsor below is load-bearing, not decoration.
        'sponsor_reach_by_tier' => [
            'elite'        => ['global'],
            'continental'  => ['global'],
            'established'  => ['national'],
            'modest'       => ['national', 'regional'],
            'local'        => ['regional'],
        ],

        // Brand pool. Each generated offer picks one at random from the sponsors
        // eligible for the club (see pickAvailableSponsor); `stadium` is the
        // name imposed on the ground while the deal is active. Real brands with
        // NO existing stadium-naming precedent and no betting ties.
        //
        // Two gates decide eligibility:
        //  • `reach` ↔ club tier (sponsor_reach_by_tier) — brand size tracks
        //    club stature.
        //  • `country` ↔ club country — a national/regional brand only operates
        //    in its home market, so it can only name a ground in its own country
        //    (no "Greggs" on a Spanish stand). GLOBAL brands are country-agnostic
        //    and carry no `country`: cross-border naming is the norm at the top
        //    (Emirates, Coca-Cola, Qatar Airways name grounds the world over).
        //
        // Each playable league (ES, EN, DE, FR, IT) therefore needs its own
        // national + regional brands; the global pool is shared across them all.
        'sponsors' => [
            // ── Global — elite & continental superclubs ──────────────────
            ['name' => 'Deutsche Telekom', 'reach' => 'global', 'stadium' => 'Telekom Arena'],
            ['name' => 'E.ON',             'reach' => 'global', 'stadium' => 'E.ON Park'],
            ['name' => 'AXA',              'reach' => 'global', 'stadium' => 'AXA Arena'],
            ['name' => 'BNP Paribas',      'reach' => 'global', 'stadium' => 'BNP Paribas Arena'],
            ['name' => 'Société Générale', 'reach' => 'global', 'stadium' => 'Société Générale Stadium'],
            ['name' => 'TotalEnergies',    'reach' => 'global', 'stadium' => 'TotalEnergies Stadium'],
            ['name' => 'Engie',            'reach' => 'global', 'stadium' => 'Engie Arena'],
            ['name' => 'Renault',          'reach' => 'global', 'stadium' => 'Renault Arena'],
            ['name' => 'Michelin',         'reach' => 'global', 'stadium' => 'Michelin Park'],
            ['name' => 'Carrefour',        'reach' => 'global', 'stadium' => 'Carrefour Arena'],
            ['name' => 'Air France',       'reach' => 'global', 'stadium' => 'Air France Stadium'],
            ['name' => 'Generali',         'reach' => 'global', 'stadium' => 'Estadio Generali'],
            ['name' => 'Intesa Sanpaolo',  'reach' => 'global', 'stadium' => 'Intesa Sanpaolo Arena'],
            ['name' => 'UniCredit',        'reach' => 'global', 'stadium' => 'UniCredit Stadium'],
            ['name' => 'Enel',             'reach' => 'global', 'stadium' => 'Enel Arena'],
            ['name' => 'Eni',              'reach' => 'global', 'stadium' => 'Eni Stadium'],
            ['name' => 'Pirelli',          'reach' => 'global', 'stadium' => 'Pirelli Arena'],
            ['name' => 'Lavazza',          'reach' => 'global', 'stadium' => 'Lavazza Arena'],
            ['name' => 'Barilla',          'reach' => 'global', 'stadium' => 'Barilla Stadium'],
            ['name' => 'Campari',          'reach' => 'global', 'stadium' => 'Campari Arena'],
            ['name' => 'Vodafone',         'reach' => 'global', 'stadium' => 'Vodafone Stadium'],
            ['name' => 'Sky',              'reach' => 'global', 'stadium' => 'Sky Arena'],
            ['name' => 'Barclays',         'reach' => 'global', 'stadium' => 'Barclays Stadium'],
            ['name' => 'HSBC',             'reach' => 'global', 'stadium' => 'HSBC Stadium'],
            ['name' => 'British Airways',  'reach' => 'global', 'stadium' => 'British Airways Stadium'],
            ['name' => 'JCB',              'reach' => 'global', 'stadium' => 'JCB Arena'],
            ['name' => 'Santander',        'reach' => 'global', 'stadium' => 'Estadio Santander'],
            ['name' => 'Iberdrola',        'reach' => 'global', 'stadium' => 'Estadio Iberdrola'],
            ['name' => 'Repsol',           'reach' => 'global', 'stadium' => 'Estadio Repsol'],
            ['name' => 'Inditex',          'reach' => 'global', 'stadium' => 'Inditex Arena'],
            ['name' => 'Mango',            'reach' => 'global', 'stadium' => 'Mango Stadium'],
            ['name' => 'Ferrovial',        'reach' => 'global', 'stadium' => 'Ferrovial Arena'],
            ['name' => 'Qatar Airways',    'reach' => 'global', 'stadium' => 'Qatar Airways Stadium'],
            ['name' => 'Turkish Airlines', 'reach' => 'global', 'stadium' => 'Turkish Airlines Arena'],
            ['name' => 'Heineken',         'reach' => 'global', 'stadium' => 'Heineken Arena'],
            ['name' => 'Booking.com',      'reach' => 'global', 'stadium' => 'Booking.com Arena'],
            ['name' => 'Shell',            'reach' => 'global', 'stadium' => 'Shell Stadium'],
            ['name' => 'DHL',              'reach' => 'global', 'stadium' => 'DHL Arena'],
            ['name' => 'Hyundai',          'reach' => 'global', 'stadium' => 'Hyundai Arena'],
            ['name' => 'Visa',             'reach' => 'global', 'stadium' => 'Visa Arena'],
            ['name' => 'Mastercard',       'reach' => 'global', 'stadium' => 'Mastercard Arena'],
            ['name' => 'Coca-Cola',        'reach' => 'global', 'stadium' => 'Coca-Cola Stadium'],
            ['name' => 'Amazon',           'reach' => 'global', 'stadium' => 'Amazon Arena'],
            ['name' => 'Microsoft',        'reach' => 'global', 'stadium' => 'Microsoft Arena'],

            // ── Spain (ES) — national & regional ─────────────────────────
            ['name' => 'CaixaBank',        'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio CaixaBank'],
            ['name' => 'Bankinter',        'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Bankinter'],
            ['name' => 'Mutua Madrileña',  'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Mutua Madrileña'],
            ['name' => 'Endesa',           'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Endesa'],
            ['name' => 'Naturgy',          'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Naturgy'],
            ['name' => 'Cepsa',            'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Cepsa'],
            ['name' => 'Iberia',           'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Iberia'],
            ['name' => 'Vueling',          'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Vueling'],
            ['name' => 'Air Europa',       'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Air Europa'],
            ['name' => 'Mercadona',        'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio Mercadona'],
            ['name' => 'El Corte Inglés',  'reach' => 'national', 'country' => 'ES', 'stadium' => 'Estadio El Corte Inglés'],
            ['name' => 'Banco Sabadell',   'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Banco Sabadell'],
            ['name' => 'Estrella Damm',    'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Estrella Damm'],
            ['name' => 'Mahou',            'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Mahou'],
            ['name' => 'Cruzcampo',        'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Cruzcampo'],
            ['name' => 'Estrella Galicia', 'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Estrella Galicia'],
            ['name' => 'Kutxabank',        'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Kutxabank'],
            ['name' => 'Ibercaja',         'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Ibercaja'],
            ['name' => 'Covirán',          'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Covirán'],
            ['name' => 'Calidad Pascual',  'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Pascual'],
            ['name' => 'Freixenet',        'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Freixenet'],
            ['name' => 'Pikolin',          'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Pikolín'],
            ['name' => 'Font Vella',       'reach' => 'regional', 'country' => 'ES', 'stadium' => 'Estadio Font Vella'],

            // ── England (EN) — national & regional ───────────────────────
            ['name' => 'Legal & General',  'reach' => 'national', 'country' => 'EN', 'stadium' => 'Legal & General Stadium'],
            ['name' => 'BT',               'reach' => 'national', 'country' => 'EN', 'stadium' => 'BT Arena'],
            ['name' => 'Tesco',            'reach' => 'national', 'country' => 'EN', 'stadium' => 'Tesco Stadium'],
            ['name' => 'Greggs',           'reach' => 'national', 'country' => 'EN', 'stadium' => 'Greggs Stadium'],
            ['name' => "Sainsbury's",      'reach' => 'national', 'country' => 'EN', 'stadium' => "Sainsbury's Stadium"],
            ['name' => 'Marks & Spencer',  'reach' => 'national', 'country' => 'EN', 'stadium' => 'M&S Stadium'],
            ['name' => 'Boots',            'reach' => 'national', 'country' => 'EN', 'stadium' => 'Boots Arena'],
            ['name' => 'Lloyds Bank',      'reach' => 'national', 'country' => 'EN', 'stadium' => 'Lloyds Stadium'],
            ['name' => 'Cadbury',          'reach' => 'national', 'country' => 'EN', 'stadium' => 'Cadbury Park'],
            ['name' => 'Yorkshire Tea',    'reach' => 'regional', 'country' => 'EN', 'stadium' => 'Yorkshire Tea Stadium'],
            ['name' => 'Thatchers',        'reach' => 'regional', 'country' => 'EN', 'stadium' => 'Thatchers Park'],
            ['name' => 'Ginsters',         'reach' => 'regional', 'country' => 'EN', 'stadium' => 'Ginsters Stadium'],
            ['name' => 'Warburtons',       'reach' => 'regional', 'country' => 'EN', 'stadium' => 'Warburtons Park'],
            ['name' => 'Hovis',            'reach' => 'regional', 'country' => 'EN', 'stadium' => 'Hovis Stadium'],

            // ── Germany (DE) — national & regional ───────────────────────
            ['name' => 'RWE',              'reach' => 'national', 'country' => 'DE', 'stadium' => 'RWE Arena'],
            ['name' => 'Krombacher',       'reach' => 'national', 'country' => 'DE', 'stadium' => 'Krombacher Stadion'],
            ['name' => 'Aldi',             'reach' => 'national', 'country' => 'DE', 'stadium' => 'Aldi Arena'],
            ['name' => 'Lidl',             'reach' => 'national', 'country' => 'DE', 'stadium' => 'Lidl Arena'],
            ['name' => 'Edeka',            'reach' => 'national', 'country' => 'DE', 'stadium' => 'Edeka Arena'],
            ['name' => 'dm',               'reach' => 'national', 'country' => 'DE', 'stadium' => 'dm Arena'],
            ['name' => 'Haribo',           'reach' => 'national', 'country' => 'DE', 'stadium' => 'Haribo Stadion'],
            ['name' => 'Rossmann',         'reach' => 'regional', 'country' => 'DE', 'stadium' => 'Rossmann Arena'],
            ['name' => 'Bahlsen',          'reach' => 'regional', 'country' => 'DE', 'stadium' => 'Bahlsen Stadion'],
            ['name' => 'Ritter Sport',     'reach' => 'regional', 'country' => 'DE', 'stadium' => 'Ritter Sport Arena'],
            ['name' => 'Müllermilch',      'reach' => 'regional', 'country' => 'DE', 'stadium' => 'Müllermilch Stadion'],
            ['name' => 'Kaufland',         'reach' => 'regional', 'country' => 'DE', 'stadium' => 'Kaufland Arena'],

            // ── France (FR) — national & regional ────────────────────────
            ['name' => 'Crédit Agricole',  'reach' => 'national', 'country' => 'FR', 'stadium' => 'Stade Crédit Agricole'],
            ['name' => 'EDF',              'reach' => 'national', 'country' => 'FR', 'stadium' => 'Stade EDF'],
            ['name' => 'E.Leclerc',        'reach' => 'national', 'country' => 'FR', 'stadium' => 'Stade Leclerc'],
            ['name' => 'Auchan',           'reach' => 'national', 'country' => 'FR', 'stadium' => 'Stade Auchan'],
            ['name' => 'Intermarché',      'reach' => 'national', 'country' => 'FR', 'stadium' => 'Stade Intermarché'],
            ['name' => 'Danone',           'reach' => 'national', 'country' => 'FR', 'stadium' => 'Stade Danone'],
            ['name' => 'Banque Populaire', 'reach' => 'national', 'country' => 'FR', 'stadium' => 'Stade Banque Populaire'],
            ['name' => 'Kronenbourg',      'reach' => 'regional', 'country' => 'FR', 'stadium' => 'Stade Kronenbourg'],
            ['name' => 'Pelforth',         'reach' => 'regional', 'country' => 'FR', 'stadium' => 'Stade Pelforth'],
            ['name' => 'Bonduelle',        'reach' => 'regional', 'country' => 'FR', 'stadium' => 'Stade Bonduelle'],
            ['name' => 'Cristaline',       'reach' => 'regional', 'country' => 'FR', 'stadium' => 'Stade Cristaline'],
            ['name' => 'Bonne Maman',      'reach' => 'regional', 'country' => 'FR', 'stadium' => 'Stade Bonne Maman'],

            // ── Italy (IT) — national & regional ─────────────────────────
            ['name' => 'Peroni',           'reach' => 'national', 'country' => 'IT', 'stadium' => 'Stadio Peroni'],
            ['name' => 'TIM',              'reach' => 'national', 'country' => 'IT', 'stadium' => 'Stadio TIM'],
            ['name' => 'Esselunga',        'reach' => 'national', 'country' => 'IT', 'stadium' => 'Stadio Esselunga'],
            ['name' => 'Conad',            'reach' => 'national', 'country' => 'IT', 'stadium' => 'Stadio Conad'],
            ['name' => 'Coop',             'reach' => 'national', 'country' => 'IT', 'stadium' => 'Stadio Coop'],
            ['name' => 'Poste Italiane',   'reach' => 'national', 'country' => 'IT', 'stadium' => 'Stadio Poste Italiane'],
            ['name' => 'Galbani',          'reach' => 'national', 'country' => 'IT', 'stadium' => 'Stadio Galbani'],
            ['name' => 'Mutti',            'reach' => 'regional', 'country' => 'IT', 'stadium' => 'Stadio Mutti'],
            ['name' => 'Granarolo',        'reach' => 'regional', 'country' => 'IT', 'stadium' => 'Stadio Granarolo'],
            ['name' => 'Mulino Bianco',    'reach' => 'regional', 'country' => 'IT', 'stadium' => 'Stadio Mulino Bianco'],
            ['name' => 'San Benedetto',    'reach' => 'regional', 'country' => 'IT', 'stadium' => 'Stadio San Benedetto'],
            ['name' => 'Levissima',        'reach' => 'regional', 'country' => 'IT', 'stadium' => 'Stadio Levissima'],
        ],
    ],

];
