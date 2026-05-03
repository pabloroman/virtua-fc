<?php

/**
 * Database plane registry.
 *
 * Maps tables to logical planes so the runtime cross-plane query guard can
 * detect queries that span both planes. See CLAUDE.md → "Control plane /
 * tenant plane" for the boundary rules.
 *
 * Tenant plane is implicit: any table not listed in `control` is tenant.
 *
 * When adding a new table, classify it here at the same time. If you forget,
 * the runtime guard treats it as tenant by default.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Control plane tables
    |--------------------------------------------------------------------------
    |
    | Cross-tenant data: identity, leaderboards, reference data, onboarding.
    | Models for these tables declare `protected $connection = 'pgsql_control'`.
    |
    */
    'control' => [
        // Identity & auth
        'users',
        'password_reset_tokens',
        'sessions',
        'device_sessions',

        // Onboarding
        'waitlist',
        'invite_codes',
        'activation_events',

        // Leaderboards / cross-tenant aggregates
        'manager_stats',
        'tournament_summaries',

        // Reference data
        'teams',
        'competitions',
        'competition_teams',
        'players',
        'team_reputations',
        'club_profiles',
    ],

    /*
    |--------------------------------------------------------------------------
    | Connections
    |--------------------------------------------------------------------------
    |
    | Maps each plane to the connection name registered in config/database.php.
    |
    */
    'connections' => [
        'control' => 'pgsql_control',
        'tenant'  => 'pgsql',
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime guard
    |--------------------------------------------------------------------------
    |
    | When enabled (non-production only), every executed query is inspected
    | to verify the tables it touches all sit on the same plane as the
    | connection it ran on. Disabled until models are annotated with
    | `protected $connection = 'pgsql_control'` (Phase 1B); enabling earlier
    | would false-positive every query that touches a control-plane table
    | on the default connection.
    |
    */
    'guard_enabled' => env('DATABASE_PLANES_GUARD_ENABLED', false),

];
