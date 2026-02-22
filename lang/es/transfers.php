<?php

return [
    // Page title
    'window' => 'Ventana',
    'window_open' => 'Ventana de :window Abierta',
    'budget' => 'Presupuesto',

    // Tab labels
    'outgoing' => 'Salidas',
    'incoming' => 'Fichajes',
    'scouting_tab' => 'Ojeadores',

    // Window countdown
    'window_closes_in' => ':window cierra en :matchdays jornada(s)',
    'window_opens_in' => ':window abre en :matchdays jornada(s)',

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
    'counter_offers_received' => 'Contraoferta(s) Recibida(s)',
    'counter_offers_help' => 'Los clubes han respondido con un precio diferente al que ofreciste',

    // Legacy keys kept for compatibility
    'loans' => 'Cesiones',

    // Pending bids
    'your_pending_bids' => 'Tus Ofertas Pendientes',
    'pending_bids_help' => 'Ofertas enviadas que están esperando respuesta del club vendedor',
    'loan_request' => 'Solicitud de Cesión',
    'loan_no_fee' => 'Cesión (sin coste)',
    'free_transfer' => 'Libre (fin de contrato)',
    'response_next_matchday' => 'Respuesta en la próxima jornada',

    // Rejected bids
    'rejected_bids' => 'Ofertas Rechazadas',
    'bid_rejected' => 'Oferta rechazada',

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
    'accept_counter' => 'Aceptar Contraoferta',
    'already_bidding' => 'Ya tienes una oferta por este jugador',
    'scouting_assessment' => 'Evaluación del Ojeador',
    'financial_details' => 'Detalles Financieros',
    'estimated_asking_price' => 'Precio de Venta Estimado',
    'wage_demand' => 'Salario Solicitado',
    'your_transfer_budget' => 'Tu Presupuesto de Fichajes',
    'transfer_fee_exceeds_budget' => 'El precio de traspaso excede tu presupuesto. No puedes pujar por este jugador.',
    'asking_price' => 'Precio pedido',
    'request_loan' => 'Solicitar Cesión',

    // Bid evaluation responses
    'bid_accepted' => ':team ha aceptado tu oferta.',
    'counter_offer_made' => ':team ha hecho una contraoferta de :amount.',
    'bid_rejected_too_low' => ':team ha rechazado tu oferta. Estaba muy por debajo de su valoración.',
    'loan_rejected_key_player' => ':team rechazó la solicitud de cesión. :player es un jugador clave para ellos.',
    'loan_accepted' => ':team ha aceptado ceder a :player a tu club.',
    'loan_rejected_keep' => ':team ha decidido quedarse con :player por ahora.',

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

    // Counter-offer detail
    'your_bid_amount' => 'Tu oferta: :amount',
    'they_ask' => 'Piden: :amount',

    // Transfers help text
    'transfers_help_toggle' => '¿Cómo funcionan los traspasos?',
    'transfers_help_intro' => 'Gestiona tus traspasos salientes, contratos y cesiones. Las ofertas llegan cada jornada según las condiciones del mercado.',
    'transfers_help_selling_title' => 'Vender jugadores',
    'transfers_help_selling_list' => 'Pon jugadores en venta desde la página de Plantilla. Otros clubes harán ofertas basadas en el valor del jugador.',
    'transfers_help_selling_unsolicited' => 'También puedes recibir ofertas no solicitadas por jugadores no listados, especialmente los de alto valor.',
    'transfers_help_selling_accept' => 'Los traspasos aceptados se completan cuando se abre la siguiente ventana de fichajes.',
    'transfers_help_contracts_title' => 'Contratos y Renovaciones',
    'transfers_help_contracts_expiring' => 'Los jugadores en su último año de contrato pueden ser fichados gratis por otros clubes mediante precontrato a partir de enero.',
    'transfers_help_contracts_renew' => 'Renueva a los jugadores antes de que sea tarde. Las negociaciones duran varias jornadas — el jugador puede aceptar, contraofertar o rechazar.',
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

    // Decline renewal
    'reconsider_renewal' => 'Reconsiderar',
    'declined_renewals' => 'No renovados',

    // Renewal negotiation
    'renewal_counter_offers' => 'Contraoferta de Renovación',
    'renewal_counter_offers_help' => 'Estos jugadores han respondido a tu oferta de renovación',
    'renewal_offers_sent' => 'Renovaciones en Curso',
    'renewal_offers_sent_help' => 'Tus ofertas de renovación están siendo consideradas',
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
    'contract_duration' => 'Duración del contrato',
];
