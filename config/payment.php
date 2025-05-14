<?php

return [
    'default_gateway' => 'fastspring', // Default fallback

    'gateways' => [
        'fastspring' => [
            'api_url' => env('FASTSPRING_API_URL'),
            'username' => env('FASTSPRING_USERNAME'),
            'password' => env('FASTSPRING_PASSWORD'),
            'webhook_secret' => env('FASTSPRING_SECRET'),
        ],
        'payproglobal' => [
            'api_url' => env('PAYPROGLOBAL_API_URL'),
            'api_key' => env('PAYPROGLOBAL_API_KEY'),
            'webhook_secret' => env('PAYPROGLOBAL_SECRET'),
        ],
        'paddle' => [
            'checkout_url' => env('PADDLE_CHECKOUT_URL'),
            'vendor_id' => env('PADDLE_VENDOR_ID'),
            'auth_code' => env('PADDLE_AUTH_CODE'),
            'public_key' => env('PADDLE_PUBLIC_KEY'),
            'product_ids' => [
                'starter' => env('PADDLE_STARTER_ID'),
                'pro' => env('PADDLE_PRO_ID'),
                'business' => env('PADDLE_BUSINESS_ID'),
                'default' => env('PADDLE_DEFAULT_ID'),
            ],
        ],
    ],
];
