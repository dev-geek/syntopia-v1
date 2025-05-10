<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected $gateway;
    protected $config;

    public function __construct()
    {
        // Default gateway can be set from config or database
        $this->gateway = config('payment.default_gateway');
        $this->config = config('payment.gateways.'.$this->gateway);
    }

    /**
     * Set the payment gateway to use
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        $this->config = config('payment.gateways.'.$gateway);
        return $this;
    }

    /**
     * Create a payment session for the given order
     */
    public function createPaymentSession(Order $order)
    {
        switch ($this->gateway) {
            case 'fastspring':
                return $this->createFastSpringSession($order);
            case 'paddle':
                return $this->createPaddleSession($order);
            case 'payproglobal':
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
            case 'fastspring':
                return $this->handleFastSpringCallback($data);
            case 'paddle':
                return $this->handlePaddleCallback($data);
            case 'payproglobal':
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
        $payload = [
            'reference' => 'order_'.$order->id,
            'product' => $order->package,
            'price' => $order->amount,
            'currency' => 'USD',
            'customer' => [
                'email' => $order->user->email,
                'firstName' => $order->user->name,
            ],
            'returnUrl' => route('payment.success'),
            'cancelUrl' => route('payment.cancel'),
        ];

        $response = Http::withBasicAuth($this->config['username'], $this->config['password'])
            ->post($this->config['api_url'].'/sessions', $payload);

        if ($response->failed()) {
            Log::error('FastSpring API Error', ['response' => $response->body()]);
            throw new \Exception('Failed to create FastSpring session');
        }

        return $response->json()['url']; // Return checkout URL
    }

    protected function handleFastSpringCallback(array $data)
    {
        // Verify the webhook signature
        $signature = hash_hmac('sha256', $data['events'], $this->config['webhook_secret']);

        if (!hash_equals($signature, $data['signature'])) {
            throw new \Exception('Invalid FastSpring webhook signature');
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
                        'payment' => 'yes',
                        'transaction_id' => $event['data']['id'],
                        'payment_method' => 'fastspring',
                    ]);
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
        $payload = [
            'vendor_id' => $this->config['vendor_id'],
            'vendor_auth_code' => $this->config['auth_code'],
            'product_id' => $this->getPaddleProductId($order->package),
            'title' => $order->package.' Subscription',
            'image_url' => asset('images/logo.png'),
            'prices' => [$order->amount.'USD'],
            'return_url' => route('payment.success'),
            'customer_email' => $order->user->email,
            'passthrough' => json_encode(['order_id' => $order->id]),
        ];

        $response = Http::post($this->config['checkout_url'], $payload);

        if ($response->failed()) {
            Log::error('Paddle API Error', ['response' => $response->body()]);
            throw new \Exception('Failed to create Paddle checkout');
        }

        return $response->json()['response']['url']; // Return checkout URL
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
            throw new \Exception('Invalid Paddle webhook signature');
        }

        // Process the event
        if ($data['alert_name'] === 'payment_succeeded') {
            $orderId = json_decode($data['passthrough'], true)['order_id'];

            $order = Order::find($orderId);
            if ($order) {
                $order->update([
                    'payment' => 'yes',
                    'transaction_id' => $data['checkout_id'],
                    'payment_method' => 'paddle',
                ]);
            }
        }

        return true;
    }

    /**
     * PayProGlobal implementation
     */
    protected function createPayProGlobalSession(Order $order)
    {
        $payload = [
            'customer' => [
                'email' => $order->user->email,
                'firstName' => $order->user->name,
            ],
            'product' => [
                'name' => $order->package.' Subscription',
                'price' => $order->amount,
                'currency' => 'USD',
            ],
            'settings' => [
                'returnUrl' => route('payment.success'),
                'cancelUrl' => route('payment.cancel'),
            ],
            'custom' => [
                'order_id' => $order->id,
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->config['api_key'],
            'Content-Type' => 'application/json',
        ])->post($this->config['api_url'].'/checkout', $payload);

        if ($response->failed()) {
            Log::error('PayProGlobal API Error', ['response' => $response->body()]);
            throw new \Exception('Failed to create PayProGlobal checkout');
        }

        return $response->json()['url']; // Return checkout URL
    }

    protected function handlePayProGlobalCallback(array $data)
    {
        // Verify webhook signature
        $signature = hash_hmac('sha256', json_encode($data), $this->config['webhook_secret']);

        if (!hash_equals($signature, $data['signature'])) {
            throw new \Exception('Invalid PayProGlobal webhook signature');
        }

        // Process the event
        if ($data['event'] === 'payment.completed') {
            $orderId = $data['custom']['order_id'];

            $order = Order::find($orderId);
            if ($order) {
                $order->update([
                    'payment' => 'yes',
                    'transaction_id' => $data['transaction_id'],
                    'payment_method' => 'payproglobal',
                ]);
            }
        }

        return true;
    }

    /**
     * Helper method to get Paddle product ID based on package
     */
    protected function getPaddleProductId(string $package): string
    {
        $mapping = [
            'Starter' => $this->config['product_ids']['starter'],
            'Pro' => $this->config['product_ids']['pro'],
            'Business' => $this->config['product_ids']['business'],
        ];

        return $mapping[$package] ?? $this->config['product_ids']['default'];
    }
}
