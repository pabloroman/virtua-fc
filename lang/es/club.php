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

        'fan_base' => 'Afición',
        'fan_base_help' => 'La lealtad sube con títulos y buenas campañas y baja tras temporadas flojas. Junto a la reputación, determina cuánto se llena el estadio los días de partido.',
        'fan_base_trend' => 'Tendencia',
        'current_loyalty' => 'Apoyo de la afición',

        'last_attendance' => 'Último partido en casa',
        'fill_rate' => 'Ocupación',
        'no_home_match_yet' => 'Aún no se ha jugado ningún partido en casa.',

        'no_finances_yet' => 'Las finanzas de la temporada aparecerán cuando se generen las proyecciones.',

        'stadium_revenue' => [
            'title' => 'Ingresos del estadio',
            'season_tickets' => 'Abonos',
            'matchday' => 'Taquilla',
            'help' => 'Los abonos se cobran por adelantado; la taquilla se acumula en cada partido en casa.',
        ],

        'upgrades' => [
            'title' => 'Ampliación y reforma',
            'base_capacity' => 'Aforo base',
            'supplementary' => 'Gradas supletorias',
            'total' => 'Aforo total',
            'seats' => 'asientos',
            'seats_total' => 'asientos totales',
            'seats_to_add' => 'Asientos a añadir',
            'target_capacity' => 'Aforo objetivo',
            'total_cost' => 'Coste total',
            'financing' => 'Financiación',
            'financing_cash' => 'Pago al contado',
            'financing_loan' => 'Préstamo bancario',
            'financing_cash_hint' => 'Se descuenta del presupuesto disponible al confirmar.',
            'financing_loan_hint' => 'Tope del banco: :cap. Se devuelve en 10 cuotas anuales (capital constante + interés sobre el saldo).',

            'project_supplementary' => 'Gradas supletorias',
            'project_stand_expansion' => 'Ampliación de grada',
            'project_rebuild' => 'Reforma del estadio',
            'ready_on' => 'Listas para :date',
            'ready_in_season' => 'Disponible en la temporada :season',
            'loan_remaining' => 'Pendiente del préstamo: :amount',

            'chip_per_seat' => ':cost / asiento',
            'chip_per_seat_from' => 'desde :cost / asiento',
            'chip_time_days' => ':days días',
            'chip_time_seasons' => ':count temporada|:count temporadas',

            'cta_supplementary_label' => 'Ampliación rápida',
            'cta_supplementary_title' => 'Añadir gradas supletorias',
            'cta_supplementary_tagline' => 'Gradas modulares, hasta :max asientos extra. Se retiran al reformar.',
            'cta_supplementary_full' => 'Has alcanzado el límite de gradas supletorias. Reforma el estadio para liberar más espacio.',
            'cta_supplementary_no_budget' => 'No te alcanza para el lote mínimo (:minimum). Presupuesto disponible: :budget.',
            'budget_caps_slider' => 'El presupuesto disponible (:budget) limita el lote — sin él podrías llegar a :natural asientos.',
            'financing_cash_hint_budget' => 'Se descuenta del presupuesto disponible (:budget) al confirmar.',

            'cta_stand_expansion_label' => 'Proyecto intermedio',
            'cta_stand_expansion_title' => 'Ampliar una grada',
            'cta_stand_expansion_tagline' => 'Reforma una grada para añadir :min–:max asientos permanentes. Sin interrumpir la temporada.',
            'cta_stand_expansion_no_budget' => 'No hay presupuesto ni crédito bancario para el proyecto mínimo (:minimum). Presupuesto disponible: :budget.',

            'cta_rebuild_label' => 'Gran proyecto',
            'cta_rebuild_title' => 'Reformar el estadio',
            'cta_rebuild_tagline' => 'Estadio nuevo, hasta :max asientos. El precio sube con el tamaño; una temporada al 40% de aforo.',
            'cta_rebuild_reputation_lock' => 'Necesitas alcanzar reputación :tier para acometer una reforma integral. Compite y haz buenas temporadas para subir de nivel.',
            'cta_rebuild_locked_by_reputation' => 'Tope del banco: :cap (máx. :max asientos). Para subirlo, alcanza reputación :tier — ganarás un tope de préstamo mayor.',
            'cta_rebuild_locked_by_affordability' => 'Tope del banco: :cap (máx. :max asientos). El banco solo presta lo que tus ingresos pueden devolver. Sube tus ingresos proyectados a unos :revenue al año para desbloquear un estadio más grande.',
            'cta_rebuild_locked_at_elite' => 'Tope del banco: :cap (máx. :max asientos). Tu reputación ya es la máxima; aumenta tus ingresos proyectados para desbloquear un proyecto mayor.',

            'reputation_tiers' => [
                'local' => 'Local',
                'modest' => 'Modesta',
                'established' => 'Consolidada',
                'continental' => 'Continental',
                'elite' => 'Elite',
            ],

            'modal_supplementary_title' => 'Añadir gradas supletorias',
            'modal_supplementary_description' => 'Gradas modulares montadas sobre la estructura existente. Rápidas de instalar (30 días) y al contado, pero con aspecto provisional, sin espacio comercial nuevo y se retiran cuando reformas el estadio. Referencia: las gradas supletorias durante la reforma de un estadio (≈6M€ por ~14.000 asientos).',
            'modal_stand_expansion_title' => 'Ampliar una grada',
            'modal_stand_expansion_description' => 'Demuele una grada y la reconstruye más grande para ganar entre 3.000 y 12.000 asientos permanentes. El resto del estadio sigue operativo durante las obras y la nueva grada se inaugura al inicio de la próxima temporada. Referencia: la ampliación de Anfield Road del Liverpool (+7.000 asientos).',
            'stand_expansion_disruption_note' => 'El resto del estadio sigue operativo durante las obras — no se reduce el aforo. Los nuevos asientos se estrenan al inicio de la próxima temporada.',
            'modal_rebuild_title' => 'Reformar el estadio',
            'modal_rebuild_description' => 'Derriba el estadio actual y construye uno nuevo. Los estadios más grandes cuestan más por asiento (precio por tramos, como el IRPF). Una pretemporada + una temporada de obras (aforo al 40%) antes de inaugurar. Referencia: Metropolitano (Atlético, 70.000, 240M€), reforma del Bernabéu (1.350M€).',
            'rebuild_disruption_warning' => 'Durante la temporada de obras el aforo de los partidos en casa baja al 40% del actual. La taquilla bajará en consecuencia.',
            'rebuild_marginal_rate_prefix' => 'Coste por asiento en este tamaño:',
            'rebuild_marginal_rate_suffix' => '',
            'commit_supplementary' => 'Confirmar gradas',
            'commit_stand_expansion' => 'Iniciar ampliación',
            'commit_rebuild' => 'Iniciar reforma',

            'cta_disabled_by_active_project' => 'Ya tienes un proyecto en curso. Consulta el historial debajo.',
        ],

        'history' => [
            'title' => 'Historial de obras',
            'empty' => 'Aún no hay obras en el estadio.',
            'empty_hint' => 'Las obras pasadas y en curso aparecerán aquí.',
            'col_type' => 'Proyecto',
            'col_detail' => 'Detalle',
            'col_cost' => 'Coste',
            'col_status' => 'Estado',
            'detail_rebuild' => ':count asientos (estadio nuevo)',
            'status_completed' => 'Completado',
            'status_in_progress' => 'En curso',
            'season_label' => 'Temp. :season',
            'ready_label' => 'Listo el :date',
        ],

        'season_tickets' => [
            'title' => 'Precios',
            'subtitle' => 'Fija el precio de los abonos para cada zona del estadio. Los precios se bloquean en cuanto se juegue el primer partido de liga.',
            'deadline_notice' => 'Plazo: los precios se bloquean al jugarse el primer partido de liga de la temporada.',
            'locked_notice' => 'Los abonos están bloqueados esta temporada. Podrás fijar nuevos precios la próxima pretemporada.',
            'tickets_sold' => 'Abonos vendidos',
            'predicted_fill' => 'ocupación prevista',
            'predicted_fill_tooltip' => 'Los precios y el apoyo de tu afición influyen en la venta de abonos.',
            'baseline_price' => 'Base',
            'capacity' => 'Aforo',
            'save_button' => 'Guardar precios',
            'reset_defaults' => 'Restaurar valores por defecto',

            'area' => [
                'general'       => 'General',
                'lateral'       => 'Lateral',
                'lateral_alta'  => 'Lateral alta',
                'lateral_baja'  => 'Lateral baja',
                'tribuna'       => 'Tribuna',
                'tribuna_alta'  => 'Tribuna alta',
                'tribuna_baja'  => 'Tribuna baja',
                'fondo_norte'   => 'Fondo norte',
                'fondo_sur'     => 'Fondo sur',
                'vip'           => 'VIP',
                'palco'         => 'Palco',
            ],
        ],
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

        'trophy_cabinet' => [
            'title' => 'Sala de trofeos',
            'empty' => 'Aún no has conquistado ningún título con este club.',
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
