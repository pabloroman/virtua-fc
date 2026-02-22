<?php

return [
    // Page title
    'squad' => 'Squad',
    'development' => 'Development',
    'stats' => 'Stats',

    // Position groups
    'goalkeepers' => 'Goalkeepers',
    'defenders' => 'Defenders',
    'midfielders' => 'Midfielders',
    'forwards' => 'Forwards',
    'goalkeepers_short' => 'GK',
    'defenders_short' => 'DEF',
    'midfielders_short' => 'MID',
    'forwards_short' => 'FWD',

    // Columns
    'technical' => 'TEC',
    'physical' => 'PHY',
    'technical_abbr' => 'TEC',
    'physical_abbr' => 'PHY',
    'years_abbr' => 'yrs',
    'fitness' => 'FIT',
    'morale' => 'MOR',
    'overall' => 'OVR',

    // Status labels
    'on_loan' => 'On Loan',
    'leaving_free' => 'Leaving (Free)',
    'renewed' => 'Renewed',
    'sale_agreed' => 'Sale Agreed',
    'retiring' => 'Retiring',
    'listed' => 'Listed',
    'list_for_sale' => 'List for Sale',
    'unlist_from_sale' => 'Remove from Sale',
    'loan_out' => 'Loan Out',
    'loan_searching' => 'Searching for loan destination',

    // Summary
    'wage_bill' => 'Wage Bill',
    'per_year' => '/yr',
    'avg_fitness' => 'Avg Fitness',
    'avg_morale' => 'Avg Morale',
    'low' => 'low',

    // Contract management
    'free_transfer' => 'Free',
    'let_go' => 'Let Go',
    'pre_contract_signed' => 'Pre-contract signed',
    'new_wage_from_next' => 'New wage from next season',
    'has_pre_contract_offers' => 'Has pre-contract offers!',
    'renew' => 'Renew',
    'expires_in_days' => 'Expires in :days days',

    // Lineup validation
    'formation_position_mismatch' => 'Formation :formation requires :required :position, but you selected :actual.',
    'player_not_available' => 'One or more selected players are not available.',

    // Lineup
    'formation' => 'Formation',
    'mentality' => 'Mentality',
    'auto_select' => 'Auto Select',
    'opponent' => 'Opponent',
    'need' => 'need',

    // Compatibility
    'natural' => 'Natural',
    'very_good' => 'Very Good',
    'good' => 'Good',
    'okay' => 'Okay',
    'poor' => 'Poor',
    'unsuitable' => 'Unsuitable',

    // Lineup editor
    'pitch' => 'Pitch',
    'select_player_for_slot' => 'Select a player for this position',

    // Opponent scout
    'team_rating' => 'Team Rating',
    'top_scorer' => 'Top scorer',
    'injured' => 'injured',
    'suspended' => 'suspended',

    // Unavailability reasons
    'suspended_matches' => 'Suspended (:count match)|Suspended (:count matches)',
    'injured_generic' => 'Injured',
    'injury_weeks' => ':count week|:count weeks',

    // Injury types
    'injury_muscle_fatigue' => 'Muscle fatigue',
    'injury_muscle_strain' => 'Muscle strain',
    'injury_calf_strain' => 'Calf strain',
    'injury_ankle_sprain' => 'Ankle sprain',
    'injury_groin_strain' => 'Groin strain',
    'injury_hamstring_tear' => 'Hamstring tear',
    'injury_knee_contusion' => 'Knee contusion',
    'injury_metatarsal_fracture' => 'Metatarsal fracture',
    'injury_acl_tear' => 'ACL tear',
    'injury_achilles_rupture' => 'Achilles tendon rupture',

    // Development page
    'ability' => 'Ability',
    'playing_time' => 'Minutes',
    'high_potential' => 'High Potential',
    'growing' => 'Growing',
    'declining' => 'Declining',
    'peak' => 'Peak',
    'all' => 'All',
    'no_players_match_filter' => 'No players match the selected filter.',
    'pot' => 'POT',
    'apps' => 'Apps',
    'projection' => 'Projection',
    'potential' => 'Potential',
    'potential_range' => 'Potential Range',
    'starter_bonus' => 'starter bonus',
    'needs_appearances' => 'Needs :count+ appearances for starter bonus',
    'qualifies_starter_bonus' => 'Qualifies for starter bonus (+50% development)',

    // Stats page
    'goals' => 'G',
    'assists' => 'A',
    'goal_contributions' => 'G+A',
    'goals_per_game' => 'G/App',
    'own_goals' => 'OG',
    'yellow_cards' => 'YC',
    'red_cards' => 'RC',
    'clean_sheets' => 'CS',
    'appearances' => 'Appearances',
    'bookings' => 'Bookings',
    'click_to_sort' => 'Click column headers to sort',

    // Stats highlights
    'top_in_squad' => 'Top in squad',

    // Legend labels
    'legend_apps' => 'Appearances',
    'legend_goals' => 'Goals',
    'legend_assists' => 'Assists',
    'legend_contributions' => 'Goal Contributions',
    'legend_own_goals' => 'Own Goals',
    'legend_clean_sheets' => 'Clean Sheets (GK only)',

    // Player detail modal
    'abilities' => 'Abilities',
    'technical_full' => 'Technical',
    'physical_full' => 'Physical',
    'fitness_full' => 'Fitness',
    'morale_full' => 'Morale',
    'season_stats' => 'Season Stats',
    'clean_sheets_full' => 'Clean Sheets',
    'goals_conceded_full' => 'Goals Conceded',
    'discovered' => 'Discovered',

    // Academy
    'academy' => 'Academy',
    'promote_to_first_team' => 'Promote to First Team',
    'academy_tier' => 'Academy Tier',
    'no_academy_prospects' => 'No academy prospects available.',
    'academy_explanation' => 'New academy prospects arrive at the start of each season based on your academy investment.',
    'academy_evaluation' => 'Academy Evaluation',
    'academy_capacity' => 'Places',
    'academy_keep' => 'Keep',
    'academy_keep_desc' => 'Player stays in the academy and develops next season.',
    'academy_dismiss' => 'Dismiss',
    'academy_dismiss_confirm' => 'Are you sure? The player will be permanently dismissed.',
    'academy_dismiss_desc' => 'Player is permanently dismissed from the club.',
    'academy_loan_out' => 'Loan Out',
    'academy_loan_desc' => 'Player goes on loan with accelerated development (1.5x) and returns at end of season.',
    'academy_promote' => 'Promote',
    'academy_promote_desc' => 'Player joins the first team with a professional contract.',
    'academy_must_decide' => 'Decision required',
    'academy_over_capacity' => 'The academy is full. You must free up places.',
    'academy_returning_loans' => ':count player returning from loan|:count players returning from loan',
    'academy_incoming' => ':min-:max new academy prospects expected',
    'academy_on_loan' => 'On Loan',
    'academy_seasons' => ':count season|:count seasons',
    'academy_phase_unknown' => 'Abilities unknown',
    'academy_phase_glimpse' => 'Abilities visible',
    'academy_phase_verdict' => 'Potential revealed',

    // Academy help text
    'academy_help_toggle' => 'How does the academy work?',
    'academy_help_development' => 'Academy players improve progressively throughout the season. Their abilities are initially unknown and are revealed at two key moments.',
    'academy_help_phases_title' => 'Ability reveal',
    'academy_help_phase_0' => 'Start of season: only identity visible (name, position, age). Decide by instinct.',
    'academy_help_phase_1' => 'First half of season: technical and physical abilities are revealed.',
    'academy_help_phase_2' => 'Winter window: potential range is revealed. The moment of truth!',
    'academy_help_evaluations_title' => 'Mandatory evaluation',
    'academy_help_evaluation_desc' => 'At the end of the season you must decide what to do with each academy player:',
    'academy_help_keep' => 'Keep - stays in the academy and continues developing',
    'academy_help_promote' => 'Promote - joins the first team with a professional contract',
    'academy_help_loan' => 'Loan - develops 1.5x faster on loan and returns at end of season',
    'academy_help_dismiss' => 'Dismiss - leaves the club permanently',
    'academy_help_age_rule' => 'Players aged 21 or older cannot stay in the academy: they must be promoted or dismissed.',
    'academy_help_capacity_rule' => 'If you exceed capacity, you must free up places before you can continue the season.',

    'academy_tier_0' => 'No Academy',
    'academy_tier_1' => 'Basic Academy',
    'academy_tier_2' => 'Good Academy',
    'academy_tier_3' => 'Elite Academy',
    'academy_tier_4' => 'World-Class Academy',
    'academy_tier_unknown' => 'Unknown',

    // Lineup help text
    'lineup_help_toggle' => 'How does lineup selection work?',
    'lineup_help_intro' => 'Choose 11 players for each match. Your formation, player fitness, and positional compatibility all affect performance.',
    'lineup_help_formation_title' => 'Formation & Mentality',
    'lineup_help_formation_desc' => 'The formation determines which positions are available on the pitch. Players perform best in their natural position.',
    'lineup_help_compatibility_natural' => 'Natural — player is in their best position, full performance.',
    'lineup_help_compatibility_good' => 'Good / Very Good — slight penalty, but the player can perform well.',
    'lineup_help_compatibility_poor' => 'Poor / Unsuitable — significant penalty, avoid if possible.',
    'lineup_help_mentality_desc' => 'Mentality affects how attacking or defensive your team plays.',
    'lineup_help_condition_title' => 'Fitness & Morale',
    'lineup_help_condition_desc' => 'Players with low fitness or morale perform worse. Rotate your squad to keep everyone fresh.',
    'lineup_help_fitness' => 'Fitness drops after each match and recovers between matchdays. Injuries increase when fitness is low.',
    'lineup_help_morale' => 'Morale is affected by results, playing time, and contract status.',
    'lineup_help_auto' => 'Use "Auto Select" to let the system pick the best available XI for your formation.',

    // Squad selection (tournament onboarding)
    'squad_selection_title' => 'Select your squad',
    'squad_selection_subtitle' => 'Choose 26 players for the tournament',
    'confirm_squad' => 'Confirm squad',
    'squad_confirmed' => 'Squad confirmed!',
    'invalid_selection' => 'Invalid selection. Please check the selected players.',
];
