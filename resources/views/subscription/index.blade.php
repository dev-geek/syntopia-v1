<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy"
        content="
      default-src 'self' data: gap: https://ssl.gstatic.com https://livebuzzstudio.test;
      style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com;
      font-src 'self' https://fonts.gstatic.com;
      script-src 'self' https://livebuzzstudio.test https://somedomain.com https://sbl.onfastspring.com https://cdn.jsdelivr.net https://cdn.paddle.com https://sandbox-cdn.paddle.com https://secure.payproglobal.com 'unsafe-inline' 'unsafe-eval';
      img-src 'self' https://syntopia.ai https://sbl.onfastspring.com data:;
      connect-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://sandbox-api.paddle.com https://sandbox-cdn.paddle.com;
      frame-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://sandbox-buy.paddle.com;
      frame-ancestors 'self' https://livebuzzstudio.test;
      media-src 'self' data: https://sbl.onfastspring.com;">
    <title>Syntopia Pricing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Payment Gateway Scripts -->
    @php
        $activeGateways = isset($payment_gateways) ? $payment_gateways->pluck('name')->toArray() : [];
    @endphp
    @if (in_array('FastSpring', $activeGateways))
        <script src="https://sbl.onfastspring.com/js/checkout/button.js"
            data-button-id="{{ $currentLoggedInUserPaymentGateway ?? 'FastSpring' }}"></script>
    @endif
    @if (in_array('Paddle', $activeGateways))
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"
            data-button-id="{{ $currentLoggedInUserPaymentGateway ?? 'Paddle' }}"></script>
    @endif
    @if (in_array('Pay Pro Global', $activeGateways))
        <script src="https://secure.payproglobal.com/js/custom/checkout.js"
            data-button-id="{{ $currentLoggedInUserPaymentGateway ?? 'Pay Pro Global' }}"></script>
    @endif

    <!-- FastSpring Integration -->
    @if ($activeGateway && $activeGateway->name === 'FastSpring')
        <script id="fsc-api" src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js" type="text/javascript"
            data-storefront="livebuzzstudio.test.onfastspring.com/popup-test-87654-payment" data-popup-closed="onFSPopupClosed"
            data-data-callback="handleFastSpringSuccess" data-debug="true"></script>
        <script>
            let currentProductPath = '';

            function processFastSpring(productPath) {
                try {
                    if (typeof fastspring === 'undefined' || !fastspring.builder) {
                        throw new Error('FastSpring is not properly initialized');
                    }
                    fastspring.builder.reset();
                    const packageName = productPath.replace('-plan', '').toLowerCase();
                    currentProductPath = productPath;
                    fastspring.builder.add(packageName);
                    setTimeout(() => {
                        fastspring.builder.checkout();
                    }, 500);
                } catch (error) {
                    throw error;
                }
            }

            function onFSPopupClosed(orderData) {
                try {
                    if (orderData && (orderData.reference || orderData.id)) {
                        const orderId = orderData.reference || orderData.id;

                        if (typeof fastspring !== 'undefined' && fastspring.builder) {
                            fastspring.builder.reset();
                        }
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/api/payments/success';
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                        if (csrfToken) {
                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = '_token';
                            csrfInput.value = csrfToken;
                            form.appendChild(csrfInput);
                        }
                        const gatewayInput = document.createElement('input');
                        gatewayInput.type = 'hidden';
                        gatewayInput.name = 'gateway';
                        gatewayInput.value = 'fastspring';
                        form.appendChild(gatewayInput);
                        const orderIdInput = document.createElement('input');
                        orderIdInput.type = 'hidden';
                        orderIdInput.name = 'orderId';
                        orderIdInput.value = orderId;
                        form.appendChild(orderIdInput);
                        const packageIdInput = document.createElement('input');
                        packageIdInput.type = 'hidden';
                        packageIdInput.name = 'package_id';
                        packageIdInput.value = 3;
                        form.appendChild(packageIdInput);
                        const paymentGatewayIdInput = document.createElement('input');
                        paymentGatewayIdInput.type = 'hidden';
                        paymentGatewayIdInput.name = 'payment_gateway_id';
                        paymentGatewayIdInput.value = "{{ $activeGateway->id ?? '' }}";
                        form.appendChild(paymentGatewayIdInput);
                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'info',
                                title: 'Payment Cancelled',
                                text: 'Your payment was cancelled. You can try again anytime.',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = "/pricing";
                            });
                        } else {
                            alert('Payment Cancelled: Your payment was cancelled. You can try again anytime.');
                            window.location.href = "/pricing";
                        }
                    }
                } catch (err) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Processing Error',
                            text: 'There was an error processing your payment. Please contact support if your payment was charged.',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = "/pricing";
                        });
                    } else {
                        alert('Processing Error: There was an error processing your payment. Please contact support.');
                        window.location.href = "/pricing";
                    }
                }
            }
        </script>
    @endif

    <!-- Paddle Integration -->
    @if ($activeGateway && $activeGateway->name === 'Paddle')
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    // Fix the environment setting
                    Paddle.Environment.set('{{ config('payment.gateways.Paddle.environment', 'sandbox') }}');
                    Paddle.Setup({
                        token: '{{ config('payment.gateways.Paddle.client_side_token') }}',
                    });

                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment System Error',
                        text: 'We cannot process payments at this moment. Our team has been notified. Please try again later.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        </script>
    @endif

    <!-- PayProGlobal Integration -->
    @if ($activeGateway && $activeGateway->name === 'Pay Pro Global')
        <script src="https://secure.payproglobal.com/js/custom/checkout.js"></script>
    @endif

    <style>
        .ppg-checkout-modal {
            z-index: 99999;
            display: none;
            background-color: transparent;
            border: 0px none transparent;
            visibility: visible;
            margin: 0px;
            padding: 0px;
            -webkit-tap-highlight-color: transparent;
            position: fixed;
            left: 0px;
            top: 0px;
            width: 100%;
            height: 100%;
        }

        .ppg-checkout-modal.ppg-show {
            display: block;
        }

        .ppg-btn-close {
            position: absolute;
            display: none;
            align-items: center;
            justify-content: center;
            top: 20px;
            right: 35px;
            background: rgb(0 0 0 / 35%);
            height: 50px;
            width: 50px;
            border: none;
            outline: none;
            cursor: pointer;
            z-index: 100000;
        }

        .ppg-btn-close.ppg-show {
            display: flex;
        }

        .ppg-btn-close img {
            width: 24px;
        }

        .ppg-iframe {
            width: 100%;
            height: 100%;
            border: 0;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .ppg-loader {
            position: absolute;
            top: calc(50% - 24px);
            left: calc(50% - 24px);
            width: 48px;
            height: 48px;
            border: 5px solid #000;
            border-bottom-color: transparent;
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: ppg-rotation 1s linear infinite;
            z-index: 100000;
        }

        @keyframes ppg-rotation {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #000;
            overflow-x: hidden;
        }

        .pricing-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 40px;
            border-bottom: 1px solid #e5e7eb;
        }

        .pricing-header img {
            height: 32px;
        }

        .pricing-header button {
            font-size: 14px;
            font-weight: 500;
            color: #2563eb;
            background: transparent;
            border: none;
            cursor: pointer;
        }

        .pricing-header button:hover {
            text-decoration: underline;
        }

        .pricing-wrapper {
            width: 100%;
            padding: 0px;
            border-bottom: 1px solid #EFE7FB;
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 50px 20px;
            border-left: 1px solid #EFE7FB;
            border-right: 1px solid #EFE7FB;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-top: 40px;
        }

        .badge-wrapper {
            text-align: center;
        }

        .pricing-badge {
            display: inline-block;
            padding: 7px 15px;
            margin-bottom: 10px;
            font-size: 16px;
            font-weight: 600;
            color: #5b0dd5;
            background-color: #f5f1fe;
            border: 1px solid #5b0dd5;
            border-radius: 999px;
            text-transform: uppercase;
        }

        .pricing-wrapper h2 {
            font-size: 65px;
        }

        .card {
            border: 1px solid #EFE7FB;
            border-radius: 10px;
            padding: 15px;
        }

        .card-light {
            background: linear-gradient(180deg, white 0%, #F2F2F7 100%);
            color: black;
        }

        .card-dark {
            background: linear-gradient(180deg, #E0347E 0%, #6B83DD 100%);
            color: white;
        }

        .card-dark.last {
            background: linear-gradient(180deg, #6B83DD 0%, #E0347E 100%);
        }

        .section-title {
            text-align: center;
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .section-subtitle {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 30px;
            font-size: 16px;
            color: #555;
        }

        .card h3 {
            font-size: 35px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .card p.price {
            font-size: 27px;
            font-weight: 600;
            color: black;
        }

        .card-dark p.price {
            color: white;
        }

        .checkout-button[aria-label="Current Plan"] {
            background-color: #4CAF50 !important;
            border-color: #4CAF50 !important;
            cursor: not-allowed;
        }

        .per-month {
            font-size: 16px;
            color: #5b0dd5;
            font-weight: 400;
        }

        .card-dark .per-month {
            color: white;
        }

        .btn {
            font-family: 'Inter', sans-serif;
            display: block;
            width: 100%;
            padding: 13px 0;
            font-size: 15px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 15px 0;
        }

        .btn.dark {
            background: black;
            color: white;
        }

        .btn.dark:hover {
            background: #5b0dd5;
        }

        .btn.purple {
            background: #5b0dd5;
            color: white;
        }

        .btn.purple:hover {
            background: white;
            color: #5b0dd5;
        }

        .btn.white {
            background: white;
            color: #5b0dd5;
        }

        .btn.white:hover {
            background: white;
        }

        .included-title {
            color: #5b0dd5;
            font-weight: 600;
            font-size: 15px;
            margin-top: 20px;
        }

        .card-dark .included-title {
            color: white;
        }

        .features {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .features li {
            font-size: 16px;
            font-weight: 400;
            line-height: 1.4;
            margin: 8px 0;
            display: flex;
            align-items: flex-start;
        }

        .icon::before {
            line-height: 1;
            margin-top: 3px;
        }

        .features li strong {
            font-weight: 700;
        }

        .icon::before {
            content: "✔";
            font-size: 18px;
            color: #5b0dd5;
            font-weight: bold;
            margin-right: 10px;
            line-height: 1.2;
        }

        .card-dark .icon::before {
            color: white;
        }

        @media (max-width: 1024px) {
            .pricing-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .pricing-grid {
                grid-template-columns: 1fr;
            }
        }

        footer {
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            padding: 40px 20px;
        }

        footer a {
            color: #2563eb;
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        .addons-wrapper {
            width: 100%;
            padding: 0px;
            border-bottom: 1px solid #EFE7FB;
        }

        .addons-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 40px;
        }

        .addon-card {
            background: linear-gradient(180deg, white 0%, #F2F2F7 100%);
            color: black;
            border: 1px solid #EFE7FB;
            border-radius: 10px;
            padding: 24px;
        }

        .addon-card h3 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .addon-price {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .addons-grid-wrapper {
            max-width: 65%;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .addons-grid {
                grid-template-columns: 1fr;
            }

            .addons-grid-wrapper {
                max-width: 100%;
            }

            .pricing-wrapper h2 {
                font-size: 40px;
            }
        }

        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            font-size: 14px;
            font-weight: 500;
            color: #2563eb;
            background: transparent;
            border: none;
            cursor: pointer;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
            z-index: 10;
        }

        .dropdown-menu a {
            display: block;
            padding: 5px 10px;
            color: #2563eb;
            text-decoration: none;
        }

        .dropdown-menu a:hover {
            background: #f0f0f0;
        }
    </style>
</head>

<body>
    <div class="pricing-header">
        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
        <div class="dropdown">
            <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">Account</button>
            <div class="dropdown-menu">
                <a href="{{ route('user.dashboard') }}">Dashboard</a>
                <a href="#"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a>
            </div>
        </div>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
            @csrf
        </form>
    </div>
    <div class="pricing-wrapper">
        <div class="container">
            @if (session('success') || session('error') || session('warning') || session('info') || $errors->any())
                @push('scripts')
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            @if (session('success'))
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success',
                                    text: '{{ addslashes(session('success')) }}',
                                    confirmButtonText: 'OK'
                                });
                            @elseif (session('error'))
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: '{{ addslashes(session('error')) }}',
                                    confirmButtonText: 'OK'
                                });
                            @elseif (session('warning'))
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Warning',
                                    text: '{{ addslashes(session('warning')) }}',
                                    confirmButtonText: 'OK'
                                });
                            @elseif (session('info'))
                                Swal.fire({
                                    icon: 'info',
                                    title: 'Information',
                                    text: '{{ addslashes(session('info')) }}',
                                    confirmButtonText: 'OK'
                                });
                            @elseif ($errors->any())
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Validation Error',
                                    html: '{!! addslashes(implode('<br>', $errors->all())) !!}',
                                    confirmButtonText: 'OK'
                                });
                            @endif
                        });
                    </script>
                @endpush
            @endif

            <div class="badge-wrapper">
                <div class="pricing-badge">PRICING PLANS</div>
            </div>
            <h2 class="section-title">
                {{ Route::currentRouteName() === 'subscription.upgrade' ? 'Upgrade Your Plan' : 'Plans For Every Type of Business' }}
            </h2>
            <p class="section-subtitle">SYNTOPIA creates hyperrealistic, interactive AI avatars that revolutionize how
                businesses and individuals connect with their audiences. Our avatars can:</p>
            <div class="pricing-grid">
                @foreach ($packages as $package)
                    <div class="card {{ $loop->iteration % 2 == 1 ? 'card-dark' : 'card-light' }}">
                        <h3>{{ $package->name }}</h3>
                        <p class="price">${{ number_format($package->price, 0) }} <span
                                class="per-month">/{{ $package->duration }}</span></p>
                        <button class="btn dark checkout-button" data-package="{{ $package->name }}"
                            {{ $currentPackage == $package->name || $package->isFree() ? 'disabled' : '' }}
                            data-action="{{ Route::currentRouteName() === 'subscription.upgrade' ? 'upgrade' : 'subscribe' }}">
                            {{ $package->name == 'Enterprise'
                                ? 'Get in Touch'
                                : ($currentPackage == $package->name
                                    ? 'Current Plan'
                                    : (Route::currentRouteName() === 'subscription.upgrade'
                                        ? 'Upgrade to ' . $package->name
                                        : 'Get Started')) }}
                        </button>
                        <p class="included-title">What's included</p>
                        <ul class="features">
                            @foreach ($package->features as $feature)
                                <li><span class="icon"></span> {{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @include('subscription.includes._addons')
    <footer>
        Having trouble? Contact us at
        <a href="mailto:support@syntopia.ai">support@syntopia.ai</a>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        document.addEventListener("DOMContentLoaded", function() {
            const currentPackage = "{{ $currentPackage ?? '' }}";
            const userOriginalGateway = "{{ $currentLoggedInUserPaymentGateway ?? '' }}";
            const activeGatewaysByAdmin = @json($activeGatewaysByAdmin ?? []);
            const selectedGateway = userOriginalGateway && userOriginalGateway.trim() !== "" ?
                userOriginalGateway :
                (activeGatewaysByAdmin.length > 0 ? activeGatewaysByAdmin[0] : null);

            document.querySelectorAll('.checkout-button').forEach(button => {
                button.addEventListener('click', function() {
                    if (this.disabled) return;
                    this.disabled = true;
                    const productPath = this.getAttribute('data-package');
                    processCheckout(productPath);
                    setTimeout(() => {
                        this.disabled = false;
                    }, 3000);
                });
            });

            const packageFromURL = "{{ $currentPackage ?? '' }}";
            if (packageFromURL && packageFromURL !== '' && packageFromURL !== currentPackage) {
                processCheckout(packageFromURL.toLowerCase() + "-plan");
            }

            function processCheckout(productPath, action) {
                try {
                    if (!selectedGateway) {
                        throw new Error('No payment gateway selected');
                    }
                    switch (selectedGateway) {
                        case 'FastSpring':
                            processFastSpring(productPath, action);
                            break;
                        case 'Paddle':
                            processPaddle(productPath, action);
                            break;
                        case 'Pay Pro Global':
                            processPayProGlobal(productPath, action);
                            break;
                        default:
                            throw new Error(`Unsupported payment gateway: ${selectedGateway}`);
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment Gateway Error',
                        text: error.message ||
                            'Payment gateway error. Please try again later or contact support.',
                        confirmButtonText: 'OK'
                    });
                }
            }

            function processFastSpring(productPath, action) {
                try {
                    if (typeof fastspring === 'undefined' || !fastspring.builder) {
                        throw new Error('FastSpring is not properly initialized');
                    }
                    fastspring.builder.reset();
                    const packageName = productPath.replace('-plan', '').toLowerCase();
                    const apiUrl = action === 'upgrade' ? `/subscription/upgrade/${packageName}` :
                        `/api/payments/fastspring/checkout/${packageName}`;
                    fetch(apiUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.productPath && data.orderId) {
                                fastspring.builder.add(data.productPath);
                                fastspring.builder.checkout(data.orderId);
                            } else {
                                throw new Error(data.error || 'Failed to initiate FastSpring checkout');
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Checkout Failed',
                                text: error.message ||
                                    'An error occurred while initiating FastSpring checkout.',
                                confirmButtonText: 'OK'
                            });
                        });
                } catch (error) {
                    throw error;
                }
            }


            function processPaddle(productPath) {
                const packageName = productPath.replace('-plan', '');
                const apiUrl = action === 'upgrade' ? `/subscription/upgrade/${packageName}` :
                    `/api/payments/paddle/checkout/${packageName}`;

                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'Authorization': 'Bearer {{ auth()->user() ? auth()->user()->createToken('api')->plainTextToken : '' }}',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || data.error || 'Unknown error occurred');
                        }
                        if (data.transaction_id && typeof Paddle !== 'undefined') {
                            Paddle.Checkout.open({
                                transactionId: data.transaction_id,
                                eventCallback: function(eventData) {
                                    console.log(eventData.data.event);
                                    if (eventData.data?.event?.name === 'checkout.completed') {
                                        window.location.href =
                                            `/payments/paddle/verify?transaction_id=${data.transaction_id}`;
                                    } else if (eventData.data?.event?.name === 'checkout.failed') {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Payment Failed',
                                            text: eventData.data.error ||
                                                'An error occurred during payment. Please try again.',
                                            confirmButtonText: 'OK'
                                        }).then(() => {
                                            window.location.reload();
                                        });
                                    } else if (eventData.data?.event?.name === 'checkout.closed' &&
                                        !eventData.data.success) {
                                        Swal.fire({
                                            icon: 'info',
                                            title: 'Payment Cancelled',
                                            text: 'Your payment was cancelled. You can try again anytime.',
                                            confirmButtonText: 'OK'
                                        }).then(() => {
                                            window.location.reload();
                                        });
                                    }
                                }
                            });
                        } else {
                            throw new Error('No transaction ID provided');
                        }
                    })
                    .catch(error => {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Checkout Failed',
                                text: error.message ||
                                    'An error occurred while processing your checkout. Please try again later.',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            alert('Checkout Failed: ' + (error.message ||
                                'An error occurred while processing your checkout. Please try again later.'
                            ));
                        }
                    });
            }
            // Payproglobal processing
            function processPayProGlobal(productPath) {
                console.log('[PayProGlobal] Starting payment process for product:', productPath);

                const packageName = productPath.replace('-plan', '');
                const apiUrl = action === 'upgrade' ? `/subscription/upgrade/${packageName}` :
                    `/api/payments/payproglobal/checkout/${packageName}`;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                if (!csrfToken) {
                    console.error('[PayProGlobal] CSRF token not found');
                    Swal.fire({
                        icon: 'error',
                        title: 'Configuration Error',
                        text: 'Security token not found. Please refresh the page and try again.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Define success and cancel callbacks
                window.paymentSuccess = function() {
                    console.log('[PayProGlobal] Payment successful for package:', packageName);
                    Swal.fire({
                        icon: 'success',
                        title: 'Payment Successful',
                        text: 'Your payment was processed successfully.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = '/user/dashboard';
                    });
                };

                window.paymentCancelled = function() {
                    console.log('[PayProGlobal] Payment cancelled for package:', packageName);
                    Swal.fire({
                        icon: 'info',
                        title: 'Payment Cancelled',
                        text: 'Your payment was cancelled. You can try again anytime.',
                        confirmButtonText: 'OK'
                    });
                };

                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            package: packageName
                        })
                    })
                    .then(response => {
                        console.log('[PayProGlobal] Response status:', response.status);
                        if (response.status === 401) {
                            console.error('[PayProGlobal] Authentication error: User not logged in');
                            throw new Error(
                                'You must be logged in to make a purchase. Please log in and try again.');
                        }
                        if (!response.ok) {
                            console.error('[PayProGlobal] HTTP error:', response.status, response.statusText);
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('[PayProGlobal] Checkout response:', data);
                        if (!data.success || !data.checkoutUrl) {
                            console.error('[PayProGlobal] Checkout failed:', data.message ||
                                'No checkout URL received');
                            throw new Error(data.message || 'No checkout URL received');
                        }
                        console.log('[PayProGlobal] Opening checkout popup with URL:', data.checkoutUrl);
                        // Open popup instead of redirecting
                        const popup = window.open(
                            data.checkoutUrl,
                            'PayProGlobal Checkout',
                            'width=800,height=600,location=no,toolbar=no,menubar=no,scrollbars=yes'
                        );
                        if (!popup) {
                            console.warn('[PayProGlobal] Popup blocked for checkout');
                            Swal.fire({
                                icon: 'warning',
                                title: 'Popup Blocked',
                                text: 'Please allow popups for this site and try again.',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            console.log('[PayProGlobal] Checkout popup opened successfully');
                        }
                    })
                    .catch(error => {
                        console.error('[PayProGlobal] Error during checkout:', error.message);
                        Swal.fire({
                            icon: 'error',
                            title: 'Checkout Error',
                            text: error.message ||
                                'Failed to initiate payment. Please try again or contact support.',
                            confirmButtonText: 'OK'
                        });
                    });
            }
        });
    </script>
    <script>
        document.querySelector('.dropdown-toggle').addEventListener('click', function() {
            const menu = document.querySelector('.dropdown-menu');
            const isOpen = menu.style.display === 'block';
            menu.style.display = isOpen ? 'none' : 'block';
            this.setAttribute('aria-expanded', !isOpen);
        });

        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.dropdown');
            if (!dropdown.contains(event.target)) {
                document.querySelector('.dropdown-menu').style.display = 'none';
                document.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
            }
        });
    </script>
</body>

</html>
