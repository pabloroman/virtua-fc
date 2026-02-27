<?php

return [
    // Inbox
    'inbox' => 'Notificaciones',
    'mark_all_read' => 'Marcar todo como leído',
    'all_caught_up' => 'Estás al día',

    // Injury types
    'injury_muscle_fatigue' => 'fatiga muscular',
    'injury_muscle_strain' => 'distensión muscular',
    'injury_calf_strain' => 'distensión de gemelo',
    'injury_ankle_sprain' => 'esguince de tobillo',
    'injury_groin_strain' => 'distensión inguinal',
    'injury_hamstring_tear' => 'rotura de isquiotibial',
    'injury_knee_contusion' => 'contusión de rodilla',
    'injury_metatarsal_fracture' => 'fractura de metatarso',
    'injury_acl_tear' => 'rotura de ligamento cruzado',
    'injury_achilles_rupture' => 'rotura del tendón de Aquiles',

    // Player injuries
    'player_injured_title' => ':player lesionado',
    'player_injured_message' => ':player tiene :injury y estará fuera :weeks semanas.',

    // Player suspensions
    'player_suspended_title' => ':player sancionado',
    'player_suspended_message' => ':player ha sido sancionado con :matches partidos por :reason.',
    'reason_red_card' => 'tarjeta roja',
    'reason_yellow_accumulation' => 'acumulación de amarillas',

    // Player recovery
    'player_recovered_title' => ':player recuperado',
    'player_recovered_message' => ':player se ha recuperado y está disponible para jugar.',

    // Transfer offers
    'transfer_offer_title' => 'Oferta :team_de',
    'transfer_offer_message' => ':team ha ofrecido :fee por :player.',
    'free_transfer' => 'Traspaso Libre',

    // Transfer complete
    'transfer_complete_incoming_title' => ':player fichado',
    'transfer_complete_incoming_message' => ':player se ha unido a tu plantilla procedente :team_de por :fee.',
    'transfer_complete_outgoing_title' => ':player vendido',
    'transfer_complete_outgoing_message' => ':player ha sido traspasado :team_a por :fee.',

    // Expiring offers
    'offer_expiring_title' => 'Oferta por :player expira pronto',
    'offer_expiring_message' => 'La oferta :team_de por :player expira en :days días.',

    // Scout
    'scout_complete_title' => 'Informe de Ojeador Listo',
    'scout_complete_message' => 'Tu ojeador ha encontrado :count jugadores que coinciden con tu búsqueda.',

    // Contracts
    'contract_expiring_title' => 'Contrato de :player expira pronto',
    'contract_expiring_message' => 'El contrato de :player expira en :months meses.',

    // Loan returns
    'loan_return_title' => ':player regresa de cesión',
    'loan_return_message' => ':player ha regresado de su cesión :team_en.',

    // Low fitness
    'low_fitness_title' => ':player con baja forma física',
    'low_fitness_message' => ':player tiene solo :fitness% de forma física y necesita descanso.',

    // Loan search
    'loan_destination_found_title' => 'Destino encontrado para :player',
    'loan_destination_found_message' => ':player ha sido cedido :team_a.',
    'loan_destination_found_waiting' => ':player será cedido :team_a cuando abra la ventana de fichajes.',
    'loan_search_failed_title' => 'Búsqueda de cesión fallida',
    'loan_search_failed_message' => 'No se encontró un club interesado en ceder a :player. El jugador vuelve a estar disponible.',

    // Competition advancement
    'competition_advancement_title' => 'Clasificación en :competition',
    'competition_advancement_message' => ':stage',
    'competition_elimination_title' => 'Eliminación de :competition',
    'competition_elimination_message' => ':stage',

    // Academy
    'academy_batch_title' => 'Nuevos canteranos',
    'academy_batch_message' => ':count nuevos jugadores han llegado a la cantera.',
    'academy_evaluation_title' => 'Evaluación de cantera',
    'academy_evaluation_message' => 'Es momento de evaluar a tus canteranos.',

    // Transfer bid results
    'bid_accepted_title' => 'Oferta por :player aceptada',
    'bid_accepted' => ':team ha aceptado tu oferta por :player — fichaje acordado por :fee.',
    'bid_counter_offer_title' => 'Contraoferta por :player',
    'bid_counter_offer' => ':team pide :asking por :player (ofreciste :offered).',
    'bid_rejected_title' => 'Oferta por :player rechazada',
    'bid_rejected' => ':team ha rechazado tu oferta por :player.',

    // Loan request results
    'loan_accepted_title' => 'Cesión de :player aceptada',
    'loan_accepted' => ':team ha aceptado tu solicitud de cesión por :player.',
    'loan_rejected_title' => 'Cesión de :player rechazada',
    'loan_rejected' => ':team ha rechazado tu solicitud de cesión por :player.',

    // Tournament welcome
    'tournament_welcome_title' => '¡Bienvenido al Mundial!',
    'tournament_welcome_message' => 'Todo el país tiene los ojos puestos en tí. Sin presión... ¡pero no les decepciones!',

    // Priority badges
    'priority_urgent' => 'Urgente',
    'priority_attention' => 'Atención',

    // AI transfer market
    'ai_transfer_title' => 'Resumen Ventana de :window',
    'ai_transfer_message' => ':count traspasos completados en la liga.',
    'ai_transfer_window_summer' => 'Verano',
    'ai_transfer_window_winter' => 'Invierno',

    // Renewal negotiations
    'renewal_accepted_title' => ':player acepta renovar',
    'renewal_accepted_message' => ':player ha aceptado la renovación por :wage/año durante :years años.',
    'renewal_countered_title' => ':player contraoferta',
    'renewal_countered_message' => ':player pide :wage/año durante :years años para renovar.',
    'renewal_rejected_title' => ':player rechaza renovar',
    'renewal_rejected_message' => ':player ha rechazado tu oferta de renovación. Se marchará al final de la temporada.',
];
