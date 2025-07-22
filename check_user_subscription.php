<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== Checking User Subscription Status ===\n";

$user = User::find(28);

if ($user) {
    echo "User ID: " . $user->id . "\n";
    echo "Email: " . $user->email . "\n";
    echo "Is subscribed: " . ($user->is_subscribed ? 'Yes' : 'No') . "\n";
    echo "Subscription ID: " . ($user->subscription_id ?? 'None') . "\n";
    echo "Package ID: " . ($user->package_id ?? 'None') . "\n";
    echo "Payment Gateway ID: " . ($user->payment_gateway_id ?? 'None') . "\n";

    if ($user->paymentGateway) {
        echo "Payment Gateway: " . $user->paymentGateway->name . "\n";
    } else {
        echo "Payment Gateway: None\n";
    }

    if ($user->package) {
        echo "Package: " . $user->package->name . "\n";
    } else {
        echo "Package: None\n";
    }

    echo "Subscription starts at: " . ($user->subscription_starts_at ?? 'None') . "\n";
    echo "Subscription ends at: " . ($user->subscription_ends_at ?? 'None') . "\n";
} else {
    echo "User not found!\n";
}

echo "\n=== Check completed ===\n";
