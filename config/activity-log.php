<?php
/**
 * Created by Shukhratjon Yuldashev on 2025-04-30
 * Contact: https://t.me/alif_coder
 * Time: 2:52 PM
 */

return [
        'db_connection' => env('ACTIVITY_LOG_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

        'use_queue'  => true,
        'queue_name' => 'default',

        'main_connection' => env('MAIN_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
        'models' => [
                'user' => App\Models\User::class,
        ],
        'encrypt_key' => env('ACTIVITY_LOG_ENCRYPT_KEY'),
        'log_keep_days' => env('ACTIVITY_LOG_KEEP_DAYS', 30),
];
