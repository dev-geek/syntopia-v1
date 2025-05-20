<?php

namespace App\Services;

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
        // Default gateway can be set from config or database
        $this->gateway = config('payment.default_gateway');
        $this->config = config('payment.gateways.' . $this->gateway);
    }

    /**
     * Set the payment gateway to use
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        $this->config = config('payment.gateways.' . $gateway);
        return $this;
    }

    /**
     * Determine gateway from user or admin settings
     */
    public function initializeGateway(Order $order)
    {
        $user = $order->user;

        // User's existing gateway if set
        if ($user->payment_gateway) {
            $this->setGateway($user->payment_gateway);
            return;
        }

        // Get admin's active gateway
        $activeGateway = PaymentGateways::where('is_active', true)->first();

        if (!$activeGateway) {
            throw new \Exception("No active payment gateway configured");
        }

        Log::info("Active Gateway Selected: {$activeGateway->name}");

        // Save to user's profile
        $user->payment_gateway = $activeGateway->name;
        $user->save();

        $this->setGateway($activeGateway->name);
        Log::info("PaymentService is using: {$this->gateway}");
    }

    /**
     * Create a payment session for the given package and user
     */
    public function createPaymentSession(string $packageName, User $user)
    {
        // Create an order record
        $package = Package::where('name', $packageName)->firstOrFail();

        $order = Order::create([
            'user_id' => $user->id,
            'package' => $packageName,
            'amount' => $package->price,
            'status' => 'pending',
            'order_id' => 'ORD-' . strtoupper(Str::random(10)),
        ]);

        // Determine the payment gateway to use
        $activeGateway = PaymentGateways::where('is_active', true)->first();

        if (!$activeGateway) {
            throw new \Exception("No active payment gateway configured");
        }

        $this->setGateway($activeGateway->name);

        // Process with appropriate gateway
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

    /**
     * Handle payment callback/webhook
     */
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

    /**
     * FastSpring implementation
     */
    protected function createFastSpringSession(Order $order)
    {
        // For FastSpring, we don't need to create a session via API
        // The JavaScript library handles it client-side
        // But we'll return the product path to use
        return [
            'success' => true,
            'productPath' => $this->getFastSpringProductPath($order->package),
            'orderId' => $order->id
        ];
    }

    protected function handleFastSpringCallback(array $data)
    {
        // Validation
        if (!isset($data['events']) || !isset($data['signature'])) {
            Log::error('Invalid FastSpring webhook payload - missing required fields');
            throw new \Exception('Invalid webhook payload');
        }

        // Verify the webhook signature
        $signature = hash_hmac('sha256', $data['events'], $this->config['webhook_secret']);

        if (!hash_equals($signature, $data['signature'])) {
            Log::error('Invalid FastSpring webhook signature');
            throw new \Exception('Invalid webhook signature');
        }

        // Process the events
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

                    // Update user subscription
                    $this->updateUserSubscription($order);
                }
            }
        }

        return true;
    }

    /**
     * Paddle implementation
     */
    protected function createPaddleSession(Order $order)
    {
        // For Paddle API v2
        $payload = [
            'product_id' => $this->getPaddleProductId($order->package),
            'customer_email' => $order->user->email,
            'passthrough' => json_encode(['order_id' => $order->id]),
            'return_url' => route('payment.success', ['gateway' => 'paddle']),
            'cancel_url' => route('payment.cancel', ['gateway' => 'paddle']),
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
        // Verify Paddle webhook signature
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

        // Process the event
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

                    // Update user subscription
                    $this->updateUserSubscription($order);
                }
            }
        }

        return true;
    }

    /**
     * PayProGlobal implementation
     */
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
                'return_url' => route('payment.success', ['gateway' => 'payproglobal']),
                'cancel_url' => route('payment.cancel', ['gateway' => 'payproglobal']),
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
        // Verify webhook signature
        $receivedSignature = $data['signature'] ?? '';
        $payload = $data;
        unset($payload['signature']);

        $calculatedSignature = hash_hmac('sha256', json_encode($payload), $this->config['webhook_secret']);

        if (!hash_equals($calculatedSignature, $receivedSignature)) {
            Log::error('Invalid PayProGlobal webhook signature');
            throw new \Exception('Invalid PayProGlobal webhook signature');
        }

        // Process the event
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

                    // Update user subscription
                    $this->updateUserSubscription($order);
                }
            }
        }

        return true;
    }

    /**
     * Update user subscription based on completed order
     */
    protected function updateUserSubscription(Order $order)
    {
        $user = $order->user;

        // Update user's subscription details
        $user->update([
            'package' => $order->package,
            'subscription_ends_at' => $this->calculateSubscriptionEndDate($order->package),
        ]);

        // You might want to log subscription activity or trigger other services
        Log::info("User {$user->id} subscription updated to {$order->package}");

        return true;
    }

    /**
     * Calculate subscription end date based on package
     */
    protected function calculateSubscriptionEndDate(string $packageName)
    {
        // This is just an example - adjust based on your actual packages
        $periodMap = [
            'Free' => null, // No end date for free tier
            'Starter' => now()->addMonth(),
            'Pro' => now()->addMonth(),
            'Business' => now()->addMonth(),
            'Enterprise' => now()->addYear(), // Assuming enterprise is annual
        ];

        return $periodMap[$packageName] ?? now()->addMonth();
    }

    /**
     * Helper methods to get product IDs for each gateway
     */
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
            'Enterprise' => $this->config['product_ids']['enterprise'],
        ];

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
