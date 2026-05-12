<?php

return [
    // Page chrome
    'planner' => 'Planificador',
    'title' => 'Planificador de plantilla',

    // Sections
    'section_staying' => 'Se quedan',
    'section_outgoing' => 'Bajas',
    'section_incoming' => 'Altas',
    'section_staying_count' => ':count jugador|:count jugadores',

    // Position group labels
    'goalkeepers' => 'Porteros',
    'defenders' => 'Defensas',
    'midfielders' => 'Centrocampistas',
    'forwards' => 'Delanteros',

    // Player row chrome
    'age_next' => ':age años la próxima temporada',
    'contract_until' => 'Hasta :year',
    'no_contract' => 'Sin contrato',

    // Reasons — STAYING
    'reason_owned' => 'En plantilla',
    'reason_renewed' => 'Renovación acordada',
    'reason_returning_from_loan' => 'Vuelve de cesión',
    'reason_still_on_loan' => 'Cedido hasta :date',

    // Reasons — OUTGOING
    'reason_retiring' => 'Se retira',
    'reason_transfer_agreed' => 'Traspaso acordado',
    'reason_pre_contract_departing' => 'Precontrato con otro club',
    'reason_contract_expiring_unrenewed' => 'Termina contrato',
    'reason_loan_ending' => 'Termina la cesión',

    // Reasons — INCOMING
    'reason_pre_contract_joining' => 'Precontrato firmado',

    // Empty states
    'empty_staying' => 'No hay jugadores previstos que se queden.',

    // Ability/potential row labels
    'current_ability' => 'Actual',
    'projected_ability' => 'Próxima temporada',
    'potential' => 'Potencial',

    // Squad role badges
    'col_action' => 'Recomendación',
    'role_wonderkid' => 'Perla',
    'role_key_player' => 'Jugador clave',
    'role_first_team' => 'Titular',
    'role_rotation' => 'Rotación',
    'role_prospect' => 'Promesa',
    'role_reserves' => 'Suplente',
    'role_departing' => 'Saliente',

    // Transfer Recommendations
    'transfer_recommendations' => 'Recomendaciones de fichajes',
    'list_conjunction' => 'y',
    'advisory_empty' => 'Sin recomendaciones globales. La plantilla prevista parece equilibrada.',
    'advisory_depth_gap' => 'Refuerza :position — faltan :count para la formación elegida.',
    'advisory_quality_gap' => 'Refuerza :position — :gap puntos por debajo del resto del equipo.',
    'advisory_age_gap' => 'Cantera escasa en :position — sin jugadores de :age o menos previstos.',
    'advisory_wage_cliff' => 'Renueva a :name — contrato hasta :year y aún sin acuerdo.',
    'advisory_development' => 'Da minutos a :names para maximizar su desarrollo.',
    'advisory_wasted_wage' => 'Considera vender a :names — altos salarios y pocos minutos.',
    'advisory_key_departure' => 'Sustituye a :name (:position) — su salida deja un hueco.',

    // Position group labels used inside advisories (singular & lowercase for sentence flow)
    'group_goalkeeper' => 'la portería',
    'group_defender' => 'la defensa',
    'group_midfielder' => 'el centro del campo',
    'group_forward' => 'la delantera',

    // Action chips
    'action_play_often' => 'Dar minutos',
    'action_loan_out' => 'Ceder',
    'action_keep' => 'Mantener',
    'action_renew' => 'Renovar',
    'action_list' => 'Vender',
    'action_replace' => 'Sustituir',

    // Auto-generated blurbs
    'blurb_wonderkid' => 'Gran potencial — ya útil y creciendo rápido.',
    'blurb_key_player' => 'Pilar del equipo. Construye en torno a él.',
    'blurb_first_team' => 'Titular fiable en su posición.',
    'blurb_prospect' => 'Joven prometedor, todavía en desarrollo.',
    'blurb_rotation' => 'Recambio sólido, cerca del once.',
    'blurb_reserves' => 'Al fondo del banquillo en su posición.',
    'blurb_departing' => 'Se va al final de la temporada.',
];
