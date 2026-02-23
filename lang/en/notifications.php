<?php

return [
    // Inbox
    'inbox' => 'Notifications',
    'mark_all_read' => 'Mark all as read',
    'all_caught_up' => 'You\'re all caught up',

    // Injury types
    'injury_muscle_fatigue' => 'muscle fatigue',
    'injury_muscle_strain' => 'muscle strain',
    'injury_calf_strain' => 'calf strain',
    'injury_ankle_sprain' => 'ankle sprain',
    'injury_groin_strain' => 'groin strain',
    'injury_hamstring_tear' => 'hamstring tear',
    'injury_knee_contusion' => 'knee contusion',
    'injury_metatarsal_fracture' => 'metatarsal fracture',
    'injury_acl_tear' => 'ACL tear',
    'injury_achilles_rupture' => 'Achilles tendon rupture',

    // Player injuries
    'player_injured_title' => ':player injured',
    'player_injured_message' => ':player has :injury and will be out for :weeks weeks.',

    // Player suspensions
    'player_suspended_title' => ':player suspended',
    'player_suspended_message' => ':player has been suspended for :matches matches due to :reason.',
    'reason_red_card' => 'a red card',
    'reason_yellow_accumulation' => 'yellow card accumulation',

    // Player recovery
    'player_recovered_title' => ':player recovered',
    'player_recovered_message' => ':player has recovered and is available for selection.',

    // Transfer offers
    'transfer_offer_title' => 'Offer :team_de',
    'transfer_offer_message' => ':team has offered :fee for :player.',
    'free_transfer' => 'Free Transfer',

    // Transfer complete
    'transfer_complete_incoming_title' => ':player signed',
    'transfer_complete_incoming_message' => ':player has joined your squad :team_de for :fee.',
    'transfer_complete_outgoing_title' => ':player sold',
    'transfer_complete_outgoing_message' => ':player has been transferred :team_a for :fee.',

    // Expiring offers
    'offer_expiring_title' => 'Offer for :player expiring soon',
    'offer_expiring_message' => 'The offer :team_de for :player expires in :days days.',

    // Scout
    'scout_complete_title' => 'Scout Report Ready',
    'scout_complete_message' => 'Your scout has found :count players matching your search.',

    // Contracts
    'contract_expiring_title' => ':player\'s contract expiring soon',
    'contract_expiring_message' => ':player\'s contract expires in :months months.',

    // Loan returns
    'loan_return_title' => ':player returns from loan',
    'loan_return_message' => ':player has returned from loan :team_en.',

    // Low fitness
    'low_fitness_title' => ':player has low fitness',
    'low_fitness_message' => ':player has only :fitness% fitness and needs rest.',

    // Loan search
    'loan_destination_found_title' => 'Destination found for :player',
    'loan_destination_found_message' => ':player has been loaned :team_a.',
    'loan_destination_found_waiting' => ':player will be loaned :team_a when the transfer window opens.',
    'loan_search_failed_title' => 'Loan search failed',
    'loan_search_failed_message' => 'No club was interested in loaning :player. The player is available again.',

    // Competition advancement
    'competition_advancement_title' => ':competition qualification',
    'competition_advancement_message' => ':stage',
    'competition_elimination_title' => ':competition elimination',
    'competition_elimination_message' => ':stage',

    // Academy
    'academy_batch_title' => 'New academy prospects',
    'academy_batch_message' => ':count new players have arrived at the academy.',
    'academy_evaluation_title' => 'Academy evaluation',
    'academy_evaluation_message' => 'It\'s time to evaluate your academy players.',

    // Transfer bid results
    'bid_accepted_title' => 'Bid for :player accepted',
    'bid_accepted' => ':team have accepted your bid for :player — transfer agreed for :fee.',
    'bid_counter_offer_title' => 'Counter offer for :player',
    'bid_counter_offer' => ':team want :asking for :player (you offered :offered).',
    'bid_rejected_title' => 'Bid for :player rejected',
    'bid_rejected' => ':team have rejected your bid for :player.',

    // Loan request results
    'loan_accepted_title' => 'Loan request for :player accepted',
    'loan_accepted' => ':team have accepted your loan request for :player.',
    'loan_rejected_title' => 'Loan request for :player rejected',
    'loan_rejected' => ':team have rejected your loan request for :player.',

    // Tournament welcome
    'tournament_welcome_title' => 'Welcome to the World Cup!',
    'tournament_welcome_message' => 'The entire nation has their eyes on you. No pressure... but don\'t let them down!',

    // Priority badges
    'priority_urgent' => 'Urgent',
    'priority_attention' => 'Attention',

    // Renewal negotiations
    'renewal_accepted_title' => ':player accepts renewal',
    'renewal_accepted_message' => ':player has accepted the renewal for :wage/yr over :years years.',
    'renewal_countered_title' => ':player counter offer',
    'renewal_countered_message' => ':player is asking for :wage/yr over :years years to renew.',
    'renewal_rejected_title' => ':player rejects renewal',
    'renewal_rejected_message' => ':player has rejected your renewal offer. They will leave at the end of the season.',
];
