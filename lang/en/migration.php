<?php

return [
    // Banner (export side)
    'banner_label' => 'New server',
    'banner_body' => 'VirtuaFC has a new server. Your data and games will be copied automatically and you can keep playing as always.',
    'banner_cta' => 'Move me to the new server',

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
    'import_failed' => 'Something went wrong. Please try again.',
    'import_retry' => 'Retry',
    'import_already_completed' => 'You have already migrated. Continue to your dashboard.',
    'import_already_in_progress' => 'A migration is already running for your account.',
    'import_no_data_found' => 'We could not find any data to migrate for your account.',

    // Export side — user has migrated and tries to use beta
    'completed_title' => 'Migration completed',
    'completed_body' => 'Your account and games now live at :url. This server is read-only for migrated users.',
    'completed_cta' => 'Continue to the new server',
];
