<?php

return [
    'hub_title' => 'Club',

    'nav' => [
        'finances' => 'Finanzas',
        'stadium' => 'Estadio',
        'reputation' => 'Reputación',
    ],

    'stadium' => [
        'home_ground' => 'Campo',
        'stadium_name' => 'Estadio',
        'capacity' => 'Aforo',
        'capacity_help' => 'El aforo se registra en cada partido para calcular la asistencia. La ampliación del estadio se convertirá en una decisión del entrenador en una fase posterior.',

        'fan_base' => 'Afición',
        'fan_base_help' => 'La lealtad de la afición cambia cada temporada según los resultados. Impulsa la ocupación del estadio junto con la reputación, que marca el precio de las entradas. La marca indica el valor inicial del club.',
        'fan_base_trend' => 'Tendencia',
        'current_loyalty' => 'Lealtad actual',
        'anchor' => 'Ancla',
        'loyalty_rising' => 'Al alza',
        'loyalty_stable' => 'Estable',
        'loyalty_declining' => 'A la baja',

        'last_attendance' => 'Último partido en casa',
        'fill_rate' => 'Ocupación',
        'no_home_match_yet' => 'Aún no se ha jugado ningún partido en casa.',

        'matchday_revenue' => 'Ingresos por taquilla',
        'matchday_revenue_help' => 'La proyección usa la fórmula presupuestaria de la temporada; los ingresos reales se liquidan al cierre. Empezarán a divergir cuando la asistencia determine directamente la taquilla.',
        'no_finances_yet' => 'Las finanzas de la temporada aparecerán cuando se generen las proyecciones.',
    ],

    'reputation' => [
        'current_tier' => 'Nivel actual',
        'points' => 'Puntos de reputación',
        'trend' => 'Tendencia prevista',

        'tiers' => 'Niveles de reputación',
        'ladder_help' => 'Los clubes suben de nivel terminando arriba en la liga; los clubes élite y continentales tienen que compensar la gravedad de su nivel cada temporada o bajarán. Un club nunca cae más de dos niveles por debajo de su ancla.',

        'current' => 'Actual',
        'anchor' => 'Ancla',
        'floor' => 'Suelo',
        'threshold' => 'Umbral',

        'progress' => 'Progreso de nivel',
        'points_to_next' => ':points puntos para :tier',
        'at_top_tier' => 'Cima de la escalera — no hay nivel superior.',

        'season_projection' => 'Proyección de fin de temporada',
        'current_position' => 'Posición actual',
        'position_points' => 'Puntos por posición',
        'gravity' => 'Gravedad de nivel',
        'net_change' => 'Cambio neto',
        'projection_help' => 'Supone que la temporada termina con el club en su posición actual. Los títulos y rachas de copa suman encima al cierre de la temporada.',
        'no_standing_yet' => 'La clasificación aparecerá cuando la temporada esté en marcha.',
    ],
];
