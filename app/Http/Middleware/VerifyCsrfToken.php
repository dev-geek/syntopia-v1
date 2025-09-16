<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/verify-code',
        '/payments/success',
        'payment/webhook/fastspring',
        'payment/webhook/payproglobal',
        'webhooks/paddle',
        'webhooks/fastspring',
        'api/webhooks/payproglobal',
    ];
}
