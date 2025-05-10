<?php

return [
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'fastspring'),

    'gateways' => [
        'fastspring' => [
            'username' => env('FASTSPRING_USERNAME'),
            'password' => env('FASTSPRING_PASSWORD'),
            'api_url' => env('FASTSPRING_API_URL', 'https://api.fastspring.com'),
            'webhook_secret' => env('FASTSPRING_WEBHOOK_SECRET'),
        ],

        'paddle' => [
            'vendor_id' => env('PADDLE_VENDOR_ID'),
            'auth_code' => env('PADDLE_AUTH_CODE'),
            'public_key' => env('PADDLE_PUBLIC_KEY'),
            'checkout_url' => env('PADDLE_CHECKOUT_URL', 'https://checkout.paddle.com/api/2.0/product/generate_pay_link'),
            'product_ids' => [
                'starter' => env('PADDLE_PRODUCT_ID_STARTER'),
                'pro' => env('PADDLE_PRODUCT_ID_PRO'),
                'business' => env('PADDLE_PRODUCT_ID_BUSINESS'),
                'default' => env('PADDLE_PRODUCT_ID_DEFAULT'),
            ],
        ],

        'payproglobal' => [
            'api_key' => env('PAYPROGLOBAL_API_KEY'),
            'api_url' => env('PAYPROGLOBAL_API_URL', 'https://api.payproglobal.com/v1'),
            'webhook_secret' => env('PAYPROGLOBAL_WEBHOOK_SECRET'),
        ],
    ],
];
