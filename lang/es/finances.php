<?php

return [
    // Page title
    'title' => 'Finanzas :team_de - Temporada :season',

    // Overview cards
    'projected_position' => 'Posición Proyectada',
    'squad_value' => 'Valor de la Plantilla',
    'annual_wage_bill' => 'Masa Salarial Anual',
    'transfer_budget' => 'Presupuesto de Fichajes',

    // Projected revenue
    'projected_revenue' => 'Ingresos Proyectados',
    'tv_rights' => 'Derechos de TV',
    'matchday' => 'Día de Partido',
    'commercial' => 'Comercial',
    'solidarity_funds' => 'Ayudas RFEF/UEFA',
    'public_subsidy' => 'Subvenciones Públicas',
    'total_revenue' => 'Ingresos Totales',

    // Surplus calculation
    'surplus_calculation' => 'Cálculo del Superávit',
    'projected_wages' => 'Salarios Proyectados',
    'projected_surplus' => 'Superávit Proyectado',
    'operating_expenses' => 'Gastos Operativos',
    'taxes' => 'Impuestos y Cargas Sociales',
    'carried_debt' => 'Deuda Arrastrada',
    'available_surplus' => 'Superávit Disponible',

    // Season results
    'season_results' => 'Resultados de la Temporada',
    'actual_revenue' => 'Ingresos Reales',
    'actual_surplus' => 'Superávit Real',
    'variance' => 'Variación',

    // No data
    'no_financial_data' => 'No hay datos financieros disponibles para esta temporada.',

    // Infrastructure investment
    'infrastructure_investment' => 'Inversión en Infraestructura',
    'adjust_allocation' => 'Ajustar Asignación',

    // Tiers
    'youth_academy' => 'Cantera',
    'youth_tier_0' => 'Sin programa de desarrollo juvenil',
    'youth_tier_1' => 'Academia básica - promesas ocasionales',
    'youth_tier_2' => 'Buena academia - cantera regular',
    'youth_tier_3' => 'Academia de élite - jóvenes de alto potencial',
    'youth_tier_4' => 'Clase mundial - estrellas de la casa',

    'medical' => 'Médico',
    'medical_tier_0' => 'Sin personal médico',
    'medical_tier_1' => 'Atención básica - recuperación estándar',
    'medical_tier_2' => 'Buenas instalaciones - 15% más rápido',
    'medical_tier_3' => 'Personal de élite - 30% más rápido, menos lesiones',
    'medical_tier_4' => 'Clase mundial - 50% más rápido, prevención',

    'scouting' => 'Ojeadores',
    'scouting_tier_0' => 'Sin red de ojeadores',
    'scouting_tier_1' => 'Red básica - solo mercado nacional',
    'scouting_tier_2' => 'Red ampliada - nacional, más resultados y precisión',
    'scouting_tier_3' => 'Alcance internacional - búsquedas rápidas y precisas',
    'scouting_tier_4' => 'Red global - máxima velocidad, resultados y precisión',

    'facilities' => 'Instalaciones',
    'facilities_tier_0' => 'Sin inversión - ingresos base de partido',
    'facilities_tier_1' => 'Mejoras básicas - 1.0x ingresos',
    'facilities_tier_2' => 'Instalaciones modernas - 1.15x ingresos',
    'facilities_tier_3' => 'Experiencia premium - 1.35x ingresos',
    'facilities_tier_4' => 'Estadio clase mundial - 1.6x ingresos',

    // Budget flow tooltips
    'tooltip_tv_rights' => 'Distribución televisiva basada en tu posición final en liga. Cuanto más alto termines, mayor será tu reparto.',
    'tooltip_commercial' => 'Ingresos por patrocinios y merchandising. Dependen del tamaño de tu estadio y la reputación del club.',
    'tooltip_matchday' => 'Recaudación por venta de entradas. Mejora con la inversión en instalaciones y una buena posición en liga.',
    'tooltip_solidarity_funds' => 'Ayudas de la RFEF/UEFA destinadas a clubes de divisiones inferiores para fomentar la competitividad.',
    'tooltip_public_subsidy' => 'Subvención pública que garantiza un presupuesto mínimo viable para infraestructura y fichajes.',
    'tooltip_wages' => 'Suma de los salarios anuales de toda la plantilla. Los fichajes a mitad de temporada se prorratean.',
    'tooltip_operating_expenses' => 'Costes fijos del club: personal no deportivo, administración, viajes, seguros y gastos legales.',
    'tooltip_taxes' => 'Impuestos y cargas sociales sobre los ingresos del club.',
    'tooltip_surplus' => 'Diferencia entre ingresos y gastos. Este importe se reparte entre infraestructura y fichajes.',
    'tooltip_carried_debt' => 'Déficit de la temporada anterior. Si los ingresos reales fueron menores a los proyectados, la diferencia se arrastra.',
    'tooltip_infrastructure' => 'Inversión en cantera, medicina deportiva, ojeadores e instalaciones. Se descuenta antes de calcular el presupuesto de fichajes.',
    'tooltip_transfer_budget' => 'Lo que queda del superávit tras cubrir deuda e infraestructura. Es tu capacidad para fichar jugadores.',

    // Budget flow
    'budget_flow' => 'Flujo de Presupuesto',
    'budget_not_set' => 'Presupuesto de temporada sin configurar',
    'surplus_to_allocate' => 'superávit disponible para asignar',

    // Quick stats
    'wage_revenue_ratio' => 'Ratio Salarios/Ingresos',
    'income' => 'ingresos',
    'expenses' => 'gastos',

    // Transaction filters
    'filter_all' => 'Todos',
    'filter_income' => 'Ingresos',
    'filter_expenses' => 'Gastos',

    // Budget setup
    'setup_season_budget' => 'Configurar Presupuesto de Temporada',
    'allocate_surplus' => 'Asigna tu superávit a infraestructura y fichajes.',

    // Transaction history
    'transaction_history' => 'Historial de Transacciones',
    'date' => 'Fecha',
    'type' => 'Tipo',
    'description' => 'Descripción',
    'amount' => 'Importe',
    'no_transactions' => 'Aún no hay transacciones registradas.',
    'transactions_hint' => 'Fichajes, salarios y otras actividades financieras aparecerán aquí.',
    'free' => 'Gratis',

    // Budget allocation page
    'budget_allocation' => 'Asignación de Presupuesto',
    'season_budget' => 'Presupuesto de Temporada :season',
    'surplus_info' => 'Tienes :amount de superávit proyectado para invertir.',
    'remaining_unallocated' => 'Sin Asignar Restante',
    'allocation_exceeds' => 'La asignación supera el superávit disponible',
    'all_tiers_minimum' => 'Todas las áreas deben ser al menos Nivel 1',
    'tier' => 'Nivel :level',
    'tier_n' => 'Nivel',
    'save_allocation' => 'Guardar Asignación',
    'confirm_budget_allocation' => 'Confirmar Asignación de Presupuesto',
    'after_debt_deduction' => 'Después de :amount de deducción por deuda',

    // Budget allocation component
    'infrastructure' => 'Infraestructura:',
    'transfers' => 'Fichajes:',
    'budget_locked' => 'Presupuesto Bloqueado',
    'budget_locked_desc' => 'La asignación presupuestaria está fijada para la temporada. Se podrán realizar cambios en la próxima pretemporada.',
    'remainder_after_infrastructure' => 'Restante tras infraestructura',
    'tier_minimum_warning' => 'Todas las áreas de infraestructura deben ser al menos Nivel 1 para mantener el estatus profesional.',

    // Youth academy tier descriptions
    'youth_academy_tier_0' => 'Sin programa de desarrollo juvenil',
    'youth_academy_tier_1' => 'Academia básica - promesas ocasionales',
    'youth_academy_tier_2' => 'Buena academia - cantera regular',
    'youth_academy_tier_3' => 'Academia de élite - jóvenes de alto potencial',
    'youth_academy_tier_4' => 'Clase mundial - estrellas de la casa',

    // Medical tier descriptions
    'medical_tier_0' => 'Sin personal médico',
    'medical_tier_1' => 'Atención básica - recuperación estándar',
    'medical_tier_2' => 'Buenas instalaciones - 15% más rápido',
    'medical_tier_3' => 'Personal de élite - 30% más rápido, menos lesiones',
    'medical_tier_4' => 'Clase mundial - 50% más rápido, prevención',

    // Scouting tier descriptions
    'scouting_tier_0' => 'Sin red de ojeadores',
    'scouting_tier_1' => 'Red básica - solo mercado nacional',
    'scouting_tier_2' => 'Red ampliada - nacional, más resultados y precisión',
    'scouting_tier_3' => 'Alcance internacional - búsquedas rápidas y precisas',
    'scouting_tier_4' => 'Red global - máxima velocidad, resultados y precisión',

    // Facilities tier descriptions
    'facilities_tier_0' => 'Sin inversión - ingresos base de partido',
    'facilities_tier_1' => 'Mejoras básicas - 1.0x ingresos',
    'facilities_tier_2' => 'Instalaciones modernas - 1.15x ingresos',
    'facilities_tier_3' => 'Experiencia premium - 1.35x ingresos',
    'facilities_tier_4' => 'Estadio clase mundial - 1.6x ingresos',

    // Categories
    'category_transfer_in' => 'Fichaje',
    'category_transfer_out' => 'Venta',
    'category_wage' => 'Salarios',
    'category_tv' => 'Derechos TV',
    'category_matchday' => 'Día de Partido',
    'category_commercial' => 'Comercial',
    'category_cup_bonus' => 'Bonificación Copa',
    'category_performance_bonus' => 'Bonus de Rendimiento',
    'category_signing_bonus' => 'Prima de Fichaje',
    'category_infrastructure' => 'Infraestructura',

    // Transaction descriptions
    'tx_free_transfer_out' => ':player se fue libre a :team',
    'tx_player_sold' => ':player vendido a :team',
    'tx_player_signed' => ':player fichado de :team',
    'tx_cup_advancement' => ':competition - Ronda :round superada',
];
