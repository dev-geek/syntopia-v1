<?php

namespace App\Services\Payment;

use App\Models\Package;
use App\Services\Payment\Gateways\PaddlePaymentGateway;
use Illuminate\Support\Facades\Log;

class PackageGatewayService
{
    /**
     * Get the gateway product/price ID for a specific payment gateway
     *
     * @param Package $package
     * @param string $gatewayName The gateway name (Paddle, FastSpring, PayProGlobal)
     * @return string|null
     */
    public function getGatewayProductId(Package $package, string $gatewayName): ?string
    {
        $packageKey = strtolower(str_replace([' ', '-'], '', $package->name));

        $gatewayConfig = config("payment.gateways.{$gatewayName}.product_ids", []);

        return $gatewayConfig[$packageKey] ?? null;
    }

    /**
     * Get Paddle price ID for a package
     * This method gets the price ID from config by matching package name
     *
     * @param Package $package
     * @param PaddlePaymentGateway|null $paddleGateway (kept for backward compatibility, not used)
     * @return string|null
     */
    public function getPaddlePriceId(Package $package, ?PaddlePaymentGateway $paddleGateway = null): ?string
    {
        $packageKey = strtolower(str_replace([' ', '-'], '', $package->name));

        $priceIds = config('payment.gateways.Paddle.price_ids', []);

        return $priceIds[$packageKey] ?? null;
    }
}

