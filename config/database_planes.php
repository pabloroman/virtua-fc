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
    | Currently OFF by default: a previous attempt to fully refactor every
    | cross-plane query (see PLANES-SEAM comments in the codebase) caused
    | OOM/timeout regressions because two-step queries were materially
    | slower than the JOINs/correlated subqueries they replaced. Until each
    | seam is rewritten in a way that doesn't regress performance, the
    | guard stays opt-in. Flip it on locally when working on a seam, then
    | flip it back off.
    |
    */
    'guard_enabled' => env('DATABASE_PLANES_GUARD_ENABLED', false),

];
