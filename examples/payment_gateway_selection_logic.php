<?php

/**
 * Example: Payment Gateway Selection Logic
 *
 * This demonstrates how the system selects payment gateways based on customer type:
 * - Returning customers: Use their original payment gateway (even if admin made a different one active)
 * - New customers: Use the currently active gateway set by admin
 */

use App\Models\User;
use App\Models\PaymentGateways;

// Scenario 1: Returning Customer with FastSpring (admin made Paddle active)
$returningUser = User::find(1); // User who previously paid with FastSpring
$adminActiveGateway = PaymentGateways::where('is_active', true)->first(); // Paddle (active by admin)

if ($returningUser->isReturningCustomer()) {
    echo "=== RETURNING CUSTOMER SCENARIO ===\n";
    echo "User's original gateway: " . ($returningUser->paymentGateway ? $returningUser->paymentGateway->name : 'None') . "\n";
    echo "Admin's active gateway: " . ($adminActiveGateway ? $adminActiveGateway->name : 'None') . "\n";

    // Logic: Use original gateway even if admin made a different one active
    $selectedGateway = $returningUser->paymentGateway ?: $adminActiveGateway;
    echo "Selected gateway: " . ($selectedGateway ? $selectedGateway->name : 'None') . "\n";
    echo "Reason: Returning customer - using their preferred payment method\n\n";
}

// Scenario 2: New Customer (no payment history)
$newUser = User::find(2); // User with no payment history
$adminActiveGateway = PaymentGateways::where('is_active', true)->first(); // Paddle (active by admin)

if ($newUser->isNewCustomer()) {
    echo "=== NEW CUSTOMER SCENARIO ===\n";
    echo "User's original gateway: " . ($newUser->paymentGateway ? $newUser->paymentGateway->name : 'None') . "\n";
    echo "Admin's active gateway: " . ($adminActiveGateway ? $adminActiveGateway->name : 'None') . "\n";

    // Logic: Use admin's active gateway
    $selectedGateway = $adminActiveGateway;
    echo "Selected gateway: " . ($selectedGateway ? $selectedGateway->name : 'None') . "\n";
    echo "Reason: New customer - using admin's currently active gateway\n\n";
}

// Scenario 3: Returning Customer with inactive original gateway
$returningUserInactive = User::find(3); // User whose original gateway is now inactive
$adminActiveGateway = PaymentGateways::where('is_active', true)->first(); // Paddle (active by admin)

if ($returningUserInactive->isReturningCustomer()) {
    echo "=== RETURNING CUSTOMER WITH INACTIVE GATEWAY ===\n";
    echo "User's original gateway: " . ($returningUserInactive->paymentGateway ? $returningUserInactive->paymentGateway->name : 'None') . "\n";
    echo "Original gateway active: " . ($returningUserInactive->paymentGateway ? ($returningUserInactive->paymentGateway->is_active ? 'Yes' : 'No') : 'N/A') . "\n";
    echo "Admin's active gateway: " . ($adminActiveGateway ? $adminActiveGateway->name : 'None') . "\n";

    // Logic: Still use original gateway even if inactive (for returning customers)
    $selectedGateway = $returningUserInactive->paymentGateway ?: $adminActiveGateway;
    echo "Selected gateway: " . ($selectedGateway ? $selectedGateway->name : 'None') . "\n";
    echo "Reason: Returning customer - continuing with their preferred method\n\n";
}

// Controller Logic Example
function getAppropriatePaymentGateway($user, $type) {
    // If user is a returning customer, try to use their original payment gateway
    if ($user->isReturningCustomer() && $user->paymentGateway) {
        // Check if their original gateway is still active
        if ($user->paymentGateway->is_active) {
            return $user->paymentGateway;
        } else {
            // Original gateway is no longer active, but we'll still use it for returning customers
            // This allows them to continue with their preferred payment method
            return $user->paymentGateway;
        }
    }

    // For new customers or returning customers without a payment gateway, use admin's active gateway
    $activeGateway = PaymentGateways::where('is_active', true)->first();

    if (!$activeGateway) {
        // No active gateway found, try to get any available gateway
        $activeGateway = PaymentGateways::first();
    }

    return $activeGateway;
}

// Usage in controller
echo "=== CONTROLLER USAGE EXAMPLE ===\n";

$user = User::find(1);
$gateway = getAppropriatePaymentGateway($user, 'new');

echo "User: " . $user->name . "\n";
echo "Is returning customer: " . ($user->isReturningCustomer() ? 'Yes' : 'No') . "\n";
echo "Selected gateway: " . ($gateway ? $gateway->name : 'None') . "\n";
echo "Using original gateway: " . ($user->paymentGateway && $gateway && $user->paymentGateway->id === $gateway->id ? 'Yes' : 'No') . "\n";
echo "Using admin gateway: " . (!$user->isReturningCustomer() || !$user->paymentGateway || ($gateway && $user->paymentGateway && $user->paymentGateway->id !== $gateway->id) ? 'Yes' : 'No') . "\n";

// View Data Example
$viewData = [
    'selectedPaymentGateway' => $gateway ? $gateway->name : null,
    'isUsingOriginalGateway' => $user->isReturningCustomer() && $user->paymentGateway && $gateway && $user->paymentGateway->id === $gateway->id,
    'isUsingAdminGateway' => !$user->isReturningCustomer() || !$user->paymentGateway || ($gateway && $user->paymentGateway && $user->paymentGateway->id !== $gateway->id)
];

echo "\n=== VIEW DATA ===\n";
echo "selectedPaymentGateway: " . $viewData['selectedPaymentGateway'] . "\n";
echo "isUsingOriginalGateway: " . ($viewData['isUsingOriginalGateway'] ? 'true' : 'false') . "\n";
echo "isUsingAdminGateway: " . ($viewData['isUsingAdminGateway'] ? 'true' : 'false') . "\n";

// Blade Template Example
echo "\n=== BLADE TEMPLATE EXAMPLE ===\n";
echo "Payment method: " . $viewData['selectedPaymentGateway'] . "\n";
if ($viewData['isUsingOriginalGateway']) {
    echo "(your preferred method)\n";
} elseif ($viewData['isUsingAdminGateway']) {
    echo "(currently available)\n";
}
