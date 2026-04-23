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
        'fan_base_help' => 'La lealtad sube con títulos y buenas campañas y baja tras temporadas flojas. Junto a la reputación, determina cuánto se llena el estadio los días de partido.',
        'fan_base_trend' => 'Tendencia',
        'current_loyalty' => 'Apoyo de la afición',

        'last_attendance' => 'Último partido en casa',
        'fill_rate' => 'Ocupación',
        'no_home_match_yet' => 'Aún no se ha jugado ningún partido en casa.',

        'matchday_revenue' => 'Ingresos por taquilla',
        'matchday_revenue_help' => 'La proyección usa la fórmula presupuestaria de la temporada; los ingresos reales se liquidan al cierre. Empezarán a divergir cuando la asistencia determine directamente la taquilla.',
        'no_finances_yet' => 'Las finanzas de la temporada aparecerán cuando se generen las proyecciones.',
    ],

    'reputation' => [
        'current_tier' => 'Nivel actual',

        'tiers' => 'Niveles de reputación',
        'tiers_help_toggle' => '¿Cómo funcionan los niveles de reputación?',
        'ladder_help' => 'Los clubes suben de nivel terminando arriba en la liga. En los niveles más altos, la reputación se desgasta cada temporada si no se respalda con resultados.',

        'current' => 'Actual',

        'qualitative_distance' => [
            'one_strong_season' => 'Una buena temporada bastaría para llegar a :tier.',
            'two_strong_seasons' => 'Un par de buenas temporadas te separan de :tier.',
            'several_seasons' => 'Varias temporadas sólidas te separan de :tier.',
            'long_road' => 'Queda un largo camino hasta :tier.',
        ],

        'tier_descriptors' => [
            'local' => 'Un club modesto con una afición local fiel.',
            'modest' => 'Un club pequeño que aspira a llegar o mantenerse en primera.',
            'established' => 'Un club histórico, con años de experiencia en primera.',
            'continental' => 'Habitual en competiciones europeas.',
            'elite' => 'Referente del fútbol europeo.',
        ],

        'career' => [
            'title' => 'Trayectoria',
            'seasons_managed' => 'Temporadas dirigidas',
            'starting_tier' => 'Nivel inicial',
            'matches_managed' => 'Partidos dirigidos',
            'trophies' => 'Títulos',
        ],

        'path_title' => 'Camino al siguiente nivel',
        'path_also' => 'Los títulos de copa y las rachas europeas también suman al cierre de la temporada.',
        'maintenance_note' => 'En este nivel, la reputación se desgasta cada temporada si no la respaldas con resultados.',
        'projected' => 'Proyectado',

        'legend' => [
            'forward' => 'Avance',
            'flat' => 'Sin avance',
            'setback' => 'Retroceso',
        ],

        'impact' => [
            'major_leap' => 'Gran salto adelante',
            'solid_step' => 'Paso sólido adelante',
            'small_step' => 'Pequeño avance',
            'stalls' => 'Sin avance',
            'setback' => 'Retroceso',
        ],

        'history' => [
            'title' => 'Historial de rendimiento',
            'empty' => 'Tu historial aparecerá al final de la primera temporada.',
            'current_suffix' => '(en curso)',
            'promoted' => 'Ascenso',
            'relegated' => 'Descenso',
            'legend' => [
                'same_tier' => 'Misma categoría',
            ],
        ],

        'impact_title' => 'Qué aporta la reputación a tu club',
        'impact_signings_title' => 'Atraer fichajes',
        'impact_signings_body' => 'Los jugadores de mayor nivel se inclinan por clubes con más reputación. Agentes libres, objetivos de traspaso y clubes rivales valoran tu nivel antes de sentarse a negociar.',
        'impact_retain_title' => 'Retener talento',
        'impact_retain_body' => 'Tu propia plantilla también reacciona a la reputación. Un club en crecimiento retiene mejor a sus piezas clave; cuando se cae de nivel, aparecen los depredadores y las renovaciones se complican.',
        'impact_economy_title' => 'Oportunidades económicas',
        'impact_economy_body' => 'La asistencia al estadio, el precio de las entradas y los ingresos comerciales escalan con la reputación. Subir desbloquea mayores ingresos en todos los frentes; bajar aprieta el presupuesto.',

    ],
];
