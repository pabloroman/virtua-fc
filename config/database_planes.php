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
        'manager_trophies',
        'tournament_summaries',

        // Reference data
        'teams',
        'competitions',
        'competition_teams',
        'players',
        'club_profiles',
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime guard
    |--------------------------------------------------------------------------
    |
    | When enabled (non-production only), every executed query is inspected
    | to verify the tables it touches all sit on the same plane as the
    | connection it ran on.
    |
    | Off by default for runtime overhead, not because of known violations:
    | every PLANES-SEAM that previously kept the guard opt-in has been
    | resolved (cold-path seams via two-step queries, hot-path seams via
    | denormalization or cursor-streamed bulk insert). Flip the env var on
    | locally when developing a feature that spans both planes to make sure
    | your code stays single-plane.
    |
    */
    'guard_enabled' => env('DATABASE_PLANES_GUARD_ENABLED', false),

];
