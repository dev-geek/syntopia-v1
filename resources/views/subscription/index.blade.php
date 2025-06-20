<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy" content="
        default-src 'self' data: gap: https://ssl.gstatic.com https://livebuzzstudio.test;
        style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com;
        font-src 'self' https://fonts.gstatic.com;
        script-src 'self' https://livebuzzstudio.test https://somedomain.com https://sbl.onfastspring.com https://cdn.jsdelivr.net https://cdn.paddle.com https://sandbox-cdn.paddle.com https://secure.payproglobal.com 'unsafe-inline' 'unsafe-eval';
        img-src 'self' https://syntopia.ai https://sbl.onfastspring.com data:;
        connect-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://sandbox-api.paddle.com https://sandbox-cdn.paddle.com;
        frame-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://sandbox-buy.paddle.com;
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
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    @endif
    @if (in_array('Pay Pro Global', $activeGateways))
    <script src="https://secure.payproglobal.com/js/custom/checkout.js"></script>
    @endif

    <!-- FastSpring Integration -->
    @if ($activeGateway && $activeGateway->name === 'FastSpring')
    <script id="fsc-api" src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js" type="text/javascript"
        data-storefront="livebuzzstudio.test.onfastspring.com/popup-test-87654-payment"
        data-popup-closed="onFSPopupClosed" data-data-callback="handleFastSpringSuccess" data-debug="true"></script>
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
            <div class="badge-wrapper">
                <div class="pricing-badge">PRICING PLANS</div>
            </div>
            @include('components.alert-messages')
            <h2 class="section-title">Plans For Every Type of Business</h2>
            <p class="section-subtitle">SYNTOPIA creates hyperrealistic, interactive AI avatars that revolutionize how
                businesses and individuals connect with their audiences. Our avatars can:</p>
            <div class="pricing-grid">
                @foreach ($packages as $package)
                <div class="card {{ $loop->iteration % 2 == 1 ? 'card-dark' : 'card-light' }}">
                    <h3>{{ $package->name }}</h3>
                    <p class="price">${{ number_format($package->price, 0) }} <span class="per-month">/{{
                            $package->duration }}</span></p>
                    <button class="btn dark checkout-button" data-package="{{ $package->name }}" {{
                        $currentPackage==$package->name ? 'disabled' : '' }}>
                        {{ $package->name == 'Enterprise'
                        ? 'Get in Touch'
                        : ($currentPackage == $package->name
                        ? 'Activated'
                        : 'Get Started') }}
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
            const userOriginalGateway = "{{ $userOriginalGateway ?? '' }}";
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

            function processCheckout(productPath) {
                try {
                    if (!selectedGateway) {
                        throw new Error('No payment gateway selected');
                    }
                    switch (selectedGateway) {
                        case 'FastSpring':
                            processFastSpring(productPath);
                            break;
                        case 'Paddle':
                            processPaddle(productPath);
                            break;
                        case 'Pay Pro Global':
                            processPayProGlobal(productPath);
                            break;
                        default:
                            throw new Error(`Unsupported payment gateway: ${selectedGateway}`);
                    }
                } catch (error) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Payment Gateway Error',
                            text: error.message ||
                                'Payment gateway error. Please try again later or contact support.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Payment Gateway Error: ' + (error.message ||
                            'Payment gateway error. Please try again later or contact support.'));
                    }
                }
            }

            function processFastSpring(productPath) {
                try {
                    if (typeof fastspring === 'undefined' || !fastspring.builder) {
                        throw new Error('FastSpring is not properly initialized');
                    }
                    fastspring.builder.reset();
                    const packageName = productPath.replace('-plan', '').toLowerCase();
                    fastspring.builder.add(packageName);
                    setTimeout(() => {
                        fastspring.builder.checkout();
                    }, 500);
                } catch (error) {
                    throw error;
                }
            }

            function processPaddle(productPath) {
                const packageName = productPath.replace('-plan', '');
                const apiUrl = `/api/payments/paddle/checkout/${packageName}`;

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
                                    if (eventData.event === 'checkout.completed') {
                                        window.location.href = '/user/dashboard';
                                    } else if (eventData.event === 'checkout.failed') {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Payment Failed',
                                            text: eventData.data.error ||
                                                'An error occurred during payment. Please try again.',
                                            confirmButtonText: 'OK'
                                        }).then(() => {
                                            window.location.reload();
                                        });
                                    } else if (eventData.event === 'checkout.closed' && !eventData
                                        .data.success) {
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

            function processPayProGlobal(productPath) {
    console.log('[PayProGlobal] Starting payment process for product:', productPath);
    const packageName = productPath.replace('-plan', '');
    const apiUrl = `/api/payments/payproglobal/checkout/${packageName}`;
    const paymentId = 'pay-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    let paymentWindow = null;
    let paymentCompleted = false;
    let paymentVerified = false;
    let checkInterval = null;
    const debugMode = true; // Set to false in production

    // Debug logging
    const debugLog = (...args) => {
        if (debugMode) {
            console.log('[PayProGlobal]', ...args);
        }
    };

    // Function to clean up resources
    const cleanup = () => {
        debugLog('Cleaning up resources');
        window.removeEventListener('message', messageHandler);
        window.removeEventListener('beforeunload', cleanup);
        if (checkInterval) clearInterval(checkInterval);
        if (paymentWindow && !paymentWindow.closed) {
            paymentWindow.close();
        }
        localStorage.removeItem('pendingPayment');
    };

    // Function to verify payment status via API
    const verifyPaymentStatus = async () => {
        debugLog('Verifying payment status for paymentId:', paymentId);
        try {
            const response = await fetch(`/api/payments/verify-payproglobal/${paymentId}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Authorization': 'Bearer {{ auth()->user() ? auth()->user()->createToken('api')->plainTextToken : '' }}'
                }
            });
            const data = await response.json();
            debugLog('Payment verification response:', data);
            return data.status === 'completed';
        } catch (error) {
            debugLog('Error verifying payment:', error);
            return false;
        }
    };

    // Function to handle payment completion
    const handlePaymentComplete = async (source) => {
        debugLog(`Payment complete triggered from: ${source}`);
        if (paymentCompleted) {
            debugLog('Payment already marked as completed, skipping');
            return;
        }

        paymentCompleted = true;

        // Verify payment status before proceeding
        const isVerified = await verifyPaymentStatus();
        if (!isVerified) {
            debugLog('Payment verification failed');
            Swal.fire({
                icon: 'error',
                title: 'Payment Verification Failed',
                text: 'Your payment could not be verified. Please contact support.',
                confirmButtonText: 'OK'
            }).then(() => {
                cleanup();
                window.location.href = '/pricing';
            });
            return;
        }

        // Send transaction details to server
        const transactionData = {
            transaction_id: paymentId,
            paymentId: paymentId,
            package: packageName,
            timestamp: Date.now(),
            _token: csrfToken
        };

        debugLog('Sending transaction details to server:', transactionData);

        const successUrl = new URL('{{ route('payments.success') }}');
        const params = new URLSearchParams();
        params.append('gateway', 'payproglobal');
        params.append('order_id', paymentId);
        params.append('user_id', '{{ Auth::id() }}');
        params.append('package', packageName);
        params.append('payment_id', paymentId);
        params.append('payment_gateway_id', '{{ $activeGateway->id ?? '' }}');
        params.append('redirect_to', '/user/dashboard');

        successUrl.search = params.toString();

        fetch(successUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'Authorization': 'Bearer {{ auth()->user() ? auth()->user()->createToken('api')->plainTextToken : '' }}'
            },
            body: JSON.stringify(transactionData)
        })
        .then(response => {
            debugLog('Server response status:', response.status);
            if (!response.ok) {
                return response.json().then(err => { throw err; });
            }
            return response.json();
        })
        .then(data => {
            debugLog('Transaction details saved:', data);
            localStorage.removeItem('pendingPayment');

            if (data.status === 'success') {
                debugLog('Showing success message');
                Swal.fire({
                    icon: 'success',
                    title: 'Payment Completed!',
                    text: data.message || 'Your subscription has been successfully updated.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    cleanup();
                    window.location.href = data.redirect || '/user/dashboard';
                });
            } else if (data.status === 'pending') {
                debugLog('Payment is pending verification');
                Swal.fire({
                    icon: 'info',
                    title: 'Payment Processing',
                    text: data.message || 'Your payment is being processed. Please check your email for confirmation.',
                    confirmButtonText: 'OK'
                }).then(() => {
                    cleanup();
                    window.location.href = data.redirect || '/user/dashboard';
                });
            } else {
                throw new Error(data.message || 'An unknown error occurred');
            }
        })
        .catch(error => {
            debugLog('Error saving transaction details:', error);
            Swal.fire({
                icon: 'error',
                title: 'Payment Error',
                text: error.message || 'Your payment was processed, but there was an issue updating your subscription. Please contact support.',
                confirmButtonText: 'OK'
            }).then(() => {
                cleanup();
                window.location.href = '/pricing';
            });
        });
    };

    // Function to check payment status
    const checkPaymentStatus = async () => {
        debugLog('Checking payment status...');
        try {
            if (paymentWindow && paymentWindow.closed) {
                debugLog('Payment window was closed by user');
                clearInterval(checkInterval);

                if (!paymentCompleted && !paymentVerified) {
                    debugLog('Checking payment status before showing cancel message');
                    // Delay verification to allow webhook processing
                    await new Promise(resolve => setTimeout(resolve, 5000));
                    const isVerified = await verifyPaymentStatus();
                    if (isVerified) {
                        debugLog('Payment was verified before window close');
                        paymentVerified = true;
                        handlePaymentComplete('verified');
                    } else {
                        debugLog('Payment was not completed or verified');
                        Swal.fire({
                            icon: 'info',
                            title: 'Checkout Cancelled',
                            text: 'The payment process was cancelled. You can try again anytime.',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            cleanup();
                            window.location.href = '/pricing';
                        });
                    }
                } else if (paymentVerified) {
                    debugLog('Payment was verified before window close');
                    handlePaymentComplete('verified');
                }
                return;
            }

            try {
                const url = paymentWindow.location.href;
                debugLog('Payment window URL:', url);

                if (url.includes('payproglobal.com/thankyou') ||
                    url.includes('payproglobal.com/thank_you') ||
                    url.includes('payproglobal.com/success')) {
                    debugLog('Detected success URL, completing payment');
                    paymentVerified = true;
                    handlePaymentComplete('URL check');
                }
            } catch (e) {
                debugLog('Cross-origin error, relying on API verification');
            }
        } catch (e) {
            debugLog('Error in checkPaymentStatus:', e);
        }
    };

    // Open payment window
    const width = 1000;
    const height = 700;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;

    try {
        paymentWindow = window.open(
            'about:blank',
            'PayProGlobalCheckout',
            `width=${width},height=${height},top=${top},left=${left},scrollbars=yes,resizable=yes`
        );

        if (!paymentWindow) {
            debugLog('Payment window blocked by browser');
            Swal.fire({
                icon: 'error',
                title: 'Popup Blocked',
                text: 'Please allow popups for this site in your browser settings and try again.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Show loading message in payment window
        paymentWindow.document.write(`
            <html>
                <head>
                    <title>Processing Payment...</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            display: flex; 
                            justify-content: center; 
                            align-items: center; 
                            height: 100vh; 
                            margin: 0; 
                            background: #f5f5f5;
                        }
                        .loader {
                            text-align: center;
                            padding: 20px;
                        }
                        .spinner {
                            border: 5px solid #f3f3f3;
                            border-top: 5px solid #3498db;
                            border-radius: 50%;
                            width: 50px;
                            height: 50px;
                            animation: spin 1s linear infinite;
                            margin: 0 auto 20px;
                        }
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                    </style>
                </head>
                <body>
                    <div class="loader">
                        <div class="spinner"></div>
                        <h2>Processing Your Payment</h2>
                        <p>Please wait while we connect you to our secure payment processor...</p>
                    </div>
                </body>
            </html>
        `);
    } catch (windowError) {
        debugLog('Error opening payment window:', windowError);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to open payment window: ' + windowError.message,
            confirmButtonText: 'OK'
        });
        return;
    }

    // Start checking payment status
    checkInterval = setInterval(checkPaymentStatus, 3000); // Increased interval to 3 seconds

    // Fetch checkout URL
    debugLog('Fetching checkout URL from:', apiUrl);
    fetch(apiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'Authorization': 'Bearer {{ auth()->user() ? auth()->user()->createToken('api')->plainTextToken : '' }}'
        },
        credentials: 'same-origin',
        body: JSON.stringify({ package: packageName })
    })
    .then(async response => {
        debugLog('Received response, status:', response.status);
        const responseData = await response.json().catch(e => ({}));

        if (!response.ok) {
            const errorMsg = responseData.message || 'Network response was not ok';
            debugLog('Error response:', responseData);
            throw new Error(errorMsg);
        }

        debugLog('Response data:', responseData);
        return responseData;
    })
    .then(data => {
        debugLog('Processing checkout data:', data);

        if (!data.checkoutUrl) {
            throw new Error('No checkout URL received in response');
        }

        debugLog('Redirecting to checkout URL:', data.checkoutUrl);

        try {
            const successUrl = new URL(data.checkoutUrl);
            const params = new URLSearchParams(successUrl.search);
            params.set('payment_id', paymentId);
            successUrl.search = params.toString();

            debugLog('Redirecting to payment page with payment ID:', paymentId);
            paymentWindow.location.href = successUrl.toString();

            // Set payment timeout
            const timeoutDuration = 30 * 60 * 1000; // 30 minutes
            debugLog(`Setting payment timeout for ${timeoutDuration}ms`);

            setTimeout(() => {
                if (!paymentCompleted) {
                    debugLog('Payment session timeout reached');
                    localStorage.removeItem('pendingPayment');
                    if (paymentWindow && !paymentWindow.closed) {
                        debugLog('Closing payment window due to timeout');
                        paymentWindow.close();
                    }
                    Swal.fire({
                        icon: 'warning',
                        title: 'Session Expired',
                        text: 'The payment session has timed out. Please try again if your payment was not processed.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        cleanup();
                    });
                }
            }, timeoutDuration);
        } catch (redirectError) {
            debugLog('Error redirecting to checkout:', redirectError);
            throw new Error('Failed to redirect to payment page: ' + redirectError.message);
        }
    })
    .catch(error => {
        debugLog('Payment processing error:', error);
        try {
            if (paymentWindow && !paymentWindow.closed) {
                paymentWindow.close();
            }
        } catch (closeError) {
            debugLog('Error closing payment window:', closeError);
        }

        const errorMessage = error.message || 'Failed to process payment. Please try again or contact support.';
        debugLog('Showing error to user:', errorMessage);

        Swal.fire({
            icon: 'error',
            title: 'Payment Error',
            text: errorMessage,
            confirmButtonText: 'OK'
        }).then(() => {
            cleanup();
        });
    });

    // Message handler for payment completion
    const messageHandler = async (event) => {
        debugLog('Received message from payment window:', event.data);

        // Only process messages from trusted origins
        if (event.origin !== 'https://store.payproglobal.com' && event.origin !== window.location.origin) {
            debugLog('Message from untrusted origin:', event.origin);
            return;
        }

        if (event.data && typeof event.data === 'object' &&
            event.data.type === 'payment-completed' &&
            event.data.paymentId === paymentId) {
            debugLog('Payment completed via message');
            paymentVerified = true;
            handlePaymentComplete('message');
        }
    };

    window.addEventListener('message', messageHandler);
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
