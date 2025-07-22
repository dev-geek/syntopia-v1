<?php

// Debug script to test cancellation endpoint
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "=== Debugging Subscription Cancellation ===\n";

// Test 1: Check if we can access the route
echo "\n1. Testing route accessibility...\n";
try {
    $response = \Illuminate\Support\Facades\Http::get('http://127.0.0.1:8000/payments/cancel-subscription');
    echo "Route response status: " . $response->status() . "\n";
    echo "Route response body: " . $response->body() . "\n";
} catch (Exception $e) {
    echo "Route test failed: " . $e->getMessage() . "\n";
}

// Test 2: Check user authentication
echo "\n2. Testing user authentication...\n";
try {
    $user = Auth::user();
    if ($user) {
        echo "User authenticated: " . $user->email . "\n";
        echo "User ID: " . $user->id . "\n";
        echo "Is subscribed: " . ($user->is_subscribed ? 'Yes' : 'No') . "\n";
        echo "Subscription ID: " . ($user->subscription_id ?? 'None') . "\n";
        echo "Payment Gateway: " . ($user->paymentGateway ? $user->paymentGateway->name : 'None') . "\n";
    } else {
        echo "No user authenticated\n";
    }
} catch (Exception $e) {
    echo "Authentication test failed: " . $e->getMessage() . "\n";
}

// Test 3: Check Paddle configuration
echo "\n3. Testing Paddle configuration...\n";
try {
    $apiKey = config('payment.gateways.Paddle.api_key');
    $environment = config('payment.gateways.Paddle.environment', 'sandbox');

    echo "Paddle API Key: " . (empty($apiKey) ? 'MISSING' : 'CONFIGURED') . "\n";
    echo "Paddle Environment: " . $environment . "\n";

    if (!empty($apiKey)) {
        $apiBaseUrl = $environment === 'production'
            ? 'https://api.paddle.com'
            : 'https://sandbox-api.paddle.com';

        echo "Paddle API Base URL: " . $apiBaseUrl . "\n";

        // Test API connectivity
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json'
        ])->get("{$apiBaseUrl}/products");

        if ($response->successful()) {
            echo "Paddle API connectivity: OK\n";
        } else {
            echo "Paddle API connectivity: FAILED (Status: " . $response->status() . ")\n";
        }
    }
} catch (Exception $e) {
    echo "Paddle configuration test failed: " . $e->getMessage() . "\n";
}

echo "\n=== Debug completed ===\n";
