<?php

return [
    // Page title
    'window' => 'Ventana',
    'window_open' => 'Ventana de :window Abierta',
    'budget' => 'Presupuesto',
    'budget_committed' => 'comprometidos',

    // Tab labels
    'outgoing' => 'Salidas',
    'incoming' => 'Fichajes',
    'scouting_tab' => 'Ojeadores',

    // Window countdown
    'window_closes_in' => 'cierra el :date',
    'window_opens_in' => 'abre el :date',

    // Wage bill
    'wage_bill' => 'Masa Salarial',

    // Section headers
    'pre_contract_offers_received' => 'Ofertas de Precontrato Recibidas',
    'pre_contract_offers_help' => 'Jugadores en último año de contrato con ofertas de otros clubes',
    'players_leaving_free' => 'Jugadores que Se Van Libres',
    'players_leaving_free_help' => 'Jugadores que han firmado precontrato con otro club',
    'expiring_contracts_section' => 'Jugadores en Último Año de Contrato',
    'pending_renewals_section' => 'Renovaciones Pendientes',
    'loans_out_section' => 'Cesiones Activas (Salidas)',
    'loan_searches_section' => 'Buscando Destino de Cesión',
    'loan_searches_help' => 'Jugadores buscando un club para ser cedidos',
    // Legacy keys kept for compatibility
    'loans' => 'Cesiones',

    'loan_request' => 'Solicitud de Cesión',
    'loan_no_fee' => 'Cesión (sin coste)',
    'free_transfer' => 'Libre (sin contrato)',
    'free_agent' => 'Agente Libre',
    'sign_free_agent' => 'Fichar Agente Libre',
    'sign' => 'Fichar',
    'window_closed_for_signing' => 'Ventana de fichajes cerrada — no se puede fichar.',
    'wage_exceeds_budget' => 'El salario solicitado supera tu presupuesto salarial.',
    // Rejected bids
    'rejected_bids' => 'Ofertas Rechazadas',
    'bid_rejected' => 'Oferta rechazada',

    // Active negotiations
    'active_negotiations' => 'Negociaciones activas',
    'active_negotiations_description' => 'Ofertas en curso por jugadores que estás fichando.',
    'withdraw_offer' => 'Retirar',
    'offer_withdrawn' => 'Oferta retirada por :player.',
    'resume_negotiation' => 'Negociar',
    'confirm_withdraw' => '¿Seguro que quieres retirar esta oferta?',

    // Incoming transfers
    'incoming_transfers' => 'Fichajes Entrantes',
    'completing_when_window' => 'se completará cuando abra la ventana de :window',
    'deal_agreed' => 'Acuerdo cerrado',

    // Unsolicited offers
    'unsolicited_offers' => 'Ofertas No Solicitadas',
    'unsolicited_offers_help' => 'Otros clubes quieren fichar a tus jugadores',

    // Offers received
    'offers_received' => 'Ofertas Recibidas',
    'offers_received_help' => 'Ofertas recibidas por jugadores que has puesto en venta',
    'expires_in_days' => 'Expira en :days días',
    'from' => 'de',

    // Agreed transfers
    'agreed_transfers' => 'Fichajes Acordados',

    // Listed players
    'listed_players' => 'Jugadores en Venta',
    'listed_players_help' => 'Jugadores que has puesto en el mercado de traspasos',
    'list_more_from_squad' => 'Poner más jugadores en venta desde Plantilla',
    'no_offers_yet' => 'Sin ofertas aún',
    'offers_count' => ':count oferta(s)',
    'best' => 'Mejor',

    // Recent transfers
    'recent_sales' => 'Ventas Recientes',
    'recent_signings' => 'Fichajes Recientes',

    // Scouting
    'scout_search_desc' => 'Tu ojeador buscará jugadores y te presentará una lista corta de candidatos que mejor se adapten a tu equipo. Cuanto más específicos sean los criterios, mejores serán los resultados.',
    'position_required' => 'Posición *',
    'select_position' => 'Seleccionar posición...',
    'specific_positions' => 'Posiciones Específicas',
    'position_groups' => 'Grupos de Posición (búsqueda más amplia)',
    // Position group labels moved to lang/es/positions.php
    'league' => 'Liga',
    'scope' => 'Rango de búsqueda',
    'scope_domestic' => 'Nacional',
    'scope_international' => 'Internacional',
    'scope_international_locked' => 'Requiere ojeadores de nivel 3 o superior.',
    'age_range' => 'Rango de Edad',
    'ability_range' => 'Rango de Habilidad',
    'value_range' => 'Rango de Valor de Mercado',
    'contract' => 'Contrato',
    'expiring_contract' => 'Último año de contrato',
    'start_scout_search' => 'Iniciar Búsqueda',

    // Scout searching
    'scout_searching' => 'El ojeador está buscando...',
    'looking_for' => 'Buscando',
    'cancel_search' => 'Cancelar Búsqueda',

    // Scout results
    'scout_results' => 'Resultados de búsqueda',
    'all_ages' => 'Todas',
    'no_players_found' => 'No se encontraron jugadores con tus criterios.',
    'try_broadening' => 'Intenta ampliar tu búsqueda.',
    'ability' => 'Habilidad',

    // Scouting player page
    'market_value' => 'Valor de Mercado',
    'contract_until' => 'Contrato hasta',
    'technical' => 'Técnico',
    'physical' => 'Físico',
    'submit_bid' => 'Enviar Oferta',
    'bid_pending' => 'Oferta Pendiente',
    'bid_awaiting_response' => 'Esperando Respuesta',
    'counter_offer_received' => 'Contraoferta Recibida',
    'transfer_agreed' => 'Fichaje Acordado',
    'already_bidding' => 'Ya tienes una oferta por este jugador',
    'negotiation_cooldown' => 'Las negociaciones con este jugador se rompieron recientemente. Espera a la siguiente jornada para intentarlo de nuevo.',
    'negotiation_cooldown_short' => 'Espera a la próxima jornada',
    'scouting_assessment' => 'Evaluación del Ojeador',
    'financial_details' => 'Detalles Financieros',
    'estimated_asking_price' => 'Precio de Venta Estimado',
    'wage_demand' => 'Salario Solicitado',
    'your_transfer_budget' => 'Tu Presupuesto de Fichajes',
    'transfer_fee_exceeds_budget' => 'El precio de traspaso excede tu presupuesto. No puedes pujar por este jugador.',
    'transfer_fee_exceeds_budget_loan_available' => 'El precio de traspaso excede tu presupuesto, pero puedes solicitar una cesión.',
    'loan_fee_exceeds_budget' => 'Tanto el traspaso como la cesión exceden tu presupuesto.',
    'loan_cost_salary' => 'Coste cesión (salario)',
    'asking_price' => 'Precio pedido',
    'request_loan' => 'Solicitar Cesión',
    'budget_cap_warning' => 'Tu oferta está limitada por tu presupuesto de fichajes disponible.',
    'budget_available' => 'Presupuesto disponible',
    'request_budget_loan' => 'Solicitar préstamo presupuestario',
    'budget_limited_hint' => 'Tu presupuesto está por debajo del precio de venta. Aún puedes negociar o solicitar un préstamo presupuestario.',

    // Bid evaluation responses
    'bid_accepted' => ':team ha aceptado tu oferta.',
    'counter_offer_made' => ':team ha hecho una contraoferta de :amount.',
    'bid_rejected_too_low' => ':team ha rechazado tu oferta. Estaba muy por debajo de su valoración.',
    'bid_rejected_not_interested' => 'El jugador no está interesado en fichar por tu club.',
    'loan_rejected_key_player' => ':team rechazó la solicitud de cesión. :player es un jugador clave para ellos.',
    'loan_accepted' => ':team ha aceptado ceder a :player a tu club.',
    'loan_rejected_keep' => ':team ha decidido quedarse con :player por ahora.',
    'loan_rejected_not_interested' => ':player no está interesado en ir cedido a tu club.',

    // Loans page
    'loaned_to' => 'Cedido :team_a',
    'active_loans_in' => 'Cesiones Activas (Entradas)',
    'loaned_from' => 'Cedido :team_de',
    'searching_destination' => 'Buscando club...',
    'returns' => 'Regresa',
    'years' => ':count año|:count años',

    // Pre-contract
    'expiring_contract_hint' => 'Los jugadores en su último año de contrato pueden ficharse gratis. Puedes hacer una oferta de precontrato a partir del 1 de enero.',
    'offered_wage_euros' => 'Salario Ofrecido (euros/año)',
    'submit_pre_contract' => 'Ofrecer Precontrato',

    // Search history
    'search_history' => 'Historial de Búsquedas',
    'no_search_history' => 'Sin búsquedas anteriores.',
    'view_results' => 'Ver',
    'results_count' => ':count resultado(s)',
    'delete_search' => 'Eliminar',
    'delete_search_confirm' => '¿Eliminar esta búsqueda? Los jugadores en tu lista de seguimiento no se perderán.',

    // Shortlist
    'shortlist' => 'Lista de Seguimiento',
    'sort_by' => 'Ordenar',
    'sort_name' => 'Nombre',
    'sort_age' => 'Edad',
    'sort_ability' => 'Habilidad',
    'sort_price' => 'Precio',
    'shortlist_empty' => 'Añade jugadores a tu lista de seguimiento desde los informes de ojeadores.',
    'add_to_shortlist' => 'Seguir',
    'remove_from_shortlist' => 'Dejar de seguir',
    'shortlisted' => 'Seguido',
    'shortlist_players_count' => ':count jugador(es)',

    // Empty states
    'no_outgoing_activity' => 'No hay actividad de salida',
    'no_incoming_activity' => 'Sin actividad de fichajes',

    // Scout search button
    'new_scout_search' => 'Nueva Búsqueda',

    // Transfers help text
    'transfers_help_toggle' => '¿Cómo funcionan los traspasos?',
    'transfers_help_intro' => 'Gestiona tus traspasos salientes, contratos y cesiones. Las ofertas llegan cada jornada según las condiciones del mercado.',
    'transfers_help_selling_title' => 'Vender jugadores',
    'transfers_help_selling_list' => 'Pon jugadores en venta desde la página de Plantilla. Otros clubes harán ofertas basadas en el valor del jugador.',
    'transfers_help_selling_unsolicited' => 'También puedes recibir ofertas no solicitadas por jugadores no listados, especialmente los de alto valor.',
    'transfers_help_selling_accept' => 'Los traspasos aceptados se completan cuando se abre la siguiente ventana de fichajes.',
    'transfers_help_contracts_title' => 'Contratos y Renovaciones',
    'transfers_help_contracts_expiring' => 'Los jugadores en su último año de contrato pueden ser fichados gratis por otros clubes mediante precontrato a partir de enero.',
    'transfers_help_contracts_renew' => 'Renueva a los jugadores antes de que sea tarde. Negocia el salario y la duración directamente con el agente del jugador.',
    'transfers_help_contracts_wages' => 'Vigila tu masa salarial. Salarios más altos atraen mejores jugadores pero reducen tu presupuesto de fichajes.',
    'transfers_help_loans_title' => 'Cesiones',
    'transfers_help_loans_out' => 'Cede a jugadores jóvenes o con poco protagonismo. Se desarrollarán jugando en otro club y regresarán al final de la temporada.',

    // Scouting help text
    'scouting_help_toggle' => '¿Cómo funcionan los ojeadores?',
    'scouting_help_intro' => 'Usa tus ojeadores para encontrar jugadores en el mercado de fichajes. Cuanto mejor sea tu infraestructura de ojeadores, mejores serán los resultados.',
    'scouting_help_search_title' => 'Búsquedas de ojeadores',
    'scouting_help_search_filters' => 'Configura filtros (posición, edad, habilidad, valor) y tu ojeador buscará jugadores que coincidan.',
    'scouting_help_search_time' => 'Cada búsqueda tarda varias jornadas en completarse. Solo puedes tener una búsqueda activa a la vez.',
    'scouting_help_search_scope' => 'Las búsquedas nacionales encuentran jugadores de tu liga. Las internacionales requieren nivel de ojeadores 3+.',
    'scouting_help_shortlist_title' => 'Lista de seguimiento y ofertas',
    'scouting_help_shortlist_star' => 'Marca con estrella a los jugadores de los informes para añadirlos a tu lista de seguimiento y compararlos fácilmente.',
    'scouting_help_shortlist_bid' => 'Envía una oferta desde la lista de seguimiento. El club vendedor responde en la siguiente jornada — puede aceptar, rechazar o contraofertar.',
    'scouting_help_shortlist_loan' => 'También puedes solicitar una cesión en vez de un traspaso permanente.',
    'scouting_help_shortlist_precontract' => 'Los jugadores en su último año de contrato pueden ficharse gratis mediante precontrato a partir de enero.',

    // Transfer activity summary
    'transfer_activity_title' => 'Resumen Ventana de :window',
    'transfer_activity_summer' => 'Verano',
    'transfer_activity_winter' => 'Invierno',
    'transfer_activity_transfers' => 'Traspasos',
    'transfer_activity_free_agents' => 'Fichajes de Agentes Libres',
    'transfer_activity_no_transfers' => 'Sin traspasos en esta ventana.',
    'transfer_activity_no_free_agents' => 'Sin fichajes de agentes libres en esta ventana.',
    'transfer_activity_player' => 'Jugador',
    'transfer_activity_from' => 'De',
    'transfer_activity_to' => 'A',
    'transfer_activity_fee' => 'Coste',
    'transfer_activity_position' => 'Pos',
    'transfer_activity_age' => 'Edad',
    'transfer_activity_foreign' => 'Extranjero',
    'transfer_activity_other_leagues' => 'Otras ligas',
    'transfer_activity_out' => 'Salida',
    'transfer_activity_in' => 'Entrada',

    // Decline renewal
    'reconsider_renewal' => 'Reconsiderar',
    'declined_renewals' => 'No renovados',

    // Renewal negotiation
    'negotiate' => 'Negociar',
    'negotiating' => 'Negociando...',
    'player_countered' => 'El jugador ha contraofertado',
    'your_offer' => 'Tu oferta (euros/año)',
    'current_wage' => 'Actual',
    'player_demand' => 'Pide',
    'response_next_matchday' => 'Respuesta en la próxima jornada',
    'accept_counter' => 'Aceptar',
    'mood_willing' => 'Dispuesto a renovar',
    'mood_open' => 'Abierto a negociar',
    'mood_reluctant' => 'Reticente',
    'mood_willing_sign' => 'Dispuesto a fichar',
    'mood_open_sign' => 'Abierto a negociar',
    'mood_reluctant_sign' => 'Reticente',
    'contract_duration' => 'Duración del contrato',

    // Explorer
    'explore_tab' => 'Explorar',
    'explore_title' => 'Explorar equipos',
    'explore_hint' => 'Explora las plantillas de otros equipos. Para información detallada de habilidades y precios, usa los ojeadores.',
    'explore_select_competition' => 'Selecciona una competición',
    'explore_select_team' => 'Selecciona un equipo para ver su plantilla',
    'explore_teams_count' => ':count equipos',
    'explore_squad_title' => 'Plantilla',
    'explore_no_teams' => 'No hay equipos disponibles.',
    'explore_scouting_nudge' => '¿Quieres más información? Inicia una búsqueda de ojeadores.',
    'explore_on_loan' => 'Cedido por :club',
    'explore_loaned_out' => 'Cedido a :club',
    'explore_contract_year' => 'Contrato',
    'explore_mobile_teams' => 'Equipos',
    'explore_mobile_squad' => 'Plantilla',
    'explore_goalkeepers' => 'Porteros',
    'explore_defenders' => 'Defensas',
    'explore_midfielders' => 'Centrocampistas',
    'explore_forwards' => 'Delanteros',
    'explore_age' => 'Edad',
    'explore_value' => 'Valor',
    'explore_free_agents' => 'Agentes Libres',
    'explore_free_agents_hint' => 'Explora agentes libres disponibles. Los agentes libres se pueden fichar en cualquier momento, incluso fuera de las ventanas de fichajes.',
    'explore_free_agents_empty' => 'No hay agentes libres disponibles.',
    'explore_filter_all' => 'Todos',
    'explore_free_agent_willing' => 'Interesado',
    'explore_free_agent_reluctant' => 'Difícil',
    'explore_free_agent_unwilling' => 'Sin interés',
    'explore_search_placeholder' => 'Buscar jugador por nombre...',
    'explore_search_results_title' => 'Resultados de búsqueda',
    'explore_search_no_results' => 'No se encontraron jugadores.',
    'explore_search_team' => 'Equipo',
    'explore_europe' => 'Europa',
    'explore_europe_hint' => 'Explora equipos europeos fuera de las 5 grandes ligas.',
    'explore_window_closed_hint' => 'Ventana de fichajes cerrada',
    'explore_make_offer' => 'Hacer oferta',
    'explore_negotiate' => 'Negociar',
    'explore_offer_hint' => 'Haz ofertas directas o añade jugadores a tu lista de seguimiento. Sin informe de ojeador, negociarás a ciegas.',

    // Free agent negotiation chat
    'chat_free_agent_title' => 'Negociación con Agente Libre',
    'chat_free_agent_demand' => 'El agente de :player pide :wage/año durante :years años.',
    'chat_free_agent_counter' => 'El agente de :player insiste en :wage/año durante :years años.',
    'chat_free_agent_accepted' => '¡:player ha firmado! Bienvenido al equipo.',
    'chat_free_agent_rejected' => 'El agente de :player se ha marchado. No hay acuerdo.',

    // Tracking
    'tracking_slots' => ':used/:max seguimientos',
    'start_tracking' => 'Seguir',
    'stop_tracking' => 'Parar',
    'track_to_unlock' => 'Rastrea para desbloquear intel',
    'track_to_unlock_desc' => 'Asigna a tu ojeador para rastrear a este jugador. La información se desbloquea cada jornada.',
    'tracking_in_progress_title' => 'Ojeador rastreando',
    'tracking_in_progress_desc' => 'Tu ojeador está rastreando a este jugador. La información se desbloqueará en la próxima jornada.',
    'tracking_in_progress' => 'Rastreando...',
    'no_tracking_slots' => 'Sin seguimientos disponibles',
    'willingness' => 'Disposición',
    'rival_interest' => 'Otros clubes interesados',
    'willingness_very_interested' => 'Muy interesado',
    'willingness_open' => 'Abierto a salir',
    'willingness_undecided' => 'Indeciso',
    'willingness_reluctant' => 'Reticente',
    'willingness_not_interested' => 'No interesado',
    'intel_surface' => 'Básico',
    'intel_report' => 'Informe',
    'intel_deep' => 'Intel Profunda',

    // Negotiation chat
    'chat_title' => 'Negociación de contrato',
    'chat_agent_demand' => 'El agente de :player pide :wage/año por :years años.',
    'chat_agent_counter' => 'El agente de :player insiste en :wage/año por :years años.',
    'chat_counter_resume' => 'El agente de :player sigue pidiendo :wage/año por :years años.',
    'chat_agent_accepted' => ':player ha aceptado :wage/año por :years años. ¡Hecho!',
    'chat_agent_rejected' => 'El agente de :player ha abandonado la mesa. La negociación ha fracasado.',
    'chat_accept' => 'Aceptar',
    'chat_reject' => 'Rechazar',
    'chat_user_accepts' => '¡Trato!',
    'chat_user_rejects' => 'No está en venta',
    'chat_deal_agreed' => 'Fichaje acordado',
    'chat_club_agreement' => 'Acuerdo entre clubes',
    'chat_renewal_agreed' => 'Renovación acordada',
    'chat_deal_failed' => 'Negociación fallida',
    'chat_continue' => 'Continuar',
    'chat_round' => 'Ronda :current/:max',
    'year_singular' => 'año',
    'year_plural' => 'años',
    'chat_send_offer' => 'Enviar',

    // Transfer negotiation chat
    'chat_transfer_title' => 'Negociación de Traspaso',
    'chat_club_demand' => ':team pide :fee por :player.',
    'chat_club_counter' => ':team insiste en :fee.',
    'chat_club_counter_resume' => ':team sigue pidiendo :fee.',
    'chat_club_accepted' => '¡:team ha aceptado :fee por :player!',
    'chat_club_rejected' => ':team ha rechazado la oferta. Las negociaciones se han roto.',
    'chat_your_bid' => 'Tu oferta',
    'mood_willing_sell' => 'Dispuesto a vender',
    'mood_open_sell' => 'Abierto a ofertas',
    'mood_reluctant_sell' => 'Reticente a vender',
    'negotiate' => 'Negociar',
    'chat_terms_transition' => '¡Precio acordado! Ahora negocia las condiciones personales con el jugador.',
    'chat_player_demand_transfer' => 'El agente de :player pide :wage/año durante :years años.',
    'chat_player_counter_transfer' => 'El agente de :player insiste en :wage/año durante :years años.',
    'chat_transfer_complete' => '¡:player ha firmado! Bienvenido al equipo.',
    'chat_transfer_complete_pending' => '¡:player ha firmado! El jugador se incorporará al equipo en el próximo mercado de fichajes.',
    'chat_terms_rejected' => 'El agente de :player se ha marchado. El acuerdo se ha roto.',

    // Counter-offer negotiation (user selling)
    'counter_offer_title' => 'Contraoferta',
    'counter_must_be_higher' => 'Tu precio debe ser superior a la oferta actual.',
    'chat_buyer_opening' => ':team ha ofrecido :fee por :player. ¿Cuál es tu precio?',
    'chat_buyer_counter' => ':team sube su oferta a :fee.',
    'chat_buyer_counter_resume' => 'La última oferta de :team es :fee.',
    'chat_buyer_accepted' => '¡:team acepta tu precio de :fee por :player!',
    'chat_buyer_rejected' => ':team ha retirado su interés. La negociación ha fracasado.',
    'chat_buyer_deal_complete' => '¡Venta acordada! :player se unirá a :team por :fee.',
    'chat_offer_rejected' => 'Has rechazado la oferta por :player. El jugador no está en venta.',

    // Pre-contract negotiation chat
    'chat_pre_contract_title' => 'Negociación de Pre-Contrato',
    'chat_pre_contract_demand' => 'El agente de :player pide :wage/año durante :years años para firmar un pre-contrato.',
    'chat_pre_contract_counter' => 'El agente de :player insiste en :wage/año durante :years años.',
    'chat_pre_contract_accepted' => '¡:player ha aceptado un pre-contrato! Se unirá a tu club en verano.',
    'chat_pre_contract_rejected' => 'El agente de :player se ha marchado. No hay acuerdo de pre-contrato.',
    'chat_pre_contract_deal' => 'Pre-contrato acordado',
    'negotiate_pre_contract' => 'Pre-Contrato',

    // Loan negotiation chat
    'chat_loan_title' => 'Negociación de Cesión',
    'chat_loan_accepted' => ':team acepta la cesión de :player. Coste: :salary/año.',
    'chat_loan_completed' => '¡:player se ha unido en cesión hasta final de temporada!',
    'chat_loan_agreed' => 'La cesión de :player ha sido acordada. El traspaso se completará cuando abra la ventana de fichajes.',
    'chat_loan_rejected' => ':team ha rechazado la solicitud de cesión. Las negociaciones se han roto.',
    'chat_loan_rejected_key_player' => ':team ha rechazado la solicitud. :player es un jugador clave para ellos.',
    'chat_loan_rejected_reputation' => ':player no está interesado en unirse a tu club en calidad de cedido.',
    'chat_loan_rejected_player' => ':player no está interesado en unirse a tu club en calidad de cedido.',
    'chat_loan_confirm' => 'Confirmar cesión',
    'chat_loan_deal' => 'Cesión acordada',

    // Chat player info strip
    'chat_player_age' => 'Edad',
    'chat_player_salary' => 'Salario',
    'chat_player_value' => 'Valor',
    'chat_player_contract' => 'Contrato',

    'mood_willing_loan' => 'Dispuesto a ceder',
    'mood_open_loan' => 'Abierto a cesión',
    'mood_reluctant_loan' => 'Reticente a ceder',
];
