<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Módulo de Coaching — Asesor al descanso
    |--------------------------------------------------------------------------
    |
    | Cadenas usadas por el asesor táctico al descanso. El asesor lee el
    | estado de la primera parte y emite 1-3 consejos que se muestran en el
    | bloque de pausa del descanso con opción de aplicar en un clic vía el
    | endpoint de acciones tácticas ya existente.
    |
    */

    // Niveles de confianza asociados a cada consejo.
    'confidence_high' => 'Alta',
    'confidence_medium' => 'Media',
    'confidence_low' => 'Baja',

    // Cabecera del panel.
    'advisor_title' => 'Lectura del segundo entrenador',
    'advisor_apply' => 'Aplicar',
    'advisor_dismiss' => 'Descartar',
    'advisor_all_dismissed' => 'Todos los consejos descartados.',

    // Consejos según resultado al descanso.
    'tip_chasing_headline' => 'Vamos a remolque — todo hacia delante',
    'tip_chasing_rationale' => 'Dos goles abajo al descanso. Ofensivo, presión alta y línea adelantada.',
    'tip_trailing_one_headline' => 'A uno — toca asumir más riesgos',
    'tip_trailing_one_rationale' => 'Empuje moderado: más gente arriba, pero sin perder la forma.',
    'tip_protecting_lead_headline' => 'Proteger la renta',
    'tip_protecting_lead_rationale' => 'Dos goles arriba. Baja la línea, suelta la presión y gestiona la segunda parte.',
    'tip_one_goal_lead_headline' => 'No te sobreexpongas',
    'tip_one_goal_lead_rationale' => 'Una renta de un gol es frágil. Equilibrado y mantener la forma.',

    // Consejos por planteamiento del rival.
    'tip_release_press_headline' => 'Están presionando — libera presión',
    'tip_release_press_rationale' => 'Baja la línea y juega más directo para superar su presión.',
    'tip_break_low_block_headline' => 'Se cierran atrás — guarda el balón',
    'tip_break_low_block_rationale' => 'Pasa a posesión para sacarlos de su bloque.',
    'tip_counter_high_line_headline' => 'Línea alta — castígalos a la contra',
    'tip_counter_high_line_rationale' => 'Cambia a contraataque para aprovechar el espacio a su espalda.',

    // Consejo de disciplina (solo informativo).
    'tip_card_risk_headline' => ':count amarillas ya — cuidado con las entradas',
    'tip_card_risk_rationale' => 'Plantéate cambiar a los amonestados si lideran las faltas.',

    // Fallback cuando no destaca nada.
    'tip_balanced_headline' => 'Sin alarmas — mantén el plan',
    'tip_balanced_rationale' => 'El planteamiento funciona. Confía y revalúa si cambia la segunda parte.',
];
