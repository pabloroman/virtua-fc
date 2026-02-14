<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Beta Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, registration requires a valid invite code and a persistent
    | banner warns users that their data may be reset during development.
    | Set to false (or remove the env var) to open registration to everyone.
    |
    */

    'enabled' => (bool) env('BETA_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Feedback URL
    |--------------------------------------------------------------------------
    |
    | URL shown in the beta banner where testers can submit feedback.
    | Can be a Google Form, GitHub Issues URL, or any external link.
    |
    */

    'feedback_url' => env('BETA_FEEDBACK_URL', 'https://github.com/pabloroman/virtua-fc/issues'),

    /*
    |--------------------------------------------------------------------------
    | Daily Invites
    |--------------------------------------------------------------------------
    |
    | Number of waitlist entries to invite each day when the scheduler runs.
    |
    */

    'daily_invites' => (int) env('BETA_DAILY_INVITES', 20),

];
