<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Services\FirstPromoterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Mockery;

class FirstPromoterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $paddleGateway;
    protected $payProGlobalGateway;
    protected $proPackage;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('payment.firstpromoter.enabled', true);
        Config::set('payment.firstpromoter.api_key', 'test_api_key');
        Config::set('payment.firstpromoter.account_id', 'test_account_id');
        Config::set('payment.firstpromoter.default_currency', 'USD');

        $this->paddleGateway = PaymentGateways::create([
            'name' => 'Paddle',
            'is_active' => true
        ]);

        $this->payProGlobalGateway = PaymentGateways::create([
            'name' => 'Pay Pro Global',
            'is_active' => true
        ]);

        $this->proPackage = Package::create([
            'name' => 'Pro',
            'price' => 29.00,
            'duration' => 'month',
            'features' => ['feature1', 'feature2']
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);

        Http::preventStrayRequests();
        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_firstpromoter_tracks_paddle_payment_successfully()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_paddle_123',
            'status' => 'completed'
        ]);

        $successResponse = [
            'id' => 'sale_123',
            'sale_amount' => 2900,
            'referral' => [
                'id' => 'ref_456',
                'email' => 'promoter@example.com'
            ],
            'commissions' => [
                [
                    'id' => 'comm_789',
                    'status' => 'pending',
                    'amount' => 290,
                    'promoter_campaign' => [
                        'promoter' => [
                            'email' => 'promoter@example.com'
                        ],
                        'campaign' => [
                            'name' => 'Test Campaign'
                        ]
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.firstpromoter.com/*' => Http::response($successResponse, 200)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $paymentData = [
            'custom_data' => [],
            'metadata' => []
        ];

        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNotNull($response);
        $this->assertEquals('sale_123', $response['id']);
        $this->assertEquals('ref_456', $response['referral']['id']);
        $this->assertCount(1, $response['commissions']);

        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://api.firstpromoter.com/api/v2/track/sale'
                && $request->hasHeader('Authorization', 'Bearer test_api_key')
                && $request->hasHeader('Account-ID', 'test_account_id')
                && $request['event_id'] === $order->transaction_id
                && $request['amount'] === 2900
                && $request['email'] === $this->user->email
                && $request['plan'] === $this->proPackage->name;
        });
    }

    public function test_firstpromoter_tracks_payproglobal_payment_successfully()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->payProGlobalGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_ppg_123',
            'status' => 'completed'
        ]);

        $successResponse = [
            'id' => 'sale_456',
            'sale_amount' => 2900,
            'referral' => [
                'id' => 'ref_789',
                'email' => 'promoter@example.com'
            ],
            'commissions' => []
        ];

        Http::fake([
            'api.firstpromoter.com/*' => Http::response($successResponse, 200)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $paymentData = [
            'custom_data' => [],
            'metadata' => []
        ];

        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNotNull($response);
        $this->assertEquals('sale_456', $response['id']);
        $this->assertEquals('ref_789', $response['referral']['id']);

        Http::assertSent(function ($request) use ($order) {
            return $request->url() === 'https://api.firstpromoter.com/api/v2/track/sale'
                && $request->hasHeader('Authorization', 'Bearer test_api_key')
                && $request->hasHeader('Account-ID', 'test_account_id')
                && $request['event_id'] === $order->transaction_id
                && $request['amount'] === 2900
                && $request['email'] === $this->user->email;
        });
    }

    public function test_firstpromoter_extracts_tid_from_paddle_custom_data()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_paddle_123',
            'status' => 'completed'
        ]);

        Http::fake([
            'api.firstpromoter.com/*' => Http::response(['id' => 'sale_123'], 200)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $paymentData = [
            'custom_data' => [
                'fp_tid' => 'tid_12345',
                'ref_id' => 'ref_67890'
            ],
            'metadata' => []
        ];

        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
            'tid' => $paymentData['custom_data']['fp_tid'] ?? null,
            'ref_id' => $paymentData['custom_data']['ref_id'] ?? null,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        Http::assertSent(function ($request) {
            return $request['tid'] === 'tid_12345'
                && $request['ref_id'] === 'ref_67890';
        });
    }

    public function test_firstpromoter_extracts_tid_from_payproglobal_checkout_query_string()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->payProGlobalGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_ppg_123',
            'status' => 'completed'
        ]);

        Http::fake([
            'api.firstpromoter.com/*' => Http::response(['id' => 'sale_123'], 200)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $customDataJson = json_encode([
            'fp_tid' => 'tid_ppg_12345',
            'ref_id' => 'ref_ppg_67890'
        ]);
        $checkoutQueryString = 'custom=' . urlencode($customDataJson);

        $paymentData = [
            'checkout_query_string' => $checkoutQueryString,
            'custom_data' => [],
            'metadata' => []
        ];

        parse_str($checkoutQueryString, $checkoutParams);
        $decodedCustom = json_decode($checkoutParams['custom'], true);
        $tid = $decodedCustom['fp_tid'] ?? $decodedCustom['tid'] ?? null;
        $refId = $decodedCustom['ref_id'] ?? null;

        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        if ($tid) {
            $trackingData['tid'] = $tid;
        }
        if ($refId) {
            $trackingData['ref_id'] = $refId;
        }

        $response = $firstPromoterService->trackSale($trackingData);

        Http::assertSent(function ($request) {
            return $request['tid'] === 'tid_ppg_12345'
                && $request['ref_id'] === 'ref_ppg_67890';
        });
    }

    public function test_firstpromoter_handles_duplicate_event_id()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_duplicate_123',
            'status' => 'completed'
        ]);

        $duplicateResponse = [
            'message' => 'Sale already tracked'
        ];

        Http::fake([
            'api.firstpromoter.com/*' => Http::response($duplicateResponse, 409)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNotNull($response);
        $this->assertTrue($response['duplicate']);
        $this->assertEquals('Sale already tracked', $response['message']);
    }

    public function test_firstpromoter_handles_referral_not_found()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_notfound_123',
            'status' => 'completed'
        ]);

        $notFoundResponse = [
            'message' => 'Referral not found',
            'code' => 'REFERRAL_NOT_FOUND'
        ];

        Http::fake([
            'api.firstpromoter.com/*' => Http::response($notFoundResponse, 404)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNull($response);
    }

    public function test_firstpromoter_handles_validation_error()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_invalid_123',
            'status' => 'completed'
        ]);

        $validationErrorResponse = [
            'message' => 'Invalid request data'
        ];

        Http::fake([
            'api.firstpromoter.com/*' => Http::response($validationErrorResponse, 400)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNull($response);
    }

    public function test_firstpromoter_service_does_not_check_enabled_flag()
    {
        Config::set('payment.firstpromoter.enabled', false);

        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_disabled_123',
            'status' => 'completed'
        ]);

        $successResponse = [
            'id' => 'sale_123',
            'sale_amount' => 2900,
            'referral' => [
                'id' => 'ref_456',
                'email' => 'promoter@example.com'
            ],
            'commissions' => []
        ];

        Http::fake([
            'api.firstpromoter.com/*' => Http::response($successResponse, 200)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNotNull($response);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.firstpromoter.com/api/v2/track/sale';
        });
    }

    public function test_firstpromoter_skips_tracking_for_zero_amount()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 0,
            'currency' => 'USD',
            'transaction_id' => 'txn_zero_123',
            'status' => 'completed'
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNull($response);
        Http::assertNothingSent();
    }

    public function test_firstpromoter_converts_amount_to_cents()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.99,
            'currency' => 'USD',
            'transaction_id' => 'txn_cents_123',
            'status' => 'completed'
        ]);

        Http::fake([
            'api.firstpromoter.com/*' => Http::response(['id' => 'sale_123'], 200)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        Http::assertSent(function ($request) {
            return $request['amount'] === 2999;
        });
    }

    public function test_firstpromoter_handles_zero_decimal_currencies()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 1000,
            'currency' => 'JPY',
            'transaction_id' => 'txn_jpy_123',
            'status' => 'completed'
        ]);

        Http::fake([
            'api.firstpromoter.com/*' => Http::response(['id' => 'sale_123'], 200)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        Http::assertSent(function ($request) {
            return $request['amount'] === 1000
                && $request['currency'] === 'JPY';
        });
    }

    public function test_firstpromoter_handles_missing_api_credentials()
    {
        Config::set('payment.firstpromoter.api_key', '');
        Config::set('payment.firstpromoter.account_id', '');

        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_no_creds_123',
            'status' => 'completed'
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNull($response);
        Http::assertNothingSent();
    }

    public function test_firstpromoter_handles_missing_required_fields()
    {
        $firstPromoterService = new FirstPromoterService();

        $response = $firstPromoterService->trackSale([]);

        $this->assertNull($response);
        Http::assertNothingSent();
    }

    public function test_firstpromoter_handles_network_error()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_network_error_123',
            'status' => 'completed'
        ]);

        Http::fake([
            'api.firstpromoter.com/*' => Http::response([], 500)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        $this->assertNull($response);
    }

    public function test_firstpromoter_stores_response_in_order_metadata()
    {
        $order = Order::create([
            'user_id' => $this->user->id,
            'package_id' => $this->proPackage->id,
            'payment_gateway_id' => $this->paddleGateway->id,
            'amount' => 29.00,
            'currency' => 'USD',
            'transaction_id' => 'txn_metadata_123',
            'status' => 'completed',
            'metadata' => []
        ]);

        $successResponse = [
            'id' => 'sale_metadata_123',
            'sale_amount' => 2900,
            'referral' => [
                'id' => 'ref_metadata_456',
                'email' => 'promoter@example.com'
            ],
            'commissions' => [
                [
                    'id' => 'comm_789',
                    'status' => 'pending',
                    'amount' => 290,
                    'promoter_campaign' => [
                        'promoter' => [
                            'email' => 'promoter@example.com'
                        ],
                        'campaign' => [
                            'name' => 'Test Campaign'
                        ]
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.firstpromoter.com/*' => Http::response($successResponse, 200)
        ]);

        $firstPromoterService = new FirstPromoterService();
        $trackingData = [
            'email' => $this->user->email,
            'event_id' => $order->transaction_id,
            'amount' => $order->amount,
            'currency' => $order->currency ?? 'USD',
            'plan' => $this->proPackage->name,
        ];

        $response = $firstPromoterService->trackSale($trackingData);

        if ($response) {
            $currentMetadata = $order->metadata ?? [];
            $currentMetadata['firstpromoter'] = [
                'sale_id' => $response['id'] ?? null,
                'sale_amount' => $response['sale_amount'] ?? null,
                'referral_id' => $response['referral']['id'] ?? null,
                'referral_email' => $response['referral']['email'] ?? null,
                'commissions' => array_map(function ($commission) {
                    return [
                        'id' => $commission['id'] ?? null,
                        'status' => $commission['status'] ?? null,
                        'amount' => $commission['amount'] ?? null,
                        'promoter_email' => $commission['promoter_campaign']['promoter']['email'] ?? null,
                        'campaign_name' => $commission['promoter_campaign']['campaign']['name'] ?? null,
                    ];
                }, $response['commissions'] ?? []),
                'tracked_at' => now()->toIso8601String(),
            ];

            $order->update(['metadata' => $currentMetadata]);
        }

        $order->refresh();

        $this->assertNotNull($order->metadata['firstpromoter']);
        $this->assertEquals('sale_metadata_123', $order->metadata['firstpromoter']['sale_id']);
        $this->assertEquals('ref_metadata_456', $order->metadata['firstpromoter']['referral_id']);
        $this->assertCount(1, $order->metadata['firstpromoter']['commissions']);
    }
}

