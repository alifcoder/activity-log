<?php

use App\Models\User;

/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 2:52 PM
 */

return [
    'enabled' => env('ACTIVITY_LOG_ENABLED', true),

    'db_connection' => env('ACTIVITY_LOG_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    'use_queue' => env('ACTIVITY_LOG_USE_QUEUE', true),
    'queue_name' => env('ACTIVITY_LOG_QUEUE_NAME', 'default'),

    'main_connection' => env('MAIN_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'models' => [
        'user' => User::class,
    ],
    'file_storage_disk' => env('ACTIVITY_LOG_FILE_STORAGE_DISK', 'local'),
    'encrypt_key' => env('ACTIVITY_LOG_ENCRYPT_KEY'),
    'log_keep_days' => env('ACTIVITY_LOG_KEEP_DAYS', 30),
    // request/response/curl bodies above this size are stored as a size marker only (bytes)
    'max_body_bytes' => env('ACTIVITY_LOG_MAX_BODY_BYTES', 262144),
    // Body keys whose values are replaced with [REDACTED] before storing (case-insensitive, nested)
    'redact_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'old_password',
        'pin',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'client_secret',
        'api_key',
        'card_number',
        'cvv',
    ],
    // Header names whose values are replaced with [REDACTED] in stored curl commands (case-insensitive)
    'redact_headers' => [
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-csrf-token',
        'x-xsrf-token',
        'x-auth-token',
        'x-access-token',
        'proxy-authorization',
    ],
    'ip_ignore' => [
        // Full IPs
        'exact' => [
            '127.0.0.1',
            '::1',
            'localhost',
        ],

        // Prefixes (with star(*))
        'prefix' => [
            '192.168.',
            '10.',
            '172.16.',
        ],
    ],
];
