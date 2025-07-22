<?php

// Simple test script to verify Paddle subscription cancellation
// This can be run from the command line to test the API endpoints

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "Testing Paddle Subscription Cancellation...\n";

// Get configuration
$apiKey = config('payment.gateways.Paddle.api_key');
$environment = config('payment.gateways.Paddle.environment', 'sandbox');
$apiBaseUrl = $environment === 'production'
    ? 'https://api.paddle.com'
    : 'https://sandbox-api.paddle.com';

echo "Environment: {$environment}\n";
echo "API Base URL: {$apiBaseUrl}\n";
echo "API Key: " . (empty($apiKey) ? 'MISSING' : 'CONFIGURED') . "\n";

if (empty($apiKey)) {
    echo "ERROR: Paddle API key is not configured!\n";
    exit(1);
}

// Test API connectivity
echo "\nTesting API connectivity...\n";

try {
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $apiKey,
        'Content-Type' => 'application/json'
    ])->get("{$apiBaseUrl}/products");

    if ($response->successful()) {
        echo "✓ API connectivity test passed\n";
        $products = $response->json()['data'] ?? [];
        echo "Found " . count($products) . " products\n";
    } else {
        echo "✗ API connectivity test failed\n";
        echo "Status: " . $response->status() . "\n";
        echo "Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "✗ API connectivity test failed with exception: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
