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
            // Map internal add-on keys to FastSpring product paths
            'addons' => [
                'avatar_customization' => 'avatar-customization',
                'voice_customization' => 'voice-customization',
                'avatar-customization' => 'avatar-customization',
                'voice-customization' => 'voice-customization',
            ],
            'product_ids' => [
                'free' => 'free-plan',
                'starter' => 'starter-plan',
                'pro' => 'pro-plan',
                'business' => 'business-plan',
                'enterprise' => 'enterprise-plan',
            ],
            'use_redirect_callback' => true,
            'api_base_url' => env('FASTSPRING_API_URL', 'https://api.fastspring.com'),
            'proration_enabled' => env('FASTSPRING_PRORATION_ENABLED', false),
        ],

        'Paddle' => [
            'api_key' => env('PADDLE_API_KEY', ''),
            'public_key' => env('PADDLE_PUBLIC_KEY', ''),
            // if sandbox environment, use https://sandbox-api.paddle.com/
            // if production environment, use https://api.paddle.com/
            'api_url' => env('PADDLE_API_URL', filter_var(env('PADDLE_SANDBOX', true), FILTER_VALIDATE_BOOLEAN) ? 'https://sandbox-api.paddle.com/' : 'https://api.paddle.com/'),
            'environment' => filter_var(env('PADDLE_SANDBOX', true), FILTER_VALIDATE_BOOLEAN) ? 'sandbox' : 'production',
            'product_ids' => [
                'free' => env('PADDLE_FREE_PRODUCT_ID', 'pro_01jzfasb1ra610mkb7y07f28ee'),
                'starter' => env('PADDLE_STARTER_PRODUCT_ID', 'pro_01jzfaqgyk4tb843ws5ehd5srp'),
                'pro' => env('PADDLE_PRO_PRODUCT_ID', 'pro_01jvyvq5qszm52esqze83w8j4d'),
                'business' => env('PADDLE_BUSINESS_PRODUCT_ID', 'pro_01jzfaypf0snmh89gxr3j9eaw2'),
            ],
            'price_ids' => [
                'free' => env('PADDLE_FREE_PRICE_ID', 'pri_01jzfasxy47m2040xq0bsq4ky3'),
                'starter' => env('PADDLE_STARTER_PRICE_ID', 'pri_01jzfarqmkedvrw0bvcnxct69b'),
                'pro' => env('PADDLE_PRO_PRICE_ID', 'pri_01jvyvt5hs48d5gd85m54nv0a6'),
                'business' => env('PADDLE_BUSINESS_PRICE_ID', 'pri_01jzfazffnex40gbr63d6vjnbk'),
            ],
            'checkout_url' => 'https://sandbox-checkout.paddle.com',
            'client_side_token' => env('PADDLE_CLIENT_SIDE_TOKEN', 'test_dab715bb779c31552d5b22561f0'),
            'pro_plan_price_id' => 'pri_01jvyvt5hs48d5gd85m54nv0a6',
            'vendor_id' => env('PADDLE_VENDOR_ID', ''),
            'webhook_secret' => env('PADDLE_WEBHOOK_SECRET', '')
        ],

        'PayProGlobal' => [
            'api_key' => env('PAYPROGLOBAL_API_KEY', ''),
            'api_url' => env('PAYPROGLOBAL_API_URL', 'https://api.payproglobal.com/v1'),
            'script_url' => env('PAYPROGLOBAL_SCRIPT_URL', 'https://checkout.payproglobal.com/checkout.js'),
            'webhook_secret' => env('PAYPROGLOBAL_WEBHOOK_SECRET', '5c96NqvkMm'),
            'vendor_account_id' => env('PAYPROGLOBAL_VENDOR_ACCOUNT_ID', '170815'),
            'api_secret_key' => env('PAYPROGLOBAL_API_SECRET_KEY', 'dd8c46a2-53cd-40cd-b265-0e98efc8096d'),
            'product_ids' => [
                'starter' => (int)env('PPG_PRODUCT_STARTER', 1),
                'pro' => (int)env('PPG_PRODUCT_PRO', 2),
                'business' => (int)env('PPG_PRODUCT_BUSINESS', 3),
                'enterprise' => (int)env('PPG_PRODUCT_ENTERPRISE', 4),
                'free' => (int)env('PPG_PRODUCT_FREE', 5),
            ],
            'merchant_id' => env('MERCHANT_ID', ''),
            'product_id_pro' => env('PPG_PRODUCT_PRO_ID', '112701'),
            'test_mode' => env('PAYPROGLOBAL_TEST_MODE', true),
            'endpoints' => [
                'checkout' => [
                    'live' => 'https://store.payproglobal.com/checkout',
                    'sandbox' => 'https://sandbox.payproglobal.com/checkout'
                ],
                'api' => [
                    'live' => 'https://store.payproglobal.com/api',
                    'sandbox' => 'https://sandbox.payproglobal.com/api'
                ]
            ]
        ],

        'License API' => [
            'endpoint' => env('LICENSE_API_ENDPOINT', 'https://openapi.xiaoice.com/vh-cp/api/partner/tenant/subscription/license/add'),
            'subscription_code' => env('SUBSCRIPTION_CODE', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FirstPromoter Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for FirstPromoter affiliate tracking. Used for tracking
    | sales and commissions for Pay Pro Global and Paddle payments.
    |
    */
    'firstpromoter' => [
        'api_key' => env('FIRSTPROMOTER_API_KEY', ''),
        'account_id' => env('FIRSTPROMOTER_ACCOUNT_ID', ''),
        'default_currency' => env('FIRSTPROMOTER_DEFAULT_CURRENCY', 'USD'),
        'enabled' => env('FIRSTPROMOTER_ENABLED', false),
    ],
];
