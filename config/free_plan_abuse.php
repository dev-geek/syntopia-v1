<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Free Plan Abuse Prevention Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for preventing abuse of the
    | free plan by limiting registrations from the same device/IP.
    |
    */

    // Maximum number of registration attempts allowed per device/IP/email
    'max_attempts' => env('FREE_PLAN_MAX_ATTEMPTS', 3),

    // Time period in days to track attempts
    'tracking_period_days' => env('FREE_PLAN_TRACKING_DAYS', 30),

    // Whether to enable device fingerprinting
    'enable_device_fingerprinting' => env('FREE_PLAN_ENABLE_FINGERPRINTING', true),

    // Whether to enable IP tracking
    'enable_ip_tracking' => env('FREE_PLAN_ENABLE_IP_TRACKING', true),

    // Whether to enable email tracking
    'enable_email_tracking' => env('FREE_PLAN_ENABLE_EMAIL_TRACKING', true),

    // Block duration in days (0 = permanent)
    'block_duration_days' => env('FREE_PLAN_BLOCK_DURATION', 0),

    // Testing bypass: when true, no device/IP/email will be blocked or counted
    'testing_bypass_enabled' => env('FREE_PLAN_TESTING_BYPASS', false),

    // Whitelists for exempting identifiers from blocking/counting
    'whitelist' => [
        // Exact IP addresses
        'ips' => array_filter(array_map('trim', explode(',', env('FREE_PLAN_WHITELIST_IPS', '')))),
        // Exact device fingerprints (sha256 from DeviceFingerprintService)
        'device_fingerprints' => array_filter(array_map('trim', explode(',', env('FREE_PLAN_WHITELIST_DEVICE_FPS', '')))),
        // Exact emails
        'emails' => array_filter(array_map('trim', explode(',', env('FREE_PLAN_WHITELIST_EMAILS', '')))),
        // Fingerprint IDs from cookie
        'fingerprint_ids' => array_filter(array_map('trim', explode(',', env('FREE_PLAN_WHITELIST_FP_IDS', '')))),
    ],

    // Error messages
    'messages' => [
        'too_many_attempts' => 'We suspect an account has already been registered from this device. If this seems wrong, please contact support.',
        'device_blocked' => 'We suspect an account has already been registered from this device. If this seems wrong, please contact support.',
        'ip_blocked' => 'We suspect an account has already been registered from this network. If this seems wrong, please contact support.',
        'email_blocked' => 'We suspect an account has already been registered with this email. If this seems wrong, please contact support.',
    ],

    // Logging configuration
    'logging' => [
        'enabled' => env('FREE_PLAN_LOGGING_ENABLED', true),
        'level' => env('FREE_PLAN_LOG_LEVEL', 'info'),
    ],
];
