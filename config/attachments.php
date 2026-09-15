<?php

return [
    'disk' => env('ATTACHMENTS_DISK', 'local'),
    'limits' => [
        'payment_evidence' => 8 * 1024 * 1024,
        'product_image' => 8 * 1024 * 1024,
        'import_source' => 25 * 1024 * 1024,
        'import_error_export' => 25 * 1024 * 1024,
        'party_evidence' => 12 * 1024 * 1024,
        'asset_condition' => 12 * 1024 * 1024,
        'approval_evidence' => 12 * 1024 * 1024,
        'generated_document' => 30 * 1024 * 1024,
        // No upload workflow is approved for system support artifacts yet.
    ],
    'count_limits' => [
        'payment_evidence' => 3,
        'product_image' => 5,
        'import_source' => 1,
        'import_error_export' => 1,
        'party_evidence' => 10,
        'asset_condition' => 12,
        'approval_evidence' => 5,
        'generated_document' => 100,
    ],
    'allowed_mimes' => [
        'payment_evidence' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'product_image' => ['image/jpeg', 'image/png', 'image/webp'],
        'import_source' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
        'import_error_export' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'party_evidence' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'asset_condition' => ['image/jpeg', 'image/png', 'image/webp'],
        'approval_evidence' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        'generated_document' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ],
    'inline_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
];
