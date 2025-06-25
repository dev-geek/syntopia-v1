<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls which payment gateway will be used by default when
    | no specific gateway is mentioned. You can set this to any of the
    | supported gateways.
    |
    */
    'default_gateway' => env('DEFAULT_PAYMENT_GATEWAY', 'FastSpring'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the payment gateways for your application.
    |
    */
    'gateways' => [
        'FastSpring' => [
            'storefront' => env('FASTSPRING_STOREFRONT', 'livebuzzstudio.test.onfastspring.com/popup-check-paymet'),
            'username' => env('FASTSPRING_USERNAME', ''),
            'password' => env('FASTSPRING_PASSWORD', ''),
            'webhook_secret' => env('FASTSPRING_WEBHOOK_SECRET', ''),
            'product_ids' => [
                'free' => 'free-plan',
                'starter' => 'starter-plan',
                'pro' => 'pro-plan',
                'business' => 'business-plan',
                'enterprise' => 'enterprise-plan',
            ],
            'use_redirect_callback' => true,
        ],

        'Paddle' => [
            'api_key' => env('PADDLE_API_KEY', ''),
            'public_key' => env('PADDLE_PUBLIC_KEY', ''),
            'api_url' => env('PADDLE_API_URL', 'https://api.paddle.com/'),
            'environment' => env('PADDLE_ENVIRONMENT', 'sandbox'), // sandbox or production
            'product_ids' => [
                'starter' => (int)env('PADDLE_PRODUCT_STARTER', 1),
                'pro' => (int)env('PADDLE_PRODUCT_PRO', 2),
                'business' => (int)env('PADDLE_PRODUCT_BUSINESS', 3),
                'enterprise' => (int)env('PADDLE_PRODUCT_ENTERPRISE', 4),
            ],
            'checkout_url' => 'https://sandbox-checkout.paddle.com',
            'client_side_token' => env('CLIENT_SIDE_TOKEN', 'test_dab715bb779c31552d5b22561f0'),
            'pro_plan_price_id' => 'pri_01jvyvt5hs48d5gd85m54nv0a6',
            'vendor_id' => env('PADDLE_VENDOR_ID', ''),
            'webhook_secret' => env('WEBHOOK_SECRET', '')
        ],

        'PayProGlobal' => [
            'api_key' => env('PAYPROGLOBAL_API_KEY', ''),
            'api_url' => env('PAYPROGLOBAL_API_URL', 'https://api.payproglobal.com/v1'),
            'script_url' => env('PAYPROGLOBAL_SCRIPT_URL', 'https://checkout.payproglobal.com/checkout.js'),
            'webhook_secret' => env('PAYPROGLOBAL_WEBHOOK_SECRET', ''),
            'product_ids' => [
                'starter' => (int)env('PPG_PRODUCT_STARTER', 1),
                'pro' => (int)env('PPG_PRODUCT_PRO', 2),
                'business' => (int)env('PPG_PRODUCT_BUSINESS', 3),
                'enterprise' => (int)env('PPG_PRODUCT_ENTERPRISE', 4),
            ],
            'merchant_id' => env('MERCHANT_ID', ''),
            'product_id_pro' => env('PPG_PRODUCT_PRO_ID', '112701'),
            'test_mode' => env('PAYPROGLOBAL_TEST_MODE', true),
        ],

        'License API' => [
            'endpoint' => env('LICENSE_API_ENDPOINT', 'https://openapi.xiaoice.com/vh-cp/api/partner/tenant/subscription/license/add'),
            'subscription_code' => env('SUBSCRIPTION_CODE', 'PKG-VD-STD-01'),
        ],
    ],
];
