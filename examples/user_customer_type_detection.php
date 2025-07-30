<?php

/**
 * Example: How to detect returning users vs new users
 *
 * This file demonstrates various ways to identify if a user is a returning customer
 * who has previously purchased packages versus a new user who hasn't bought anything.
 */

use App\Models\User;
use Illuminate\Support\Facades\Auth;

// Method 1: Using the new User model methods (Recommended)
$user = Auth::user(); // or User::find($userId);

if ($user->isReturningCustomer()) {
    echo "This is a returning customer who has purchased before.";

    // Get detailed purchase history
    $purchaseHistory = $user->getPurchaseHistory();
    echo "Total orders: " . $purchaseHistory['total_orders'];
    echo "Completed orders: " . $purchaseHistory['completed_orders'];
    echo "Total spent: $" . $purchaseHistory['total_spent'];
    echo "Current package: " . $purchaseHistory['current_package'];
    echo "Payment gateway: " . $purchaseHistory['payment_gateway'];
} else {
    echo "This is a new customer who hasn't purchased anything yet.";
}

// Method 2: Check specific indicators
$hasCompletedOrders = $user->orders()->where('status', 'completed')->exists();
$hasAnyOrderHistory = $user->orders()->exists();
$hasPaymentGateway = $user->payment_gateway_id !== null;
$hasPackage = $user->package_id !== null;
$isCurrentlySubscribed = $user->is_subscribed;

if ($hasCompletedOrders || $hasAnyOrderHistory || $hasPaymentGateway || $hasPackage || $isCurrentlySubscribed) {
    echo "Returning customer detected.";
} else {
    echo "New customer detected.";
}

// Method 3: Get detailed order information
$orders = $user->orders()->with(['package', 'paymentGateway'])->get();

if ($orders->count() > 0) {
    echo "User has " . $orders->count() . " orders in history.";

    foreach ($orders as $order) {
        echo "Order ID: " . $order->id;
        echo "Status: " . $order->status;
        echo "Amount: $" . $order->amount;
        echo "Package: " . $order->package->name;
        echo "Payment Gateway: " . $order->paymentGateway->name;
        echo "Date: " . $order->created_at->format('Y-m-d H:i:s');
    }
} else {
    echo "User has no order history.";
}

// Method 4: Check subscription status
if ($user->hasActiveSubscription()) {
    echo "User has an active subscription.";
} else {
    echo "User does not have an active subscription.";
}

// Method 5: Conditional logic based on customer type
if ($user->isNewCustomer()) {
    // Show welcome message and first-time purchase offers
    echo "Welcome! Here are our packages for new customers...";
} else {
    // Show upgrade/downgrade options for returning customers
    echo "Welcome back! Here are your upgrade options...";

    if ($user->hasActiveSubscription()) {
        echo "You can upgrade or downgrade your current package.";
    } else {
        echo "Your subscription has expired. You can renew or choose a new package.";
    }
}

// Method 6: In Blade templates
/*
@if($user->isReturningCustomer())
    <div class="returning-customer-banner">
        <h3>Welcome back, {{ $user->name }}!</h3>
        <p>You have {{ $user->getPurchaseHistory()['completed_orders'] }} completed orders.</p>
        <p>Total spent: ${{ $user->getPurchaseHistory()['total_spent'] }}</p>
    </div>
@else
    <div class="new-customer-banner">
        <h3>Welcome to our platform!</h3>
        <p>Choose your first package to get started.</p>
    </div>
@endif
*/

// Method 7: API response example
$customerData = [
    'user_id' => $user->id,
    'is_returning_customer' => $user->isReturningCustomer(),
    'is_new_customer' => $user->isNewCustomer(),
    'has_active_subscription' => $user->hasActiveSubscription(),
    'purchase_history' => $user->getPurchaseHistory(),
    'current_package' => $user->package ? $user->package->name : null,
    'payment_gateway' => $user->paymentGateway ? $user->paymentGateway->name : null,
];

// Return as JSON for API
// return response()->json($customerData);
