<?php

return [
    // Transfer messages
    'transfer_complete' => '¡Fichaje completado! :player se ha unido a tu plantilla.',
    'transfer_agreed' => ':message El fichaje se completará cuando abra la ventana de :window.',
    'bid_exceeds_budget' => 'La oferta supera tu presupuesto de fichajes.',
    'player_listed' => ':player puesto a la venta. Las ofertas pueden llegar tras la próxima jornada.',
    'player_unlisted' => ':player retirado de la lista de fichajes.',
    'cannot_sell_same_window' => 'No se puede vender a :player — fue fichado recientemente y aún no puede ser traspasado.',
    'offer_rejected' => 'Oferta :team_de rechazada.',
    'offer_accepted_sale' => ':player vendido :team_a por :fee.',
    'offer_accepted_pre_contract' => '¡Acuerdo cerrado! :player fichará por :team por :fee cuando abra la ventana de :window.',
    'offer_accepted_intra_window' => '¡Acuerdo cerrado! :player se marchará :team_a por :fee tras el próximo partido.',

    // Free agent signing
    'free_agent_signed' => '¡:player ha fichado por tu equipo como agente libre!',
    'free_agent_agreed' => '¡Acuerdo cerrado! :player se incorporará como agente libre tras el próximo partido.',
    'not_free_agent' => 'Este jugador no es agente libre.',
    'free_agent_reputation_too_low' => 'Este jugador no tiene interés en fichar por un club de tu nivel de reputación.',
    'transfer_window_closed' => 'La ventana de fichajes está cerrada.',
    'wage_budget_exceeded' => 'Fichar a este jugador superaría tu presupuesto salarial.',
    'signing_exceeds_salary_cap' => 'Fichar a :player por :wage/año elevaría tu masa salarial a :total, por encima de tu límite salarial de :cap. Libera :shortfall vendiendo jugadores primero.',
    'salary_cap_locked' => 'Estás por encima de tu límite salarial. Vende jugadores para volver por debajo del límite antes de fichar o renovar.',

    // Bid/loan submission confirmations
    'bid_already_exists' => 'Ya tienes una oferta pendiente por este jugador.',
    'loan_request_submitted' => 'Tu solicitud de cesión por :player ha sido enviada. Recibirás respuesta próximamente.',

    // Loan messages
    'loan_agreed' => ':message La cesión comenzará cuando abra la ventana de :window.',
    'loan_in_complete' => ':message La cesión ya está activa.',
    'already_on_loan' => ':player ya está cedido.',
    'loan_search_started' => 'Se ha iniciado la búsqueda de destino para :player. Se te notificará cuando se encuentre un club.',
    'loan_search_active' => ':player ya tiene una búsqueda de cesión activa.',
    'loan_search_cancelled' => 'Se ha cancelado la búsqueda de cesión de :player.',
    'loan_offer_accepted' => ':player cedido :team_a.',
    'loan_offer_accepted_pre_window' => ':player será cedido :team_a cuando abra la ventana de :window.',
    'loan_offer_agreed_intra_window' => ':player será cedido :team_a tras el próximo partido.',

    // Contract messages
    'renewal_agreed' => ':player ha aceptado una extensión de :years años a :wage/año (efectivo desde la próxima temporada).',
    'renewal_failed' => 'No se pudo procesar la renovación.',
    'renewal_declined' => 'Has decidido no renovar a :player. Se marchará al final de la temporada.',
    'renewal_reconsidered' => 'Has reconsiderado la renovación de :player.',
    'cannot_renew' => 'Este jugador no puede recibir una oferta de renovación.',
    'renewal_invalid_offer' => 'La oferta debe ser mayor que cero.',

    // Pre-contract messages
    'pre_contract_accepted' => '¡:player ha aceptado tu oferta de precontrato! Se unirá a tu equipo al final de la temporada.',
    'pre_contract_rejected' => ':player ha rechazado tu oferta de precontrato. Intenta mejorar las condiciones salariales.',
    'pre_contract_not_available' => 'Las ofertas de precontrato solo están disponibles entre enero y mayo.',
    'player_not_expiring' => 'Este jugador no tiene el contrato en su último año.',
    'pre_contract_submitted' => 'Oferta de precontrato enviada. El jugador responderá en los próximos días.',
    'pre_contract_result_accepted' => '¡:player ha aceptado tu oferta de precontrato!',
    'pre_contract_result_rejected' => ':player ha rechazado tu oferta de precontrato.',

    // Scout messages
    'scout_search_started' => 'El ojeador ha iniciado la búsqueda.',
    'scout_already_searching' => 'Ya tienes una búsqueda activa. Cancélala primero o espera los resultados.',
    'scout_search_cancelled' => 'Búsqueda del ojeador cancelada.',
    'scout_search_deleted' => 'Búsqueda eliminada.',
    'scout_search_limit' => 'Has alcanzado el límite de búsquedas (máximo :max). Elimina una búsqueda antigua para iniciar una nueva.',

    // Shortlist messages
    'shortlist_added' => ':player añadido a tu lista de seguimiento.',
    'shortlist_removed' => ':player eliminado de tu lista de seguimiento.',
    'shortlist_full' => 'Tu lista de seguimiento está llena (máximo :max jugadores).',

    // Budget messages
    'budget_saved' => 'Asignación de presupuesto guardada.',
    'budget_no_projections' => 'No se encontraron proyecciones financieras.',

    // Stadium / abonos
    'season_tickets_saved' => 'Precios de abonos guardados.',
    'season_tickets_locked' => 'Los precios de los abonos ya están bloqueados para esta temporada.',

    // Season messages
    'budget_exceeds_surplus' => 'La asignación total supera el superávit disponible.',
    'budget_minimum_tier' => 'Todas las áreas de infraestructura deben ser al menos Nivel 1.',

    // Infrastructure upgrades
    'infrastructure_upgraded' => ':area mejorada a Nivel :tier.',
    'infrastructure_upgrade_invalid_area' => 'Área de infraestructura no válida.',
    'infrastructure_upgrade_not_higher' => 'El nivel objetivo debe ser superior al actual.',
    'infrastructure_upgrade_max_tier' => 'El nivel máximo es 4.',
    'infrastructure_upgrade_insufficient_budget' => 'Presupuesto de fichajes insuficiente. La mejora cuesta :cost.',

    // Onboarding
    'welcome_to_team' => '¡Bienvenido :team_a! Tu temporada te espera.',

    // Season
    'season_not_complete' => 'No se puede iniciar una nueva temporada - la temporada actual no ha terminado.',

    // Academy
    'academy_player_promoted' => ':player ha sido subido al primer equipo.',
    'academy_player_dismissed' => ':player ha sido despedido de la cantera.',
    'academy_player_loaned' => ':player ha sido cedido.',
    'academy_must_decide_21' => 'Los jugadores de 21+ años serán promocionados automáticamente al primer equipo.',

    // Reserve team (filial)
    'reserve_player_called_up' => ':player ha sido convocado al primer equipo.',
    'reserve_player_sent_back' => ':player ha vuelto al filial.',
    'reserve_player_call_up_blocked_full' => 'La plantilla del primer equipo está completa. Libera un dorsal antes de subir más jugadores.',
    'player_sent_down_to_reserve' => ':player ha sido enviado al filial.',
    'send_down_not_allowed' => 'Este jugador no puede ser enviado al filial.',
    'send_down_squad_too_small' => 'No se puede enviar al filial — el primer equipo debe tener al menos :min jugadores.',
    'send_down_position_minimum' => 'No se puede enviar al filial — el primer equipo necesita al menos :min :group.',
    'reserve_player_promoted' => ':player ha subido al primer equipo.',

    // Player release messages
    'player_released' => ':player ha sido liberado. Indemnización pagada: :severance.',
    'release_not_your_player' => 'Solo puedes liberar jugadores de tu propio equipo.',
    'release_on_loan' => 'No se puede liberar a un jugador cedido.',
    'release_has_agreed_transfer' => 'No se puede liberar a un jugador con un traspaso acordado.',
    'release_has_pre_contract' => 'No se puede liberar a un jugador con un precontrato firmado.',
    'release_squad_too_small' => 'No se puede liberar — tu plantilla debe tener al menos :min jugadores.',
    'release_position_minimum' => 'No se puede liberar — necesitas al menos :min :group.',

    // Squad-minimum guards on promote / demote / list / accept
    'promote_squad_too_small' => 'No se puede subir — el filial debe tener al menos :min jugadores.',
    'promote_position_minimum' => 'No se puede subir — el filial necesita al menos :min :group.',
    'demote_squad_too_small' => 'No se puede bajar al filial — el primer equipo debe tener al menos :min jugadores.',
    'demote_position_minimum' => 'No se puede bajar al filial — el primer equipo necesita al menos :min :group.',
    'list_for_sale_squad_too_small' => 'No se puede poner a la venta — tu plantilla debe tener al menos :min jugadores.',
    'list_for_sale_position_minimum' => 'No se puede poner a la venta — necesitas al menos :min :group.',
    'list_for_loan_squad_too_small' => 'No se puede ceder — tu plantilla debe tener al menos :min jugadores.',
    'list_for_loan_position_minimum' => 'No se puede ceder — necesitas al menos :min :group.',
    'accept_offer_squad_too_small' => 'No se puede aceptar la oferta — tu plantilla debe tener al menos :min jugadores.',
    'accept_offer_position_minimum' => 'No se puede aceptar la oferta — necesitas al menos :min :group.',
    'accept_loan_squad_too_small' => 'No se puede aceptar la cesión — tu plantilla debe tener al menos :min jugadores.',
    'accept_loan_position_minimum' => 'No se puede aceptar la cesión — necesitas al menos :min :group.',

    'cannot_loan_free_agent' => 'No se puede ceder a un jugador libre. Fíchalo directamente.',

    // Pending actions
    'action_required' => 'Hay acciones pendientes que debes resolver antes de continuar.',
    'action_required_short' => 'Acción Requerida',

    // Tracking
    'tracking_started' => 'Ahora rastreando a :player.',
    'tracking_stopped' => 'Se dejó de rastrear a :player.',
    'tracking_slots_full' => 'Todos los seguimientos están en uso. Deja de rastrear a otro jugador primero.',

    // Tactical presets
    'preset_saved' => 'Táctica guardada.',
    'preset_updated' => 'Táctica actualizada.',
    'preset_deleted' => 'Táctica eliminada.',
    'preset_limit_reached' => 'Máximo de 3 tácticas guardadas alcanzado.',

    // Game management
    'game_deleted' => 'La partida se está eliminando.',
    'game_limit_reached' => 'Has alcanzado el límite máximo de 3 partidas. Elimina una para crear otra nueva.',
    'career_mode_requires_invite' => '¡Club Manager y Pro Manager requieren invitación. Juega el Mundial gratis!',
    'tournament_mode_requires_access' => 'El modo torneo requiere acceso. Contacta con un administrador para empezar.',
    'invalid_pro_manager_team' => 'Elige uno de los clubes mostrados — Pro Manager empieza en Primera RFEF.',

    // Pre-match confirmation
    'pre_match_title' => 'Previa del Partido',
    'pre_match_no_lineup' => 'No tienes una alineación configurada.',
    'pre_match_incomplete' => 'Tu alineación tiene menos de 11 jugadores.',
    'pre_match_unavailable_injured' => 'Tienes un jugador lesionado en tu alineación.',
    'pre_match_unavailable_suspended' => 'Tienes un jugador sancionado en tu alineación.',
    'pre_match_unavailable_multiple' => 'Tienes jugadores no disponibles en tu alineación.',
    'pre_match_auto_explanation' => 'Si no lo cambias, tu cuerpo técnico elegirá la mejor alineación entre los jugadores disponibles.',
    'pre_match_warning_title' => 'Tu alineación necesita atención',
    'pre_match_play' => 'Jugar Partido',
    'pre_match_continue' => 'Continuar',
    'pre_match_edit_lineup' => 'Editar Alineación',
    'pre_match_reason_injured' => 'lesionado',
    'pre_match_reason_suspended' => 'sancionado',
    'pre_match_starting_xi' => 'Once Titular',
    'pre_match_no_lineup_set' => 'Alineación no configurada',
    'pre_match_auto_lineup' => 'Dejar al cuerpo técnico modificar la alineación automáticamente cuando haya jugadores no disponibles.',
    'pre_match_auto_select_done' => 'Se ha seleccionado automáticamente la mejor alineación entre los jugadores disponibles.',

    // Matchday advance
    'advance_failed' => 'Error al avanzar la jornada. Inténtalo de nuevo.',

    // Fast mode
    'fast_mode_enabled' => 'Modo rápido activado. Tu entrenador asistente dirigirá al equipo.',
    'fast_mode_disabled' => 'Modo rápido desactivado. Vuelves a tener el control.',
    'fast_mode_action_required' => 'Una acción requiere tu atención. Sal del modo rápido para resolverla.',
    'fast_mode_blocked_live_match' => 'Termina el partido actual antes de activar el modo rápido.',
    'fast_mode_blocked_tournament' => 'El modo rápido no está disponible en el modo torneo.',
    'fast_mode_advance_failed_retry' => 'No se pudo simular la jornada. Inténtalo de nuevo.',

    // Budget loan messages
    'budget_loan_approved' => 'Préstamo de :amount aprobado y añadido a tu presupuesto de fichajes.',
    'loan_not_available' => 'Un préstamo presupuestario no está disponible ahora mismo.',
    'loan_below_minimum' => 'El importe del préstamo está por debajo del mínimo.',
    'loan_exceeds_maximum' => 'El importe del préstamo supera el máximo permitido.',

    'stadium_supplementary_committed' => 'Obras iniciadas: :seats asientos supletorios estarán listos en 30 días.',
    'stadium_stand_expansion_committed' => 'Ampliación de grada aprobada: :seats nuevos asientos permanentes estarán listos la próxima temporada.',
    'stadium_rebuild_committed' => 'Reforma del estadio aprobada. Nuevo aforo objetivo: :capacity.',
    'stadium_active_project_exists' => 'Ya tienes un proyecto en curso. Espera a que termine antes de iniciar otro.',
    'stadium_supplementary_too_few_seats' => 'Debes añadir al menos un asiento supletorio.',
    'stadium_supplementary_exceeds_cap' => 'Supera el límite de gradas supletorias permitidas.',
    'stadium_rebuild_reputation_too_low' => 'Tu reputación todavía no permite una reforma integral del estadio.',
    'stadium_rebuild_must_be_larger' => 'El aforo objetivo debe ser mayor que el actual.',
    'stadium_rebuild_exceeds_max_capacity' => 'El aforo objetivo supera el tope que tu reputación e ingresos permiten financiar.',
    'stadium_invalid_financing' => 'Financiación no válida.',
    'stadium_insufficient_budget' => 'No tienes presupuesto suficiente para pagar el proyecto al contado.',
    'stadium_loan_exceeds_cap' => 'El préstamo solicitado supera el tope autorizado por el banco.',
    'stadium_uefa_upgrade_committed' => 'Mejora UEFA iniciada: el estadio alcanzará la Categoría :level la próxima temporada.',
    'stadium_uefa_already_max' => 'Tu estadio ya está en la máxima categoría UEFA.',
    'stadium_uefa_capacity_floor' => 'El aforo actual no alcanza el mínimo exigido por la siguiente categoría UEFA.',
    'stadium_uefa_no_base_level' => 'Tu estadio no tiene categoría UEFA asignada. Amplía el aforo primero.',
];
