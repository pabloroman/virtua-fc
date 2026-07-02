<?php

return [
    // Inbox
    'inbox' => 'Notificaciones',
    'new' => 'nuevas',
    'mark_all_read' => 'Marcar todo como leído',
    'all_caught_up' => 'Estás al día',

    // Department inbox tabs
    'dept_all' => 'Todo',
    'dept_sporting' => 'Cuerpo técnico',
    'dept_transfers' => 'Dirección deportiva',
    'dept_scouting' => 'Ojeadores',
    'dept_academy' => 'Cantera',
    'dept_board' => 'Junta directiva',
    'dept_competition' => 'Competición',

    // Critical-alert popup (blocking, must-dismiss)
    'alert_heading' => 'Aviso importante',
    'alerts_heading' => ':count avisos importantes',
    'celebration_heading' => '¡Enhorabuena!',
    'alert_dismiss' => 'Descartar',
    'dismiss_all' => 'Descartar todo',
    'alert_continue' => 'Continuar',
    'action_review_offer' => 'Revisar oferta',
    'action_view_competition' => 'Ver competición',
    'action_view_details' => 'Ver detalles',

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
    'player_injured_message' => ':player ha sufrido :injury :location.',
    'player_injured_message_with_date' => ':player ha sufrido :injury :location. Baja hasta el :date.',
    'injury_location_match' => 'durante el partido',
    'injury_location_training' => 'durante el entrenamiento',

    // Player suspensions
    'player_suspended_title' => ':player sancionado',
    'player_suspended_message' => ':player ha sido sancionado con :matches partido por :reason. Se perderá el próximo partido de :competition.|:player ha sido sancionado con :matches partidos por :reason. Se perderá el próximo partido de :competition.',
    'reason_red_card' => 'tarjeta roja',
    'reason_yellow_accumulation' => 'acumulación de amarillas',

    // Player recovery
    'player_recovered_title' => ':player recuperado',
    'player_recovered_message' => ':player se ha recuperado y está disponible para jugar.',

    // Transfer offers
    'transfer_offer_title' => 'Oferta de compra por :player',
    'transfer_offer_message' => ':team_el ha ofrecido :fee por el jugador.',
    'free_transfer' => 'Traspaso Libre',

    // Transfer complete
    'transfer_complete_incoming_title' => ':player fichado',
    'transfer_complete_incoming_message' => ':player se ha unido a tu plantilla procedente :team_de por :fee.',
    'transfer_complete_outgoing_title' => ':player vendido',
    'transfer_complete_outgoing_message' => ':player ha sido traspasado :team_a por :fee.',
    'transfer_failed_title' => 'Fichaje frustrado: :player',
    'transfer_failed_message' => 'El traspaso acordado de :player no pudo completarse y se ha liberado el presupuesto reservado.',
    'loan_out_complete_title' => ':player cedido',
    'loan_out_complete_message' => ':player ha sido cedido :team_a hasta final de temporada.',

    // Release clause triggered against the user (Phase 3)
    'player_left_via_release_clause_title' => 'Cláusula de rescisión: :player se marcha',
    'player_left_via_release_clause_message' => ':player se marcha :team_a tras activarse su cláusula de rescisión. Tu club recibe :fee.',

    // Expiring offers
    'offer_expiring_title' => 'Oferta por :player expira pronto',
    'offer_expiring_message' => '{0}La oferta :team_de por :player expira hoy.|{1}La oferta :team_de por :player expira en :count día.|[2,*]La oferta :team_de por :player expira en :count días.',

    // Scout
    'scout_complete_title' => 'Informe de Ojeador Listo',
    'scout_complete_message' => 'Tu ojeador ha encontrado :count jugadores que coinciden con tu búsqueda.',

    // Contracts
    'contract_expiring_title' => 'Contrato de :player expira pronto',
    'contract_expiring_message' => 'El contrato de :player expira en :months meses.',

    // Loan returns
    'loan_return_title' => ':player regresa de cesión',
    'loan_return_message' => ':player ha regresado de su cesión :team_en.',

    // Low energy
    'low_fitness_title' => ':player con baja energía',
    'low_fitness_message' => ':player tiene solo :fitness% de energía y necesita descanso.',

    // Loan search
    'loan_offer_received_title' => 'Oferta de cesión por :player',
    'loan_offer_received_message' => ':team_el ha ofrecido llevarse cedido al jugador.',
    'loan_search_failed_title' => 'Búsqueda de cesión fallida',
    'loan_search_failed_message' => 'No se encontró un club interesado en ceder a :player. El jugador vuelve a estar disponible.',

    // Competition advancement
    'competition_advancement_title' => 'Clasificación en :competition',
    'competition_advancement_message' => ':stage',
    'competition_elimination_title' => 'Eliminación de :competition',
    'competition_elimination_message' => ':stage',
    'trophy_won_title' => '¡Campeón de la :competition!',

    // Academy
    'academy_batch_title' => 'Nuevos canteranos',
    'academy_batch_message' => ':count nuevos jugadores han llegado a la cantera.',
    'academy_overage_promoted_title' => 'Graduados de la cantera',
    'academy_overage_promoted_message' => ':count canteranos de 21+ años han sido promocionados al primer equipo.',
    'academy_gap_promoted_title' => 'Canteranos promocionados',
    'academy_gap_promoted_message' => ':count canteranos han sido promocionados para cubrir huecos en la plantilla.',
    'reserve_overage_promoted_title' => 'Graduado del filial',
    'reserve_overage_promoted_message' => ':player ha superado la edad del filial y se incorpora al primer equipo de forma permanente.',
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

    // Ofertas de empleo del manager (modo Pro Manager)
    'job_offer_received_title' => ':count clubes interesados en ti',
    'job_offer_post_firing_title' => 'Elige tu próximo club (:count ofertas)',
    'job_offer_received_message' => 'Revisa la pantalla de fin de temporada para aceptar o rechazar.',

    // Transfer window open
    'transfer_window_open_title' => 'Ventana de :window Abierta',
    'transfer_window_open_message' => 'La ventana de fichajes está abierta. Los fichajes acordados se incorporarán a tu plantilla de inmediato.',

    // Transfer window closing
    'transfer_window_closing_title' => 'Cierre de Ventana de :window',
    'transfer_window_closing_message' => 'Esta es tu última oportunidad para fichar. La ventana de fichajes cierra tras esta jornada.',

    // Transfer window closed (also the AI market summary — the window-close notice
    // and the league transfer count are a single notification)
    'ai_transfer_title' => 'Ventana de :window Cerrada',
    'ai_transfer_message' => 'La ventana de fichajes está cerrada. :count traspasos completados en la liga. Los fichajes acordados se completarán cuando se abra la próxima ventana.',
    'ai_transfer_message_none' => 'La ventana de fichajes está cerrada. Los fichajes acordados se completarán cuando se abra la próxima ventana.',
    'ai_transfer_window_summer' => 'Verano',
    'ai_transfer_window_winter' => 'Invierno',

    // Player released
    'player_released_title' => ':player liberado',
    'player_released_message' => ':player ha sido liberado de tu plantilla. Indemnización pagada: :severance.',
    'player_released_message_free' => ':player ha sido liberado de tu plantilla.',

    // Fichajes de emergencia
    'emergency_signing_title' => 'Refuerzo de emergencia',
    'emergency_signing_message' => 'Tu plantilla estaba en niveles críticos. Se han fichado :count agentes libres para asegurar que puedas alinear un equipo: :players.',

    // Partido perdido por incomparecencia
    'match_forfeit_title' => 'Partido perdido por incomparecencia',
    'match_forfeit_message' => 'Tu equipo no pudo alinear el mínimo de 7 jugadores. El partido se ha registrado como una derrota 0-3.',

    // Reputation changes
    'reputation_change_title' => 'Reputación del club modificada',
    'reputation_improved' => 'La reputación de tu club ha ascendido a :tier. Patrocinadores, jugadores y aficionados lo notan.',
    'reputation_declined' => 'La reputación de tu club ha descendido a :tier. Es hora de reconstruir y recuperar la gloria pasada.',

    // Budget loan
    'budget_loan_taken_title' => 'Préstamo presupuestario concedido',
    'budget_loan_taken_message' => 'El club ha obtenido un préstamo de :amount. La devolución de :repayment se descontará del presupuesto de la próxima temporada.',
    'budget_loan_repaid_title' => 'Préstamo presupuestario devuelto',
    'budget_loan_repaid_message' => 'El préstamo presupuestario ha sido devuelto (:repayment con intereses).',
    'budget_loan_repaid_with_debt' => 'La devolución del préstamo de :repayment superó el superávit disponible. El déficit se arrastra como deuda.',

    // Stadium
    'stadium_supplementary_committed_title' => 'Obras de gradas iniciadas',
    'stadium_supplementary_committed_message' => 'Se han contratado :capacity asientos supletorios. Estarán listos para el :completion.',
    'stadium_stand_expansion_committed_title' => 'Ampliación de grada aprobada',
    'stadium_stand_expansion_committed_message' => 'Se ha aprobado una ampliación de :capacity nuevos asientos permanentes. Estarán operativos el :completion.',
    'stadium_rebuild_committed_title' => 'Reforma del estadio aprobada',
    'stadium_rebuild_committed_message' => 'Se ha iniciado la reforma para un aforo de :capacity asientos. El nuevo estadio abrirá el :completion.',
    'stadium_supplementary_completed_title' => 'Gradas supletorias listas',
    'stadium_supplementary_completed_message' => 'Las nuevas gradas ya están operativas. Aforo total: :capacity asientos.',
    'stadium_stand_expansion_completed_title' => 'Nueva grada inaugurada',
    'stadium_stand_expansion_completed_message' => 'La grada ampliada ya está operativa. Aforo total: :capacity asientos.',
    'stadium_rebuild_completed_title' => 'Estadio inaugurado',
    'stadium_rebuild_completed_message' => 'El nuevo estadio ha sido inaugurado con un aforo de :capacity asientos.',
    'stadium_uefa_upgrade_committed_title' => 'Mejora UEFA aprobada',
    'stadium_uefa_upgrade_committed_message' => 'Se ha iniciado la reforma para alcanzar la Categoría UEFA :capacity. La nueva categoría estará vigente el :completion.',
    'stadium_uefa_upgrade_completed_title' => 'Nueva Categoría UEFA',
    'stadium_uefa_upgrade_completed_message' => 'Tu estadio ha alcanzado la Categoría UEFA :capacity.',
    'stadium_loan_drawn_title' => 'Préstamo del estadio formalizado',
    'stadium_loan_drawn_message' => 'El banco ha financiado el proyecto con :amount, a devolver en :years cuotas anuales.',
    'stadium_loan_repaid_title' => 'Préstamo del estadio devuelto',
    'stadium_loan_repaid_message' => 'El préstamo de :amount ha sido devuelto en su totalidad.',
    'commercial_window_open_title' => 'Ventana comercial abierta',
    'commercial_window_open_message' => 'Hasta el primer partido de liga puedes buscar patrocinadores en la página Comercial para aumentar tus ingresos y tu tope salarial.',

    // Squad registration
    'squad_registration_required_title' => 'Inscripción de plantilla requerida',
    'squad_registration_required_message' => 'Tienes :count jugadores sin inscribir. Registra tu plantilla antes de que comience la temporada — los jugadores no inscritos no podrán ser convocados.',
    'unenrolled_before_window_close_title' => 'Jugadores sin inscribir — cierra la ventana de :window',
    'unenrolled_before_window_close_message' => 'Tienes :count jugadores sin inscribir. Esta es tu última jornada para registrarlos antes de que cierre la ventana de fichajes — sin dorsal no podrán ser convocados.',
];
