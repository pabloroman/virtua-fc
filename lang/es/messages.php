<?php

return [
    // Transfer messages
    'transfer_complete' => '¡Fichaje completado! :player se ha unido a tu plantilla.',
    'transfer_agreed' => ':message El fichaje se completará cuando abra la ventana de :window.',
    'transfer_bid_sent' => ':message',
    'transfer_bid_failed' => ':message',
    'bid_exceeds_budget' => 'La oferta supera tu presupuesto de fichajes.',
    'player_listed' => ':player puesto a la venta. Las ofertas pueden llegar tras la próxima jornada.',
    'player_unlisted' => ':player retirado de la lista de fichajes.',
    'offer_rejected' => 'Oferta de :team rechazada.',
    'offer_accepted_sale' => ':player vendido a :team por :fee.',
    'offer_accepted_pre_contract' => '¡Acuerdo cerrado! :player fichará por :team por :fee cuando abra la ventana de :window.',

    // Counter offer
    'counter_offer_accepted' => '¡Contraoferta aceptada! :player se unirá cuando abra la ventana de :window.',
    'counter_offer_accepted_immediate' => '¡Fichaje completado! :player se ha unido a tu plantilla.',
    'counter_offer_expired' => 'Esta oferta ya no está disponible.',

    // Loan messages
    'loan_complete' => ':player ha sido cedido a :team.',
    'loan_agreed' => ':message La cesión comenzará cuando abra la ventana de :window.',
    'loan_in_complete' => ':message La cesión ya está activa.',
    'loan_failed' => ':message',
    'already_on_loan' => ':player ya está cedido.',
    'no_suitable_club' => 'No se encontró un club adecuado para la cesión.',
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
    'renewal_counter_accepted' => ':player ha aceptado la contraoferta.',

    // Pre-contract messages
    'pre_contract_accepted' => '¡:player ha aceptado tu oferta de precontrato! Se unirá a tu equipo al final de la temporada.',
    'pre_contract_rejected' => ':player ha rechazado tu oferta de precontrato. Intenta mejorar las condiciones salariales.',
    'pre_contract_not_available' => 'Las ofertas de precontrato solo están disponibles entre enero y mayo.',
    'player_not_expiring' => 'Este jugador no tiene el contrato en su último año.',

    // Scout messages
    'scout_search_started' => 'El ojeador ha iniciado la búsqueda.',
    'scout_already_searching' => 'Ya tienes una búsqueda activa. Cancélala primero o espera los resultados.',
    'scout_search_cancelled' => 'Búsqueda del ojeador cancelada.',

    // Budget messages
    'budget_saved' => 'Asignación de presupuesto guardada.',
    'budget_no_projections' => 'No se encontraron proyecciones financieras.',

    // Season messages
    'new_season_started' => '¡Bienvenido a la temporada :season!',
    'budget_exceeds_surplus' => 'La asignación total supera el superávit disponible.',
    'budget_minimum_tier' => 'Todas las áreas de infraestructura deben ser al menos Nivel 1.',

    // Onboarding
    'welcome_to_team' => '¡Bienvenido a :team! Tu temporada te espera.',

    // Season
    'season_not_complete' => 'No se puede iniciar una nueva temporada - la temporada actual no ha terminado.',

    // Lineup
    'lineup_confirmed' => 'Alineación confirmada.',
    'lineup_invalid' => 'La alineación debe tener exactamente 11 jugadores.',
    'lineup_unavailable_players' => 'Algunos jugadores seleccionados no están disponibles.',

    // Academy
    'academy_player_promoted' => ':player ha sido subido al primer equipo.',
    'academy_player_not_found' => 'Jugador no encontrado en la cantera.',

    // Game management
    'game_deleted' => 'Partida eliminada correctamente.',
    'game_limit_reached' => 'Has alcanzado el límite máximo de 3 partidas. Elimina una para crear otra nueva.',
];
