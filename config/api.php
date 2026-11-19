<?php

return [

    /*
    | The personal-token API (/api/v1) that lets an AI agent read and add PastorEyes data.
    | See doc/api.md.
    */

    // Reject plain-HTTP requests (tokens would cross the network in the clear). On by default in production.
    'require_https' => env('API_REQUIRE_HTTPS', env('APP_ENV') === 'production'),

    // Requests per minute, per token
    'rate_limit' => [
        'read'  => 120,
        'write' => 30,
    ],

    // Default token lifetime offered when one is created (days)
    'token_days' => 90,

    // How long an Idempotency-Key is remembered, and how long a delete confirmation lasts
    'idempotency_hours'   => 24,
    'confirmation_minutes' => 5,

    // Text search over entries has to decrypt each one, so it stops after this many
    'text_search_scan_cap' => 2000,

];
