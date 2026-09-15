<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Initial production administrator
    |--------------------------------------------------------------------------
    |
    | These values are deployment secrets/inputs. The production seeder fails
    | closed when any value is missing and never supplies a default identity or
    | password. After the first successful login, rotate the password and enable
    | the approved multi-factor authentication controls.
    |
    */
    'admin' => [
        'name' => env('PRODUCTION_ADMIN_NAME'),
        'username' => env('PRODUCTION_ADMIN_USERNAME'),
        'email' => env('PRODUCTION_ADMIN_EMAIL'),
        'password' => env('PRODUCTION_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Owner-approved setup data
    |--------------------------------------------------------------------------
    |
    | Set this to an absolute path outside the public web root when genuine,
    | reviewed Production setup data is ready. No operational records are
    | invented when the value is empty. The optional SHA-256 value pins the
    | exact approved artifact and causes seeding to fail if it changes.
    |
    */
    'setup_data' => [
        'enabled' => filter_var(env('PRODUCTION_SETUP_SEEDING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'path' => env('PRODUCTION_SETUP_DATA_PATH'),
        'sha256' => env('PRODUCTION_SETUP_DATA_SHA256'),
        'user_passwords' => json_decode((string) env('PRODUCTION_SETUP_USER_PASSWORDS', '{}'), true) ?: [],
    ],
];
