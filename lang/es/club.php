<?php

return [
    'hub_title' => 'Club',

    'nav' => [
        'finances' => 'Finanzas',
        'investment' => 'Inversión',
        'stadium' => 'Estadio',
        'commercial' => 'Comercial',
        'reputation' => 'Reputación',
    ],

    'commercial' => [
        'title' => 'Patrocinios comerciales',
        'intro' => 'Busca patrocinadores para generar ingresos recurrentes que refuercen el presupuesto del club.',
        'naming_rights_title' => 'Derechos de nombre del estadio',
        'seek_explainer' => 'Contrata a una agencia para sondear patrocinadores. Cada búsqueda cuesta :fee y debes esperar :days días entre búsquedas.',
        'seek_button' => 'Buscar patrocinadores (:fee)',
        'seek_cooldown' => '{1} Podrás volver a buscar en :days día.|[2,*] Podrás volver a buscar en :days días.',
        'seek_unaffordable' => 'No tienes presupuesto para la comisión de la agencia (:fee).',
    ],

    'stadium' => [
        'home_ground' => 'Campo',
        'stadium_name' => 'Estadio',
        'capacity' => 'Aforo',
        'uefa_category' => 'Nivel UEFA',
        'uefa_category_short' => 'UEFA',
        'uefa_category_tooltip' => 'La UEFA clasifica los estadios en cuatro categorías (1 a 4). Subir de categoría requiere reformar las instalaciones (iluminación, vestuarios, sala de prensa, palcos) y que el aforo supere el mínimo de la siguiente categoría.',

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
            'completion_date' => 'Fecha de finalización',
            'financing' => 'Financiación',
            'financing_cash' => 'Pago al contado',
            'financing_loan' => 'Préstamo bancario',
            'financing_cash_hint' => 'Se descuenta del presupuesto disponible al confirmar.',
            'financing_loan_hint' => 'Tope del banco: :cap. Se devuelve en 10 cuotas anuales (capital constante + interés sobre el saldo).',

            'project_supplementary' => 'Gradas supletorias',
            'project_stand_expansion' => 'Ampliación de grada',
            'project_rebuild' => 'Reforma del estadio',
            'project_uefa_upgrade' => 'Mejora UEFA',
            'ready_on' => 'Listas para :date',
            'ready_in_season' => 'Disponible en la temporada :season',
            'loan_remaining' => 'Pendiente del préstamo: :amount',

            'tier_label' => 'Nivel :n',
            'from_total' => 'Desde :total',
            'per_seat_inline' => ':cost / asiento',
            'time_days_inline' => ':days días',
            'time_months_inline' => ':count mes|:count meses',
            'status_available' => 'Disponible',
            'status_locked' => 'Bloqueado',
            'status_in_progress' => 'En obra',
            'cta_planificar' => 'Planificar →',
            'unlock_with_revenue' => 'Desbloquea con :revenue de ingresos anuales',
            'unlock_with_reputation' => 'Desbloquea en categoría :tier',
            'unlock_progress_label' => 'Ingresos actuales: :current',

            'cta_supplementary_full_short' => 'Aforo supletorio al límite. Reforma el estadio para liberar espacio.',
            'cta_locked_no_budget' => 'Desbloquea con :cost. Presupuesto disponible: :budget.',

            'budget_caps_slider' => 'El presupuesto disponible (:budget) limita el lote — sin él podrías llegar a :natural asientos.',
            'financing_cash_hint_budget' => 'Se descuenta del presupuesto disponible (:budget) al confirmar.',

            'cta_supplementary_label' => 'Ampliación',
            'cta_supplementary_title' => 'Añadir gradas supletorias',

            'cta_stand_expansion_label' => 'Ampliación',
            'cta_stand_expansion_title' => 'Ampliar una grada',

            'cta_rebuild_label' => 'Reconstrucción',
            'cta_rebuild_title' => 'Reconstruir el estadio',

            'reputation_tiers' => [
                'local' => 'Local',
                'modest' => 'Modesta',
                'established' => 'Consolidada',
                'continental' => 'Continental',
                'elite' => 'Elite',
            ],

            'modal_supplementary_title' => 'Añadir gradas supletorias',
            'modal_supplementary_description' => 'Gradas modulares provisionales: rápidas (30 días) y al contado, pero sin espacio comercial nuevo y se retiran al reformar el estadio.',
            'modal_stand_expansion_title' => 'Ampliar una grada',
            'modal_stand_expansion_description' => 'Demuele una grada y la reconstruye más grande. Los asientos son permanentes, a diferencia de las gradas supletorias.',
            'modal_rebuild_title' => 'Reconstruir el estadio',
            'modal_rebuild_description' => 'Derriba el estadio actual y construye uno nuevo. El precio por asiento crece por tramos: cuanto más grande el estadio, más cara cada plaza adicional. El estadio nuevo se entrega con la mejor categoría UEFA que permita su aforo, sin coste adicional.',
            'rebuild_marginal_rate_prefix' => 'Coste por asiento en este tamaño:',
            'rebuild_marginal_rate_suffix' => '',
            'rebuild_cap_explainer_reputation' => 'El máximo lo fija el préstamo bancario al que opta tu club (:cap). Tu reputación actual (:tier) marca ese tope: sube de categoría para acceder a un crédito mayor.',
            'rebuild_cap_explainer_affordability' => 'El máximo lo fija el préstamo bancario al que opta tu club (:cap), calculado sobre tus ingresos anuales previstos. Aumenta tus ingresos para acceder a un crédito mayor.',
            'commit_project' => 'Iniciar obras',

            'cta_disabled_by_active_project' => 'Ya tienes un proyecto en curso. Consulta el historial debajo.',

            'cta_uefa_label' => 'Reforma',
            'cta_uefa_title' => 'Subir a Categoría UEFA :to (desde :from)',
            'cta_uefa_title_generic' => 'Subir de categoría UEFA',
            'cta_uefa_button' => 'Mejorar instalaciones',
            'cta_uefa_tagline' => 'Reforma las instalaciones para subir a Categoría UEFA :target. Coste fijo :cost, unos 9 meses de obras, sin afectar al aforo.',
            'cta_uefa_capacity_floor' => 'Para optar a Categoría UEFA :target el estadio debe superar los :min_cap asientos. Amplía el aforo primero.',
            'cta_uefa_already_max' => 'Tu estadio ya está en la máxima categoría UEFA. No hay más niveles que desbloquear.',
            'cta_uefa_no_base_level' => 'Tu estadio no tiene categoría UEFA asignada. Amplía el aforo para acceder a la clasificación.',

            'modal_uefa_title' => 'Subir a Categoría UEFA :to',
            'modal_uefa_description' => 'Reforma de las instalaciones para alcanzar los requisitos de la siguiente categoría UEFA (iluminación, vestuarios, zonas de prensa, palcos y accesibilidad). El aforo no se ve afectado durante las obras: la nueva categoría queda inscrita al inicio de la próxima temporada.',
            'uefa_transition_label' => 'Categoría',
        ],

        'history' => [
            'title' => 'Historial de obras',
            'empty' => 'Aún no hay obras en el estadio.',
            'empty_hint' => 'Las obras pasadas y en curso aparecerán aquí.',
            'col_type' => 'Proyecto',
            'col_detail' => 'Detalles',
            'col_cost' => 'Coste',
            'col_status' => 'Estado',
            'detail_seats' => ':count asientos',
            'detail_rebuild' => ':count asientos (estadio nuevo)',
            'detail_uefa_upgrade' => 'Categoría UEFA :from → :to',
            'status_completed' => 'Completado',
            'status_in_progress' => 'En curso',
            'season_label' => 'Temp. :season',
            'ready_label' => 'Listo el :date',
        ],

        'season_tickets' => [
            'title' => 'Precios',
            'subtitle' => 'Elige una política de precios para tus abonos. Precios más bajos llenan más el estadio; precios más altos rinden más por asiento. Se bloquea al jugarse el primer partido de liga.',
            'deadline_notice' => 'Plazo: los precios se bloquean al jugarse el primer partido de liga de la temporada.',
            'locked_notice' => 'Los abonos están bloqueados esta temporada. Podrás fijar nuevos precios la próxima pretemporada.',
            'tickets_sold' => 'Abonos vendidos',
            'projected_season_tickets' => 'Abonos previstos',
            'projected_season_tickets_tooltip' => 'Abonos que prevés vender (de pago por adelantado). La asistencia a cada partido es distinta: suma las entradas de taquilla y descuenta los abonados que no acuden.',
            'of_capacity' => 'del aforo',
            'matchday_occupancy' => 'ocupación en partido',
            'save_button' => 'Guardar',
            'preset' => [
                'accessible' => 'Accesible',
                'standard' => 'Estándar',
                'premium' => 'Premium',
            ],
            'preset_hint' => [
                'accessible' => 'Más baratos, estadio más lleno.',
                'standard' => 'Precios de referencia.',
                'premium' => 'Más caros, menos ocupación.',
            ],
        ],

        'identity' => [
            'subtitle' => 'Renombra tu estadio sin coste (una vez por temporada, en pretemporada). Vender el nombre a un patrocinador se gestiona en la página Comercial.',
            'sponsor_owns_name' => 'Un patrocinador (:sponsor) posee el nombre del estadio hasta que expire el acuerdo, así que no puedes renombrarlo.',
            'manage_in_commercial' => 'Gestionar en Comercial',
            'sell_naming_rights' => 'Vender los derechos de nombre',
        ],

        'naming_rights' => [
            'title' => 'Identidad del estadio y derechos de nombre',
            'current_name' => 'Nombre actual',
            'source_historic' => 'Histórico',
            'source_custom' => 'Renombrado',
            'source_sponsor' => 'Patrocinado',

            'seasons_remaining' => '{1} queda :count temporada|[2,*] quedan :count temporadas',

            'offers_title' => 'Ofertas de patrocinio',
            'becomes' => 'El estadio pasa a llamarse «:name»',
            'annual_value' => 'Valor anual',
            'contract_length' => 'Contrato',
            'seasons' => '{1} :count temporada|[2,*] :count temporadas',
            'accept_button' => 'Aceptar acuerdo',
            'accept_confirm' => '¿Vender los derechos de nombre a :sponsor? Esto bloquea el nombre del estadio durante el contrato y resta apoyo de la afición.',
            'renewal_badge' => 'Renovación',
            'renew_button' => 'Renovar acuerdo',
            'renew_confirm' => '¿Renovar el acuerdo con :sponsor? Mantiene el nombre del estadio sin coste de apoyo de la afición.',

            'rename_button' => 'Renombrar estadio',
            'rename_placeholder' => 'Nuevo nombre del estadio',
            'rename_save' => 'Guardar nombre',
            'rename_locked_season' => 'El estadio ya se ha renombrado esta temporada.',

            'window_closed_notice' => 'La identidad del estadio se fija en pretemporada. Los acuerdos y renombres se reabren antes del primer partido de liga de la próxima temporada.',
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
