<?php

return [
    // Page title
    'squad' => 'Plantilla',
    'first_team' => 'Primer Equipo',
    'development' => 'Desarrollo',
    'stats' => 'Estadísticas',

    // Position groups
    'goalkeepers' => 'Porteros',
    'defenders' => 'Defensas',
    'midfielders' => 'Centrocampistas',
    'forwards' => 'Delanteros',
    'goalkeepers_short' => 'PO',
    'defenders_short' => 'DEF',
    'midfielders_short' => 'MC',
    'forwards_short' => 'DEL',

    // Columns
    'technical' => 'TEC',
    'physical' => 'FIS',
    'technical_abbr' => 'TEC',
    'physical_abbr' => 'FIS',
    'years_abbr' => 'años',
    'fitness' => 'FOR',
    'morale' => 'MOR',
    'overall' => 'MED',

    // Status labels
    'on_loan' => 'Cedido',
    'leaving_free' => 'Se va (Libre)',
    'renewed' => 'Renovado',
    'sale_agreed' => 'Venta Acordada',
    'retiring' => 'Se retira',
    'listed' => 'En Venta',
    'list_for_sale' => 'Poner a la Venta',
    'unlist_from_sale' => 'Retirar de la Venta',
    'loan_out' => 'Ceder',
    'loan_searching' => 'Buscando destino de cesión',

    // Summary
    'wage_bill' => 'Masa Salarial',
    'per_year' => '/año',
    'avg_fitness' => 'Media Forma',
    'avg_morale' => 'Media Moral',
    'low' => 'bajo',

    // Contract management
    'free_transfer' => 'Libre',
    'let_go' => 'Dejar Ir',
    'pre_contract_signed' => 'Precontrato firmado',
    'new_wage_from_next' => 'Nuevo salario desde próxima temporada',
    'has_pre_contract_offers' => '¡Tiene ofertas de precontrato!',
    'renew' => 'Renovar',
    'expires_in_days' => 'Expira en :days días',

    // Lineup validation
    'formation_position_mismatch' => 'La formación :formation requiere :required :position, pero seleccionaste :actual.',
    'player_not_available' => 'Uno o más jugadores seleccionados no están disponibles.',

    // Lineup
    'formation' => 'Formación',
    'mentality' => 'Mentalidad',
    'auto_select' => 'Selección Auto',
    'opponent' => 'Rival',
    'need' => 'necesitas',

    // Compatibility
    'natural' => 'Natural',
    'very_good' => 'Muy Bueno',
    'good' => 'Bueno',
    'okay' => 'Aceptable',
    'poor' => 'Malo',
    'unsuitable' => 'Inadecuado',

    // Lineup editor
    'pitch' => 'Campo',
    'select_player_for_slot' => 'Selecciona un jugador para esta posición',

    // Opponent scout
    'injured' => 'lesionados',
    'suspended' => 'sancionados',

    // Coach assistant
    'coach_assistant' => 'Asistente Técnico',
    'coach_recommendations' => 'Recomendaciones',
    'coach_no_tips' => 'Sin recomendaciones especiales para este partido.',
    'coach_defensive_recommended' => 'Rival más fuerte. Mentalidad defensiva reduce sus goles esperados un 30%.',
    'coach_attacking_recommended' => 'Tienes ventaja. Una mentalidad ofensiva puede maximizar tus goles.',
    'coach_risky_formation' => 'Tu formación ofensiva contra un rival superior les dará más ocasiones. Considera una más defensiva.',
    'coach_home_advantage' => 'Jugáis en casa (+0.15 goles esperados).',
    'coach_critical_fitness' => ':names en forma crítica (<50). Riesgo de lesión 2x mayor. Considera rotarlos.',
    'coach_low_fitness' => ':count jugador(es) con forma baja (<70). Rinden peor y tienen mayor riesgo de lesión.',
    'coach_low_morale' => ':count jugador(es) con moral baja. Tendrán peor rendimiento en el partido.',
    'coach_bench_frustration' => ':count jugador(es) de calidad sin jugar y perdiendo moral. Rota para mantenerlos contentos.',
    'coach_opponent_expected_label' => 'Previsto',
    'coach_opponent_defensive_setup' => 'Rival previsto con :formation (:mentality). Considera un enfoque ofensivo para desbloquearlos.',
    'coach_opponent_attacking_setup' => 'Rival previsto con :formation (:mentality). Dejarán espacios — una defensa sólida puede aprovecharlos.',
    'coach_opponent_deep_block' => 'Rival con 5 defensas. Amplitud y paciencia serán clave.',
    'mentality_defensive' => 'Defensiva',
    'mentality_balanced' => 'Equilibrada',
    'mentality_attacking' => 'Ofensiva',

    // Unavailability reasons
    'suspended_matches' => 'Sancionado (:count partido)|Sancionado (:count partidos)',
    'injured_generic' => 'Lesionado',
    'injury_weeks' => ':count semana|:count semanas',

    // Injury types
    'injury_muscle_fatigue' => 'Fatiga muscular',
    'injury_muscle_strain' => 'Distensión muscular',
    'injury_calf_strain' => 'Distensión de gemelo',
    'injury_ankle_sprain' => 'Esguince de tobillo',
    'injury_groin_strain' => 'Distensión de aductor',
    'injury_hamstring_tear' => 'Rotura de isquiotibial',
    'injury_knee_contusion' => 'Contusión de rodilla',
    'injury_metatarsal_fracture' => 'Fractura de metatarso',
    'injury_acl_tear' => 'Rotura de ligamento cruzado',
    'injury_achilles_rupture' => 'Rotura del tendón de Aquiles',

    // Development page
    'ability' => 'Habilidad',
    'playing_time' => 'Minutos',
    'high_potential' => 'Alto Potencial',
    'growing' => 'Creciendo',
    'declining' => 'Declinando',
    'peak' => 'En su Pico',
    'all' => 'Todos',
    'no_players_match_filter' => 'Ningún jugador coincide con el filtro seleccionado.',
    'pot' => 'POT',
    'apps' => 'PJ',
    'projection' => 'Proyección',
    'potential' => 'Potencial',
    'potential_range' => 'Rango de Potencial',
    'starter_bonus' => 'bonus de titular',
    'needs_appearances' => 'Necesita :count+ partidos para bonus de titular',
    'qualifies_starter_bonus' => 'Cualifica para bonus de titular (+50% desarrollo)',

    // Stats page
    'goals' => 'G',
    'assists' => 'A',
    'goal_contributions' => 'G+A',
    'goals_per_game' => 'G/P',
    'own_goals' => 'PP',
    'yellow_cards' => 'TA',
    'red_cards' => 'TR',
    'clean_sheets' => 'PC',
    'appearances' => 'Partidos',
    'bookings' => 'Amonestaciones',
    'click_to_sort' => 'Haz clic en las cabeceras de columna para ordenar',

    // Stats highlights
    'top_in_squad' => 'Máximo en plantilla',

    // Legend labels
    'legend_apps' => 'Partidos',
    'legend_goals' => 'Goles',
    'legend_assists' => 'Asistencias',
    'legend_contributions' => 'Contribuciones de Gol',
    'legend_own_goals' => 'Goles en Propia',
    'legend_clean_sheets' => 'Porterías a Cero (solo PO)',

    // Player detail modal
    'abilities' => 'Habilidades',
    'technical_full' => 'Técnica',
    'physical_full' => 'Físico',
    'fitness_full' => 'Forma',
    'morale_full' => 'Moral',
    'season_stats' => 'Estadísticas de Temporada',
    'clean_sheets_full' => 'Porterías a Cero',
    'goals_conceded_full' => 'Goles Encajados',
    'discovered' => 'Descubierto',

    // Academy
    'academy' => 'Cantera',
    'promote_to_first_team' => 'Subir al Primer Equipo',
    'academy_tier' => 'Nivel de Cantera',
    'no_academy_prospects' => 'No hay canteranos disponibles.',
    'academy_explanation' => 'Los nuevos canteranos llegan al inicio de cada temporada según tu inversión en cantera.',
    'academy_evaluation' => 'Evaluación de Cantera',
    'academy_capacity' => 'Plazas',
    'academy_keep' => 'Mantener',
    'academy_keep_desc' => 'El jugador sigue en la cantera y se desarrolla la próxima temporada.',
    'academy_dismiss' => 'Despedir',
    'academy_dismiss_confirm' => '¿Estás seguro? El jugador será despedido de forma permanente.',
    'academy_dismiss_desc' => 'El jugador es despedido del club de forma permanente.',
    'academy_loan_out' => 'Ceder',
    'academy_loan_desc' => 'El jugador sale cedido con desarrollo acelerado (1.5x) y regresa a final de temporada.',
    'academy_promote' => 'Subir',
    'academy_promote_desc' => 'El jugador se incorpora al primer equipo con contrato profesional.',
    'academy_must_decide' => 'Decisión obligatoria',
    'academy_over_capacity' => 'La cantera está llena. Debes liberar plazas.',
    'academy_returning_loans' => ':count jugador regresa de cesión|:count jugadores regresan de cesión',
    'academy_incoming' => ':min-:max nuevos canteranos esperados',
    'academy_on_loan' => 'Cedido',
    'academy_seasons' => ':count temporada|:count temporadas',
    'academy_phase_unknown' => 'Habilidades desconocidas',
    'academy_phase_glimpse' => 'Habilidades visibles',
    'academy_phase_verdict' => 'Potencial revelado',

    // Academy help text
    'academy_help_toggle' => '¿Cómo funciona la cantera?',
    'academy_help_development' => 'Los canteranos mejoran progresivamente a lo largo de la temporada. Sus habilidades son inicialmente desconocidas y se revelan en dos momentos clave.',
    'academy_help_phases_title' => 'Revelación de habilidades',
    'academy_help_phase_0' => 'Inicio de temporada: solo identidad visible (nombre, posición, edad). Decide por intuición.',
    'academy_help_phase_1' => 'Primera vuelta: se revelan la técnica y la física.',
    'academy_help_phase_2' => 'Mercado de invierno: se revela el rango de potencial. ¡El momento de la verdad!',
    'academy_help_evaluations_title' => 'Evaluación obligatoria',
    'academy_help_evaluation_desc' => 'Al final de temporada debes decidir qué hacer con cada canterano:',
    'academy_help_keep' => 'Mantener — sigue en la cantera y continúa mejorando',
    'academy_help_promote' => 'Subir — se incorpora al primer equipo con contrato profesional',
    'academy_help_loan' => 'Ceder — mejora 1.5× más rápido fuera y regresa al final de temporada',
    'academy_help_dismiss' => 'Despedir — abandona el club de forma permanente',
    'academy_help_age_rule' => 'Los jugadores de 21 o más años no pueden seguir en la cantera: debes subirlos o despedirlos.',
    'academy_help_capacity_rule' => 'Si superas la capacidad, debes liberar plazas antes de poder continuar la temporada.',

    'academy_tier_0' => 'Sin Cantera',
    'academy_tier_1' => 'Cantera Básica',
    'academy_tier_2' => 'Buena Cantera',
    'academy_tier_3' => 'Cantera de Élite',
    'academy_tier_4' => 'Cantera de Clase Mundial',
    'academy_tier_unknown' => 'Desconocido',

    // Lineup help text
    'lineup_help_toggle' => '¿Cómo funciona la alineación?',
    'lineup_help_intro' => 'Elige 11 jugadores para cada partido. Tu formación, la forma física y la compatibilidad posicional afectan al rendimiento.',
    'lineup_help_formation_title' => 'Formación y Mentalidad',
    'lineup_help_formation_desc' => 'La formación determina qué posiciones hay disponibles en el campo. Los jugadores rinden mejor en su posición natural.',
    'lineup_help_compatibility_natural' => 'Natural — el jugador está en su mejor posición, rendimiento completo.',
    'lineup_help_compatibility_good' => 'Bueno / Muy Bueno — penalización leve, pero el jugador puede rendir bien.',
    'lineup_help_compatibility_poor' => 'Malo / Inadecuado — penalización significativa, evítalo si es posible.',
    'lineup_help_mentality_desc' => 'La mentalidad afecta a lo ofensivo o defensivo que juega tu equipo.',
    'lineup_help_condition_title' => 'Forma Física y Moral',
    'lineup_help_condition_desc' => 'Los jugadores con baja forma física o moral rinden peor. Rota la plantilla para mantener a todos frescos.',
    'lineup_help_fitness' => 'La forma física baja después de cada partido y se recupera entre jornadas. Las lesiones aumentan cuando la forma es baja.',
    'lineup_help_morale' => 'La moral se ve afectada por los resultados, los minutos jugados y la situación contractual.',
    'lineup_help_auto' => 'Usa "Selección Auto" para que el sistema elija el mejor XI disponible para tu formación.',

    // Squad selection (tournament onboarding)
    'squad_selection_title' => 'Selecciona tu convocatoria',
    'squad_selection_subtitle' => 'Elige 26 jugadores para el torneo',
    'confirm_squad' => 'Confirmar convocatoria',
    'squad_confirmed' => '¡Convocatoria confirmada!',
    'invalid_selection' => 'Selección inválida. Verifica los jugadores seleccionados.',
];
