<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POS Offline Sync Configuration (Design §10.2, §11.2, POS-09, POS-10)
    |--------------------------------------------------------------------------
    */
    'current_client_version' => env('POS_CURRENT_CLIENT_VERSION', '1.2.0'),
    'min_client_version' => env('POS_MIN_CLIENT_VERSION', '1.1.0'),
    'clock_skew_threshold_seconds' => (int) env('POS_CLOCK_SKEW_THRESHOLD_SECONDS', 300),
    'max_concurrent_sync_batches' => (int) env('POS_MAX_CONCURRENT_SYNC_BATCHES', 10),
];
