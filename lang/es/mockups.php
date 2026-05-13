<?php

return [
    'renovation' => [
        'page_title' => 'Estadio e Instalaciones',
        'breadcrumb' => 'Club · Mockup',
        'mockup_badge' => 'Mockup',
        'intro' => 'Planifica las obras del estadio y de las instalaciones del club. Inicia una renovación por temporada y elige a qué área asignar tu presupuesto.',

        'summary' => [
            'budget' => 'Presupuesto de obras',
            'invested' => 'Inversión acumulada',
            'projects' => 'Áreas del club',
            'in_progress' => 'Obras en curso',
        ],

        'active' => [
            'eyebrow' => 'Obra en curso',
            'progress' => 'Progreso',
            'eta' => 'Faltan :weeks semanas para la inauguración',
            'cancel' => 'Cancelar obra',
        ],

        'tier_label' => 'N:num',
        'tier_to' => 'Nivel :from → :to',
        'current_effect' => 'Efecto actual',
        'next_tier' => 'Siguiente nivel',
        'upgrade_to' => 'Pasar al nivel :num',
        'what_changes' => '¿Qué cambia?',
        'from' => 'Antes',
        'to' => 'Después',
        'cost' => 'Coste',
        'duration' => 'Duración',
        'weeks' => ':num semanas',
        'weeks_left' => 'faltan :num sem.',
        'budget_after' => 'Presupuesto tras obra',
        'start_works' => 'Iniciar obras',
        'confirm_hint' => 'Los fondos se descuentan al iniciar la obra.',
        'insufficient_budget' => 'Presupuesto insuficiente.',
        'max_help' => 'Has alcanzado el máximo en esta área.',
        'footnote' => 'Solo puede haber una obra activa a la vez. Las mejoras se completan dentro de la temporada.',

        'badge' => [
            'max' => 'Máx.',
            'building' => 'En obras',
        ],

        'delta' => [
            'seats' => 'asientos',
            'matchday' => 'ingresos por partido',
            'youth' => 'Jóvenes con mejor potencial',
            'medical' => '-30% → -50% tiempo de recuperación',
        ],

        'buildings' => [
            'stadium' => [
                'name' => 'Estadio',
                'tagline' => 'Aforo · ingresos por taquilla',
                'tier' => [
                    1 => 'Aforo básico, gradas descubiertas',
                    2 => '38.000 asientos · cubierta parcial',
                    3 => '46.500 asientos · cubierta total + zonas VIP',
                    4 => 'Estadio moderno · palcos premium y comercio interior',
                ],
            ],
            'facilities' => [
                'name' => 'Instalaciones del estadio',
                'tagline' => 'Multiplicador de ingresos de día de partido',
                'tier' => [
                    1 => 'Mejoras básicas · ×1.00 ingresos',
                    2 => 'Instalaciones modernas · ×1.15 ingresos',
                    3 => 'Experiencia premium · ×1.35 ingresos',
                    4 => 'Estadio de clase mundial · ×1.60 ingresos',
                ],
            ],
            'youth_academy' => [
                'name' => 'Cantera',
                'tagline' => 'Potencial de los jugadores formados',
                'tier' => [
                    1 => 'Academia básica · promesas ocasionales',
                    2 => 'Buena academia · cantera regular',
                    3 => 'Academia de élite · jóvenes de alto potencial',
                    4 => 'Clase mundial · estrellas de la casa',
                ],
            ],
            'medical' => [
                'name' => 'Centro médico',
                'tagline' => 'Velocidad de recuperación y prevención',
                'tier' => [
                    1 => 'Atención básica · recuperación estándar',
                    2 => 'Buenas instalaciones · 15% más rápido',
                    3 => 'Personal de élite · 30% más rápido, menos lesiones',
                    4 => 'Clase mundial · 50% más rápido, prevención activa',
                ],
            ],
            'scouting' => [
                'name' => 'Red de ojeadores',
                'tagline' => 'Alcance y precisión del scouting',
                'tier' => [
                    1 => 'Red básica · solo mercado nacional',
                    2 => 'Red ampliada · más resultados y precisión',
                    3 => 'Alcance internacional · búsquedas rápidas',
                    4 => 'Red global · máxima velocidad y precisión',
                ],
            ],
        ],
    ],
];
