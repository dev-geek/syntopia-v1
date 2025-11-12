<?php

namespace App\Services;

use App\Http\Controllers\SubscriptionController;
use App\Models\Order;
use App\Models\PaymentGateways;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    protected $gateway;
    protected $config;

    public function __construct()
    {
        $this->gateway = config('payment.default_gateway');
        $this->config = config('payment.gateways.' . $this->gateway);
    }

    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        $this->config = config('payment.gateways.' . $gateway);
        Log::info("PaymentService gateway set to: {$this->gateway}");
        return $this;
    }

    public function initializeGateway(Order $order)
    {
        $user = $order->user;

        if ($user->payment_gateway_id) {
            $paymentGateway = PaymentGateways::find($user->payment_gateway_id);
            if ($paymentGateway) {
                $this->setGateway($paymentGateway->name);
                Log::info("Active Gateway Selected: {$paymentGateway->name}");
                return;
            } else {
                Log::warning("Invalid payment gateway ID for user: {$user->payment_gateway_id}");
            }
        }

        $activeGateway = PaymentGateways::where('is_active', true)->first();
        if (!$activeGateway) {
            throw new \Exception("No active payment gateway configured");
        }

        Log::info("Active Gateway Selected: {$activeGateway->name}");
        $user->update(['payment_gateway_id' => $activeGateway->id]);
        $this->setGateway($activeGateway->name);
    }

    public function createPaymentSession(string $packageName, User $user)
    {
        $package = Package::where('name', $packageName)->firstOrFail();

        $order = Order::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'amount' => $package->getEffectivePrice(),
            'status' => 'pending',
            'transaction_id' => 'ORD-' . strtoupper(Str::random(10)),
            'payment_gateway_id' => $user->payment_gateway_id,
        ]);

        if ($package->isFree()) {
            $order->update(['status' => 'completed']);
            return [
                'success' => true,
                'orderId' => $order->id,
                'message' => 'Free package activated'
            ];
        }

        $this->initializeGateway($order);

        switch ($this->gateway) {
            case 'FastSpring':
                return $this->createFastSpringSession($order);
            case 'Paddle':
                return $this->createPaddleSession($order);
            case 'Pay Pro Global':
                return $this->createPayProGlobalSession($order);
            default:
                throw new \Exception("Unsupported payment gateway: {$this->gateway}");
        }
    }

    public function handlePaymentCallback(array $data)
    {
        switch ($this->gateway) {
            case 'FastSpring':
                return $this->handleFastSpringCallback($data);
            case 'Paddle':
                return $this->handlePaddleCallback($data);
            case 'Pay Pro Global':
                return $this->handlePayProGlobalCallback($data);
            default:
                throw new \Exception("Unsupported payment gateway: {$this->gateway}");
        }
    }

    protected function createFastSpringSession(Order $order)
    {
        return [
            'success' => true,
            'productPath' => $this->getFastSpringProductPath($order->package),
            'orderId' => $order->id
        ];
    }

    protected function handleFastSpringCallback(array $data)
    {
        if (!isset($data['events']) || !isset($data['signature'])) {
            Log::error('Invalid FastSpring webhook payload - missing required fields');
            throw new \Exception('Invalid webhook payload');
        }

        $signature = hash_hmac('sha256', $data['events'], $this->config['webhook_secret']);
        if (!hash_equals($signature, $data['signature'])) {
            Log::error('Invalid FastSpring webhook signature');
            throw new \Exception('Invalid webhook signature');
        }

        $events = json_decode($data['events'], true);
        foreach ($events as $event) {
            if ($event['type'] === 'order.completed') {
                $orderReference = $event['data']['reference'];
                $orderId = str_replace('order_', '', $orderReference);
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'status' => 'completed',
                        'transaction_id' => $event['data']['id'],
                        'payment_method' => 'FastSpring',
                    ]);
                    app(SubscriptionController::class)->updateUserSubscription($order);
                }
            }
        }
        return true;
    }

    protected function createPaddleSession(Order $order)
    {
        $payload = [
            'product_id' => $this->getPaddleProductId($order->package),
            'customer_email' => $order->user->email,
            'passthrough' => json_encode(['order_id' => $order->id]),
            'return_url' => route('payments.success', ['gateway' => 'paddle']),
            'cancel_url' => route('payments.cancel', ['gateway' => 'paddle']),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
            ])->post($this->config['api_url'] . '/checkout', $payload);

            if ($response->failed()) {
                Log::error('Paddle API Error', ['response' => $response->body()]);
                throw new \Exception('Failed to create Paddle checkout: ' . $response->body());
            }

            return ['success' => true, 'checkoutUrl' => $response->json()['url']];
        } catch (\Exception $e) {
            Log::error('Paddle checkout error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function handlePaddleCallback(array $data)
    {
        $publicKey = $this->config['public_key'];
        $signature = base64_decode($data['p_signature']);
        $fields = $data;
        unset($fields['p_signature']);
        ksort($fields);
        $dataToVerify = serialize($fields);
        $verified = openssl_verify($dataToVerify, $signature, $publicKey, OPENSSL_ALGO_SHA1);

        if ($verified !== 1) {
            Log::error('Invalid Paddle webhook signature');
            throw new \Exception('Invalid Paddle webhook signature');
        }

        if ($data['alert_name'] === 'payment_succeeded') {
            $passthrough = json_decode($data['passthrough'], true);
            $orderId = $passthrough['order_id'] ?? null;
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'status' => 'completed',
                        'transaction_id' => $data['checkout_id'],
                        'payment_method' => 'Paddle',
                    ]);
                    app(SubscriptionController::class)->updateUserSubscription($order);
                }
            }
        }
        return true;
    }

    protected function createPayProGlobalSession(Order $order)
    {
        try {
            $payload = [
                'product_id' => $this->getPayProGlobalProductId($order->package),
                'customer' => [
                    'email' => $order->user->email,
                    'first_name' => $order->user->first_name ?? 'Customer',
                    'last_name' => $order->user->last_name ?? $order->user->id,
                ],
                'custom_fields' => [
                    'order_id' => $order->id,
                ],
                'return_url' => route('payments.success', ['gateway' => 'payproglobal']),
                'cancel_url' => route('payments.cancel', ['gateway' => 'payproglobal']),
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
            ])->post($this->config['api_url'] . '/checkout-sessions', $payload);

            if ($response->failed()) {
                Log::error('PayProGlobal API Error', ['response' => $response->body()]);
                throw new \Exception('Failed to create PayProGlobal checkout: ' . $response->body());
            }

            return ['success' => true, 'checkoutUrl' => $response->json()['checkout_url']];
        } catch (\Exception $e) {
            Log::error('PayProGlobal checkout error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function handlePayProGlobalCallback(array $data)
    {
        $receivedSignature = $data['signature'] ?? '';
        $payload = $data;
        unset($payload['signature']);
        $calculatedSignature = hash_hmac('sha256', json_encode($payload), $this->config['webhook_secret']);
        if (!hash_equals($calculatedSignature, $receivedSignature)) {
            Log::error('Invalid PayProGlobal webhook signature');
            throw new \Exception('Invalid PayProGlobal webhook signature');
        }

        if ($data['event_type'] === 'payment_success') {
            $customFields = $data['custom_fields'] ?? [];
            $orderId = $customFields['order_id'] ?? null;
            if ($orderId) {
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'status' => 'completed',
                        'transaction_id' => $data['transaction_id'] ?? null,
                        'payment_method' => 'Pay Pro Global',
                    ]);
                    app(SubscriptionController::class)->updateUserSubscription($order);
                }
            }
        }
        return true;
    }

    protected function getFastSpringProductPath(string $package): string
    {
        $mapping = [
            'Free' => 'free-plan',
            'Starter' => 'starter-plan',
            'Pro' => 'pro-plan',
            'Business' => 'business-plan',
            'Enterprise' => 'enterprise-plan',
        ];
        return $mapping[$package] ?? 'starter-plan';
    }

    protected function getPaddleProductId(string $package): int
    {
        $mapping = [
            'Starter' => $this->config['product_ids']['starter'],
            'Pro' => $this->config['product_ids']['pro'],
            'Business' => $this->config['product_ids']['business'],
        ];
        
        // Enterprise is optional - only use if it exists in config
        if (isset($this->config['product_ids']['enterprise'])) {
            $mapping['Enterprise'] = $this->config['product_ids']['enterprise'];
        }
        
        return $mapping[$package] ?? $this->config['product_ids']['starter'];
    }

    protected function getPayProGlobalProductId(string $package): int
    {
        $mapping = [
            'Starter' => $this->config['product_ids']['starter'],
            'Pro' => $this->config['product_ids']['pro'],
            'Business' => $this->config['product_ids']['business'],
            'Enterprise' => $this->config['product_ids']['enterprise'],
        ];
        return $mapping[$package] ?? $this->config['product_ids']['starter'];
    }
}
