<?php

return [
    /*
    |--------------------------------------------------------------------------
    | KC ERP Platform Control Plane Configuration
    |--------------------------------------------------------------------------
    */

    'audit' => [
        /*
        | Dedicated secret used exclusively to compute HMAC signatures for
        | platform audit log records. Completely independent of Laravel APP_KEY.
        */
        'hmac_secret' => env('PLATFORM_AUDIT_HMAC_SECRET'),

        /*
        | Active key version. Written into new platform_audit_logs.key_version records.
        | Key version 0 denotes legacy Phase 1 records signed with APP_KEY.
        | Key version 1+ denotes records signed with dedicated PLATFORM_AUDIT_HMAC_SECRET.
        */
        'key_version' => (int) env('PLATFORM_AUDIT_KEY_VERSION', 1),

        /*
        | Registry of historical signing keys used to verify audit records
        | created prior to a key rotation.
        | Format: [ (int) $version => (string) $secret ]
        */
        'historical_keys' => [
            // Example: 1 => env('PLATFORM_AUDIT_HMAC_SECRET_V1'),
        ],
    ],

    'pos_bridge' => [
        /*
        | Internal endpoint where the Go POS Fiscal & Hardware Bridge daemon listens.
        | Defaults to localhost:9191 /health.
        */
        'health_url' => env('POS_BRIDGE_HEALTH_URL', 'http://127.0.0.1:9191/health'),

        /*
        | Socket timeout for the Go bridge probe in seconds.
        */
        'timeout_seconds' => (float) env('POS_BRIDGE_TIMEOUT_SECONDS', 1.0),
    ],
];
