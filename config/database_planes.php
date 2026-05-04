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
        'club_profiles',
    ],

];
