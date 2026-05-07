<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Migration mode
    |--------------------------------------------------------------------------
    |
    | Per-user opt-in migration flow from the legacy beta deployment to the new
    | production deployment. The same image runs on both sides; the role is
    | selected with this flag.
    |
    |   off     — feature disabled (default; banner hidden, routes 404)
    |   export  — legacy beta side. Shows banner; exposes export/seal API.
    |   import  — new prod side. Accepts the signed handoff and runs the import.
    |
    | See plan: i-am-thinking-what-starry-lightning.md
    |
    */

    'mode' => env('MIGRATION_MODE', 'off'),

    /*
    |--------------------------------------------------------------------------
    | Handoff secret
    |--------------------------------------------------------------------------
    |
    | Shared HMAC secret used to sign handoff tokens (user-facing redirect) and
    | server-to-server bearer tokens (export/seal API). Must be identical on
    | both export and import deployments. Generate with:
    |
    |   php -r 'echo bin2hex(random_bytes(32))."\n";'
    |
    */

    'handoff_secret' => env('MIGRATION_HANDOFF_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Handoff token TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Lifetime of the user-facing handoff token. Short by design — the user
    | clicks the banner and is redirected immediately; anything longer than a
    | minute is wasted attack surface.
    |
    */

    'handoff_ttl' => 60,

    /*
    |--------------------------------------------------------------------------
    | Server-to-server token TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Lifetime of bearer tokens used by the import side to call the export
    | side's API. Slightly longer than handoff to absorb queue scheduling
    | latency, but still tight.
    |
    */

    's2s_ttl' => 300,

    /*
    |--------------------------------------------------------------------------
    | Peer URL (import side only)
    |--------------------------------------------------------------------------
    |
    | Base URL of the export-side deployment. The import job calls this to
    | pull the user's data. Example: https://beta.virtuafc.example
    |
    */

    'peer_url' => env('MIGRATION_PEER_URL'),

    /*
    |--------------------------------------------------------------------------
    | Marketing destination (export side only)
    |--------------------------------------------------------------------------
    |
    | Public URL of the import-side deployment. The handoff redirect points
    | here. Example: https://app.virtuafc.example
    |
    */

    'destination_url' => env('MIGRATION_DESTINATION_URL'),

    /*
    |--------------------------------------------------------------------------
    | Test user allow-list
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of user IDs. When set, the forced-migration
    | lockout and the start action only fire for these users — everyone
    | else sees beta as usual. Use this for a production smoke test before
    | flipping MIGRATION_MODE on for real. Empty/unset → no restriction.
    |
    */

    'test_user_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('MIGRATION_TEST_USER_IDS', ''))
    ))),

];
