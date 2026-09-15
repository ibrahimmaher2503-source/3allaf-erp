<?php

declare(strict_types=1);

return [
    // OFF-01: opt-in remains disabled until a Local/Dev operator enables it.
    'enabled' => env('OFFLINE_POS_ENABLED', false),
    'schema_version' => '1',
    'policy_version' => 'OFF-01..OFF-05-local-dev-v1',

    // Owner-configurable Local/Dev defaults. Production is always rejected in code.
    'limits' => [
        'max_duration_minutes' => 240, // OFF-02
        'max_transactions' => 50, // OFF-02
        'max_transaction_value' => '5000.00', // OFF-02
        'max_cumulative_value' => '25000.00', // OFF-02
        'max_price_cache_age_minutes' => 1440, // OFF-03
        'queue_expiry_minutes' => 4320, // OFF-04
    ],
    'payments' => [
        'cash' => true,
        'manual_electronic' => true,
    ],
    'review' => [
        'permission' => 'offline_queue_conflicts.approve', // OFF-05
        'requires_reason' => true,
    ],
];
