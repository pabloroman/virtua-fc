<?php

return [
    // Handoff errors
    'handoff_invalid' => 'This migration link is invalid or has expired. Please go back and try again.',
    'handoff_user_missing' => 'We could not find your account on the new server. Please contact support.',

    // Import page (import side)
    'import_title' => 'Complete your migration',
    'import_intro' => 'Click the button to copy your manager career and all your games to the new server.',
    'import_cta' => 'Copy my data',
    'import_in_progress' => 'Copying your data…',
    'import_progress_label' => ':percent% — :step',
    'import_step_starting' => 'Starting',
    'import_step_user' => 'Profile',
    'import_step_stats' => 'Manager stats',
    'import_step_trophies' => 'Trophies',
    'import_step_games' => 'Games (:current of :total)',
    'import_step_finalizing' => 'Finalizing',
    'import_completed' => 'All done! Welcome aboard.',
    'import_failed' => 'Something went wrong with the migration.',
    'import_failed_body' => 'An automatic alert has been sent and this will be looked into. Your data is safe on the old server and nothing has been lost — you\'ll be able to finish the migration once it\'s sorted.',
    'import_already_completed' => 'You have already migrated. Continue to your dashboard.',
    'import_already_in_progress' => 'A migration is already running for your account.',
    'import_no_data_found' => 'We could not find any data to migrate for your account.',
    'import_skip_link' => 'Or start fresh without copying',
    'import_skip_modal_title' => 'Start fresh?',
    'import_skip_modal_body' => 'Your old career and games will stay on the legacy server but you won\'t be able to access them anymore. You will start with a clean slate here. This cannot be undone.',
    'import_skip_modal_confirm' => 'Yes, start fresh',
    'import_skip_modal_cancel' => 'Cancel',
    'import_skip_not_allowed' => 'This account can\'t skip the migration in its current state.',

    // Export side — user has migrated and tries to use beta
    'completed_title' => 'Migration completed',
    'completed_body' => 'Your account and games now live at :url. This server is read-only for migrated users.',
    'completed_cta' => 'Continue to the new server',

    // Export side — pending user is forced to migrate before they can keep playing
    'required_title' => 'Time to move',
    'required_body' => 'VirtuaFC has a new server. To keep playing you need to move your account and games over — it only takes a moment and nothing is lost.',
    'required_cta' => 'Move me to the new server',
];
