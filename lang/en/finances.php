<?php

return [

    // Overview cards
    'squad_value' => 'Squad Value',
    'annual_wage_bill' => 'Annual Wage Bill',
    'transfer_budget' => 'Transfer Budget',

    // Projected revenue
    'projected_revenue' => 'Projected Revenue',
    'tv_rights' => 'TV Rights',
    'matchday' => 'Matchday',
    'commercial' => 'Commercial',
    'solidarity_funds' => 'FA/UEFA Solidarity Funds',
    'public_subsidy' => 'Public Subsidies',
    'total_revenue' => 'Total Revenue',

    // Surplus calculation
    'projected_wages' => 'Projected Wages',
    'projected_surplus' => 'Projected Surplus',
    'operating_expenses' => 'Operating Expenses',
    'taxes' => 'Taxes & Social Charges',
    'carried_debt' => 'Carried Debt',
    'carried_surplus' => 'Carried Surplus',
    'available_surplus' => 'Available Surplus',

    // Season results
    'actual_revenue' => 'Actual Revenue',
    'actual_surplus' => 'Actual Surplus',
    'variance' => 'Variance',

    // No data
    'no_financial_data' => 'No financial data available for this season.',

    // Infrastructure investment
    'infrastructure_investment' => 'Infrastructure Investment',
    'adjust_allocation' => 'Adjust Allocation',

    // Tiers
    'youth_academy' => 'Youth Academy',

    'medical' => 'Medical',
    'medical_tier_0' => 'No medical staff',
    'medical_tier_1' => 'Basic care - standard recovery',
    'medical_tier_2' => 'Good facilities - 15% faster',
    'medical_tier_3' => 'Elite staff - 30% faster, fewer injuries',
    'medical_tier_4' => 'World class - 50% faster, prevention',

    'scouting' => 'Scouting',
    'scouting_tier_0' => 'No scouting network',
    'scouting_tier_1' => 'Basic network - domestic market only',
    'scouting_tier_2' => 'Expanded network - domestic, more results and accuracy',
    'scouting_tier_3' => 'International reach - fast and accurate searches',
    'scouting_tier_4' => 'Global network - maximum speed, results and accuracy',

    'facilities' => 'Facilities',
    'facilities_tier_0' => 'No investment - base matchday revenue',
    'facilities_tier_1' => 'Basic upgrades - 1.0x revenue',
    'facilities_tier_2' => 'Modern facilities - 1.15x revenue',
    'facilities_tier_3' => 'Premium experience - 1.35x revenue',
    'facilities_tier_4' => 'World-class stadium - 1.6x revenue',

    // Budget flow tooltips
    'tooltip_tv_rights' => 'TV revenue distribution based on your final league position. The higher you finish, the larger your share.',
    'tooltip_commercial' => 'Sponsorship and merchandising income. Depends on your stadium capacity and club reputation.',
    'tooltip_matchday' => 'Ticket sales revenue. Improves with facilities investment and a good league position.',
    'tooltip_solidarity_funds' => 'FA/UEFA solidarity funds for lower-division clubs to promote competitiveness.',
    'tooltip_public_subsidy' => 'Public subsidy guaranteeing a minimum viable budget for infrastructure and transfers.',
    'tooltip_wages' => 'Sum of all annual squad wages. Mid-season signings are pro-rated.',
    'tooltip_operating_expenses' => 'Fixed club costs: non-sporting staff, administration, travel, insurance and legal expenses.',
    'tooltip_taxes' => 'Taxes and social charges on club revenue.',
    'tooltip_surplus' => 'Difference between revenue and expenses. This amount is split between infrastructure and transfers.',
    'tooltip_carried_debt' => 'Deficit from last season. If actual revenue was lower than projected, the difference carries over.',
    'tooltip_carried_surplus' => 'Surplus from last season. If actual revenue exceeded projections, the difference carries over.',
    'tooltip_infrastructure' => 'Investment in academy, medical, scouting and facilities. Deducted before calculating transfer budget.',
    'tooltip_transfer_budget' => 'What remains of the surplus after covering debt and infrastructure. This is your capacity to sign players.',

    // Budget flow
    'budget_flow' => 'Budget Flow',
    'budget_not_set' => 'Season budget not configured',
    'surplus_to_allocate' => 'available surplus to allocate',

    // Quick stats
    'wage_revenue_ratio' => 'Wage/Revenue Ratio',
    'income' => 'income',
    'expenses' => 'expenses',

    // Transaction filters
    'filter_all' => 'All',
    'filter_income' => 'Income',
    'filter_expenses' => 'Expenses',

    // Budget setup
    'setup_season_budget' => 'Set Up Season Budget',

    // Transaction history
    'transaction_history' => 'Transaction History',
    'date' => 'Date',
    'type' => 'Type',
    'description' => 'Description',
    'amount' => 'Amount',
    'no_transactions' => 'No transactions recorded yet.',
    'transactions_hint' => 'Transfers, wages and other financial activity will appear here.',
    'free' => 'Free',

    // Budget allocation page
    'budget_allocation' => 'Budget Allocation',
    'season_budget' => 'Season :season Budget',
    'tier' => 'Tier :level',
    'tier_n' => 'Tier',
    'confirm_budget_allocation' => 'Confirm Budget Allocation',
    'after_debt_deduction' => 'After :amount debt deduction',
    'includes_carried_surplus' => 'Includes :amount surplus from last season',

    // Budget allocation component
    'infrastructure' => 'Infrastructure:',
    'transfers' => 'Transfers:',
    'budget_locked' => 'Budget Locked',
    'budget_locked_desc' => 'Budget allocation is fixed for the season. Changes can be made at the start of next pre-season.',
    'remainder_after_infrastructure' => 'Remainder after infrastructure',
    'tier_minimum_warning' => 'All infrastructure areas must be at least Tier 1 to maintain professional status.',

    // Youth academy tier descriptions
    'youth_academy_tier_0' => 'No youth development programme',
    'youth_academy_tier_1' => 'Basic academy - occasional prospects',
    'youth_academy_tier_2' => 'Good academy - regular youth pipeline',
    'youth_academy_tier_3' => 'Elite academy - high-potential youngsters',
    'youth_academy_tier_4' => 'World class - homegrown stars',

    // Medical tier descriptions
    'medical_tier_0' => 'No medical staff',
    'medical_tier_1' => 'Basic care - standard recovery',
    'medical_tier_2' => 'Good facilities - 15% faster',
    'medical_tier_3' => 'Elite staff - 30% faster, fewer injuries',
    'medical_tier_4' => 'World class - 50% faster, prevention',

    // Scouting tier descriptions
    'scouting_tier_0' => 'No scouting network',
    'scouting_tier_1' => 'Basic network - domestic market only',
    'scouting_tier_2' => 'Expanded network - domestic, more results and accuracy',
    'scouting_tier_3' => 'International reach - fast and accurate searches',
    'scouting_tier_4' => 'Global network - maximum speed, results and accuracy',

    // Facilities tier descriptions
    'facilities_tier_0' => 'No investment - base matchday revenue',
    'facilities_tier_1' => 'Basic upgrades - 1.0x revenue',
    'facilities_tier_2' => 'Modern facilities - 1.15x revenue',
    'facilities_tier_3' => 'Premium experience - 1.35x revenue',
    'facilities_tier_4' => 'World-class stadium - 1.6x revenue',

    // Categories
    'category_transfer_in' => 'Sale',
    'category_transfer_out' => 'Signing',
    'category_wage' => 'Wages',
    'category_tv' => 'TV Rights',
    'category_cup_bonus' => 'Cup Bonus',
    'category_performance_bonus' => 'Performance Bonus',
    'category_signing_bonus' => 'Signing Bonus',

    // Transaction descriptions
    'tx_free_transfer_out' => ':player left on free transfer to :team',
    'tx_player_sold' => ':player sold to :team',
    'tx_player_signed' => ':player signed from :team',
    'tx_cup_advancement' => ':competition - Round :round advancement',
];
