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
                Paddle.Environment.set('{{ config('payment.gateways.Paddle.environment ', 'sandbox ') }}');
                Paddle.Setup({
                    token: '{{ config('payment.gateways.Paddle.client_side_token ') }}',
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
                                    'Authorization': 'Bearer {{ auth()->user() ? auth()->user()->createToken('
                                    api ')->plainTextToken : '
                                    ' }}',
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
                                                window.location.href = '/user/dashboard';
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
                                            } else if (eventData.data?.event?.name === 'checkout.closed' && !eventData
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

                    // Enhanced PayProGlobal payment processing function
                    function processPayProGlobal(productPath) {
                        console.log('[PayProGlobal] Starting payment process for product:', productPath);

                        const packageName = productPath.replace('-plan', '');
                        const apiUrl = `/api/payments/payproglobal/checkout/${packageName}`;

                        let paymentWindow = null;
                        let paymentCompleted = false;
                        let checkInterval = null;
                        let paymentReference = null;

                        // Helper function for debugging
                        const debugLog = (message, data = null) => {
                            console.log(`[PayProGlobal Debug] ${message}`, data || '');
                        };

                        // Cleanup function to clear intervals and close windows
                        const cleanup = () => {
                            if (checkInterval) {
                                clearInterval(checkInterval);
                                checkInterval = null;
                            }
                            if (paymentWindow && !paymentWindow.closed) {
                                try {
                                    paymentWindow.close();
                                } catch (e) {
                                    debugLog('Error closing payment window:', e);
                                }
                            }
                        };

                        // Function to verify payment status via our API
                        const verifyPaymentStatus = async (reference) => {
                            try {
                                debugLog('Verifying payment status for reference:', reference);

                                const response = await fetch(`/api/payments/verify-payproglobal/${reference}`, {
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

                                return {
                                    isCompleted: data.status === 'completed',
                                    isPending: data.status === 'pending',
                                    isError: data.status === 'error',
                                    message: data.message,
                                    orderId: data.order_id
                                };
                            } catch (error) {
                                debugLog('Error verifying payment status:', error);
                                return {
                                    isCompleted: false,
                                    isPending: false,
                                    isError: true
                                };
                            }
                        };

                        // Function to handle successful payment completion
                        const handlePaymentSuccess = async (source) => {
                            debugLog(`Payment success detected from: ${source}`);

                            if (paymentCompleted) {
                                debugLog('Payment already processed, skipping duplicate');
                                return;
                            }

                            paymentCompleted = true;
                            cleanup();

                            // Verify the payment one more time to be sure
                            if (paymentReference) {
                                const verification = await verifyPaymentStatus(paymentReference);

                                if (verification.isCompleted) {
                                    debugLog('Payment verification successful, redirecting to dashboard');

                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Payment Completed!',
                                        text: 'Your subscription has been successfully activated.',
                                        confirmButtonText: 'Continue to Dashboard',
                                        allowOutsideClick: false
                                    }).then(() => {
                                        window.location.href = '/user/dashboard';
                                    });
                                    return;
                                }

                                if (verification.isPending) {
                                    debugLog('Payment is pending verification');

                                    Swal.fire({
                                        icon: 'info',
                                        title: 'Payment Processing',
                                        text: 'Your payment is being processed. Please check your dashboard in a few minutes.',
                                        confirmButtonText: 'Go to Dashboard'
                                    }).then(() => {
                                        window.location.href = '/user/dashboard';
                                    });
                                    return;
                                }
                            }

                            // Fallback success message
                            Swal.fire({
                                icon: 'success',
                                title: 'Payment Submitted',
                                text: 'Your payment has been submitted. Please check your email for confirmation.',
                                confirmButtonText: 'Continue'
                            }).then(() => {
                                window.location.href = '/user/dashboard';
                            });
                        };

                        // Function to handle payment cancellation
                        const handlePaymentCancel = () => {
                            debugLog('Payment was cancelled by user');

                            if (paymentCompleted) return;

                            cleanup();

                            Swal.fire({
                                icon: 'info',
                                title: 'Payment Cancelled',
                                text: 'Your payment was cancelled. You can try again anytime.',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                // Stay on current page
                            });
                        };

                        // Function to check payment window status and URL
                        const checkPaymentStatus = async () => {
                            try {
                                // Check if window was closed by user
                                if (paymentWindow && paymentWindow.closed) {
                                    debugLog('Payment window was closed by user');
                                    clearInterval(checkInterval);

                                    if (!paymentCompleted && paymentReference) {
                                        // Give webhook a moment to process before checking
                                        await new Promise(resolve => setTimeout(resolve, 3000));

                                        const verification = await verifyPaymentStatus(paymentReference);

                                        if (verification.isCompleted) {
                                            debugLog('Payment was completed before window close');
                                            handlePaymentSuccess('delayed verification');
                                        } else {
                                            debugLog('Payment was not completed when window closed');
                                            handlePaymentCancel();
                                        }
                                    }
                                    return;
                                }

                                // Try to check the URL of the payment window
                                try {
                                    if (paymentWindow && paymentWindow.location) {
                                        const currentUrl = paymentWindow.location.href;
                                        debugLog('Current payment window URL:', currentUrl);

                                        // Check for success indicators in the URL
                                        if (currentUrl.includes('success') ||
                                            currentUrl.includes('thank') ||
                                            currentUrl.includes('complete') ||
                                            currentUrl.includes('thankyou')) {
                                            debugLog('Success URL detected, completing payment');
                                            handlePaymentSuccess('URL detection');
                                        }
                                    }
                                } catch (crossOriginError) {
                                    // This is expected when the iframe is on a different domain
                                    debugLog('Cross-origin restriction (normal for payment pages)');
                                }

                                // Periodically verify payment status even if we can't check the URL
                                if (paymentReference && !paymentCompleted) {
                                    const verification = await verifyPaymentStatus(paymentReference);
                                    if (verification.isCompleted) {
                                        debugLog('Payment completion detected via API check');
                                        handlePaymentSuccess('API polling');
                                    }
                                }

                            } catch (error) {
                                debugLog('Error in payment status check:', error);
                            }
                        };

                        // Open the payment window
                        const openPaymentWindow = () => {
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
                                    throw new Error('Payment window was blocked by your browser. Please allow popups and try again.');
                                }

                                // Show loading content in the payment window
                                paymentWindow.document.write(`
                <!DOCTYPE html>
                <html>
                    <head>
                        <title>Processing Payment...</title>
                        <style>
                            body { 
                                font-family: 'Inter', Arial, sans-serif; 
                                display: flex; 
                                justify-content: center; 
                                align-items: center; 
                                height: 100vh; 
                                margin: 0; 
                                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                                color: white;
                            }
                            .loader {
                                text-align: center;
                                padding: 40px;
                                background: rgba(255, 255, 255, 0.1);
                                border-radius: 15px;
                                backdrop-filter: blur(10px);
                            }
                            .spinner {
                                border: 4px solid rgba(255, 255, 255, 0.3);
                                border-top: 4px solid white;
                                border-radius: 50%;
                                width: 60px;
                                height: 60px;
                                animation: spin 1s linear infinite;
                                margin: 0 auto 20px;
                            }
                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }
                            h2 { margin: 20px 0 10px; font-weight: 600; }
                            p { margin: 0; opacity: 0.9; }
                        </style>
                    </head>
                    <body>
                        <div class="loader">
                            <div class="spinner"></div>
                            <h2>Connecting to Payment Gateway</h2>
                            <p>Please wait while we securely redirect you to complete your payment...</p>
                        </div>
                    </body>
                </html>
            `);

                                return true;
                            } catch (error) {
                                debugLog('Error opening payment window:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Unable to Open Payment Window',
                                    text: error.message,
                                    confirmButtonText: 'OK'
                                });
                                return false;
                            }
                        };

                        // Start the payment process
                        debugLog('Initiating PayProGlobal checkout');

                        if (!openPaymentWindow()) {
                            return; // Failed to open window
                        }

                        // Start monitoring the payment window
                        checkInterval = setInterval(checkPaymentStatus, 2000);

                        // Fetch the checkout URL from our backend
                        debugLog('Fetching checkout URL from backend');

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
                                body: JSON.stringify({
                                    package: packageName
                                })
                            })
                            .then(async response => {
                                debugLog('Received response from backend, status:', response.status);

                                if (!response.ok) {
                                    const errorData = await response.json().catch(() => ({}));
                                    throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
                                }

                                return response.json();
                            })
                            .then(data => {
                                debugLog('Checkout response data:', data);

                                if (!data.success || !data.checkoutUrl) {
                                    throw new Error(data.message || 'No checkout URL received');
                                }

                                // Store the payment reference for verification
                                paymentReference = data.payment_reference;
                                debugLog('Payment reference stored:', paymentReference);

                                // Redirect the payment window to the checkout URL
                                debugLog('Redirecting payment window to checkout URL');
                                paymentWindow.location.href = data.checkoutUrl;

                            })
                            .catch(error => {
                                debugLog('Error during checkout process:', error);
                                cleanup();

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Checkout Error',
                                    text: error.message || 'Failed to initiate payment. Please try again or contact support.',
                                    confirmButtonText: 'OK'
                                });
                            });
                    }

                    // Enhanced success URL handler for PayProGlobal
                    window.addEventListener('message', function(event) {
                        // Listen for messages from the payment window
                        if (event.data && event.data.type === 'payproGlobalSuccess') {
                            console.log('[PayProGlobal] Received success message from payment window');
                            handlePaymentSuccess('postMessage');
                        }
                    }, false);
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