<?php

namespace App\Contracts\Payment;

use App\Models\User;
use App\Models\Package;
use App\Models\Order;

/**
 * @method self setUser(User $user)
 * @method self setOrder(Order $order)
 */
interface PaymentGatewayInterface
{
    public function processPayment(array $paymentData, bool $returnRedirect = true);
}
