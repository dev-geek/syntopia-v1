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

    // Error messages
    'messages' => [
        'too_many_attempts' => 'Too many registration attempts from this device. Please contact support if you need assistance.',
        'device_blocked' => 'Registration is not allowed from this device. Please contact support if you believe this is an error.',
        'ip_blocked' => 'Registration is not allowed from this IP address. Please contact support if you believe this is an error.',
        'email_blocked' => 'This email address has been blocked due to abuse. Please contact support.',
    ],

    // Logging configuration
    'logging' => [
        'enabled' => env('FREE_PLAN_LOGGING_ENABLED', true),
        'level' => env('FREE_PLAN_LOG_LEVEL', 'info'),
    ],
]; 