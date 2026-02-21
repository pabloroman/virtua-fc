<?php

return [
    // Transfer messages
    'transfer_complete' => '¡Fichaje completado! :player se ha unido a tu plantilla.',
    'transfer_agreed' => ':message El fichaje se completará cuando abra la ventana de :window.',
    'bid_exceeds_budget' => 'La oferta supera tu presupuesto de fichajes.',
    'player_listed' => ':player puesto a la venta. Las ofertas pueden llegar tras la próxima jornada.',
    'player_unlisted' => ':player retirado de la lista de fichajes.',
    'offer_rejected' => 'Oferta :team_de rechazada.',
    'offer_accepted_sale' => ':player vendido :team_a por :fee.',
    'offer_accepted_pre_contract' => '¡Acuerdo cerrado! :player fichará por :team por :fee cuando abra la ventana de :window.',

    // Bid/loan submission confirmations
    'bid_submitted' => 'Tu oferta por :player ha sido enviada. Recibirás respuesta próximamente.',
    'loan_request_submitted' => 'Tu solicitud de cesión por :player ha sido enviada. Recibirás respuesta próximamente.',

    // Counter offer
    'counter_offer_accepted' => '¡Contraoferta aceptada! :player se unirá cuando abra la ventana de :window.',
    'counter_offer_accepted_immediate' => '¡Fichaje completado! :player se ha unido a tu plantilla.',
    'counter_offer_expired' => 'Esta oferta ya no está disponible.',

    // Loan messages
    'loan_agreed' => ':message La cesión comenzará cuando abra la ventana de :window.',
    'loan_in_complete' => ':message La cesión ya está activa.',
    'already_on_loan' => ':player ya está cedido.',
    'loan_search_started' => 'Se ha iniciado la búsqueda de destino para :player. Se te notificará cuando se encuentre un club.',
    'loan_search_active' => ':player ya tiene una búsqueda de cesión activa.',

    // Contract messages
    'renewal_agreed' => ':player ha aceptado una extensión de :years años a :wage/año (efectivo desde la próxima temporada).',
    'renewal_failed' => 'No se pudo procesar la renovación.',
    'renewal_declined' => 'Has decidido no renovar a :player. Se marchará al final de la temporada.',
    'renewal_reconsidered' => 'Has reconsiderado la renovación de :player.',
    'cannot_renew' => 'Este jugador no puede recibir una oferta de renovación.',
    'renewal_offer_submitted' => 'Oferta de renovación enviada a :player por :wage/año. Respuesta en la próxima jornada.',
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

    // Shortlist messages
    'shortlist_added' => ':player añadido a tu lista de seguimiento.',
    'shortlist_removed' => ':player eliminado de tu lista de seguimiento.',

    // Budget messages
    'budget_saved' => 'Asignación de presupuesto guardada.',
    'budget_no_projections' => 'No se encontraron proyecciones financieras.',

    // Season messages
    'budget_exceeds_surplus' => 'La asignación total supera el superávit disponible.',
    'budget_minimum_tier' => 'Todas las áreas de infraestructura deben ser al menos Nivel 1.',

    // Onboarding
    'welcome_to_team' => '¡Bienvenido :team_a! Tu temporada te espera.',

    // Season
    'season_not_complete' => 'No se puede iniciar una nueva temporada - la temporada actual no ha terminado.',

    // Academy
    'academy_player_promoted' => ':player ha sido subido al primer equipo.',
    'academy_evaluation_required' => 'Debes evaluar a los canteranos antes de continuar.',
    'academy_evaluation_complete' => 'Evaluación de cantera completada.',
    'academy_player_dismissed' => ':player ha sido despedido de la cantera.',
    'academy_player_loaned' => ':player ha sido cedido.',
    'academy_over_capacity' => 'La cantera supera la capacidad. Debes liberar :excess plaza(s).',
    'academy_must_decide_21' => 'Los jugadores de 21+ años deben ser subidos o despedidos.',

    // Pending actions
    'action_required' => 'Hay acciones pendientes que debes resolver antes de continuar.',
    'action_required_short' => 'Acción Requerida',

    // Game management
    'game_deleted' => 'Partida eliminada correctamente.',
    'game_limit_reached' => 'Has alcanzado el límite máximo de 3 partidas. Elimina una para crear otra nueva.',
];
