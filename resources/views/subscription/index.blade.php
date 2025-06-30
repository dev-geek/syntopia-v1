<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy"
        content="
      default-src 'self' data: gap: https://ssl.gstatic.com https://livebuzzstudio.test https://cdn.jsdelivr.net;
      style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net;
      font-src 'self' https://fonts.gstatic.com;
      script-src 'self' https://livebuzzstudio.test https://somedomain.com https://sbl.onfastspring.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://secure.payproglobal.com 'unsafe-inline' 'unsafe-eval';
      img-src 'self' https://syntopia.ai https://sbl.onfastspring.com data:;
      connect-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://sandbox-api.paddle.com https://sandbox-cdn.paddle.com;
      frame-src 'self' https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com https://sandbox-buy.paddle.com;
      frame-ancestors 'self' https://livebuzzstudio.test;
      media-src 'self' data: https://sbl.onfastspring.com;">
    <title>
        @if($isUpgrade ?? false)
        Upgrade Your Subscription
        @elseif($isDowngrade ?? false)
        Downgrade Your Subscription
        @else
        Syntopia Pricing
        @endif
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Payment Gateway Scripts -->
    @php
    $activeGateways = isset($payment_gateways) ? $payment_gateways->pluck('name')->toArray() : [];
    // Create package mapping for JavaScript
    $packageMapping = [];

    // Get ALL packages for display purposes
    $allPackages = \App\Models\Package::select('id', 'name', 'price', 'duration', 'features')->get();

    foreach ($allPackages as $package) {
    $packageMapping[strtolower($package->name)] = $package->id;
    }

    // Determine current package price for comparison
    $currentPackagePrice = $currentPackagePrice ?? 0;
    @endphp
    @if (in_array('FastSpring', $activeGateways))
    <script src="https://sbl.onfastspring.com/js/checkout/button.js"
        data-button-id="{{ $currentLoggedInUserPaymentGateway ?? 'FastSpring' }}"></script>
    <script src="https://sbl.onfastspring.com/js/checkout/button.js"
        data-button-id="{{ $currentLoggedInUserPaymentGateway ?? 'FastSpring' }}"></script>
    @endif
    @if (in_array('Paddle', $activeGateways))
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    @endif
    @if (in_array('Pay Pro Global', $activeGateways))
    <script src="https://secure.payproglobal.com/js/custom/checkout.js"></script>
    <script src="https://secure.payproglobal.com/js/custom/checkout.js"></script>
    @endif

    <!-- FastSpring Integration -->
    @if ($activeGateway && $activeGateway->name === 'FastSpring' && !($isUpgrade || $isDowngrade))
    <script id="fsc-api" src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js" type="text/javascript"
        data-storefront="livebuzzstudio.test.onfastspring.com/popup-test-87654-payment" data-popup-closed="onFSPopupClosed"
        data-data-callback="handleFastSpringSuccess" data-debug="true"></script>
    <script>
        // Package mapping for dynamic package ID lookup
        const packageMapping = @json($packageMapping);
        let currentProductPath = '';
        let currentPackageId = null;

        function processFastSpring(productPath) {
            try {
                if (typeof fastspring === 'undefined' || !fastspring.builder) {
                    throw new Error('FastSpring is not properly initialized');
                }
                fastspring.builder.reset();
                const packageName = productPath.replace('-plan', '').toLowerCase();
                currentProductPath = productPath;

                // Set the current package ID based on the package name
                currentPackageId = packageMapping[packageName] || null;

                console.log('FastSpring checkout initiated:', {
                    productPath: productPath,
                    packageName: packageName,
                    packageId: currentPackageId,
                    packageMapping: packageMapping
                });

                if (!currentPackageId) {
                    throw new Error(`Package ID not found for: ${packageName}`);
                }

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

                    console.log('FastSpring popup closed with order data:', {
                        orderData: orderData,
                        orderId: orderId,
                        currentPackageId: currentPackageId,
                        currentProductPath: currentProductPath
                    });

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

                    // Use the dynamic package ID instead of hardcoded 3
                    const packageIdInput = document.createElement('input');
                    packageIdInput.type = 'hidden';
                    packageIdInput.name = 'package_id';
                    packageIdInput.value = currentPackageId || '';
                    form.appendChild(packageIdInput);

                    // Also send the package name for additional validation
                    const packageNameInput = document.createElement('input');
                    packageNameInput.type = 'hidden';
                    packageNameInput.name = 'package_name';
                    packageNameInput.value = currentProductPath.replace('-plan', '').toLowerCase();
                    form.appendChild(packageNameInput);

                    const paymentGatewayIdInput = document.createElement('input');
                    paymentGatewayIdInput.type = 'hidden';
                    paymentGatewayIdInput.name = 'payment_gateway_id';
                    paymentGatewayIdInput.value = "{{ $activeGateway->id ?? '' }}";
                    form.appendChild(paymentGatewayIdInput);

                    console.log('Submitting FastSpring success form with data:', {
                        orderId: orderId,
                        packageId: currentPackageId,
                        packageName: currentProductPath.replace('-plan', '').toLowerCase(),
                        paymentGatewayId: "{{ $activeGateway->id ?? '' }}"
                    });

                    document.body.appendChild(form);
                    form.submit();
                } else {
                    console.log('FastSpring popup closed without order data');
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
                console.error('Error in FastSpring popup closed handler:', err);
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
                Paddle.Environment.set('{{ config('
                    payment.gateways.Paddle.environment ', '
                    sandbox ') }}');
                Paddle.Setup({
                    token: '{{ config('
                    payment.gateways.Paddle.client_side_token ') }}',
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

        .upgrade-badge {
            background-color: #fef3c7;
            color: #d97706;
            border-color: #d97706;
        }

        .downgrade-badge {
            background-color: #fef2f2;
            color: #dc2626;
            border-color: #dc2626;
        }

        .pricing-wrapper h2 {
            font-size: 65px;
        }

        .card {
            border: 1px solid #EFE7FB;
            border-radius: 10px;
            padding: 15px;
            position: relative;
            transition: all 0.3s ease;
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

        .card-current {
            border: 3px solid #10b981;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
        }

        .card-disabled {
            opacity: 0.6;
            filter: grayscale(50%);
        }

        .current-plan-indicator {
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #10b981;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
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

        .upgrade-info {
            text-align: center;
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .upgrade-info h3 {
            color: #0ea5e9;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .upgrade-info p {
            color: #0369a1;
            font-size: 14px;
        }

        .downgrade-info {
            text-align: center;
            background: #fef2f2;
            border: 1px solid #dc2626;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .downgrade-info h3 {
            color: #dc2626;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .downgrade-info p {
            color: #991b1b;
            font-size: 14px;
        }

        .no-upgrades-message {
            text-align: center;
            background: #fef3c7;
            border: 1px solid #d97706;
            border-radius: 8px;
            padding: 30px;
            margin-top: 30px;
        }

        .no-upgrades-message h3 {
            color: #d97706;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .no-upgrades-message p {
            color: #92400e;
            font-size: 16px;
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
            transition: all 0.3s ease;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn.dark {
            background: black;
            color: white;
        }

        .btn.dark:hover:not(:disabled) {
            background: #5b0dd5;
        }

        .btn.purple {
            background: #5b0dd5;
            color: white;
        }

        .btn.purple:hover:not(:disabled) {
            background: white;
            color: #5b0dd5;
        }

        .btn.white {
            background: white;
            color: #5b0dd5;
        }

        .btn.white:hover:not(:disabled) {
            background: white;
        }

        .btn.upgrade {
            background: #f59e0b;
            color: white;
        }

        .btn.upgrade:hover:not(:disabled) {
            background: #d97706;
        }

        .btn.downgrade {
            background: #dc2626;
            color: white;
        }

        .btn.downgrade:hover:not(:disabled) {
            background: #b91c1c;
        }

        .btn.current {
            background: #10b981;
            color: white;
        }

        .btn.disabled-plan {
            background: #9ca3af;
            color: #6b7280;
            cursor: not-allowed;
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

            .pricing-wrapper h2 {
                font-size: 40px;
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

        .price-difference {
            font-size: 14px;
            color: #059669;
            font-weight: 500;
            margin-top: 5px;
        }

        .price-difference.savings {
            color: #dc2626;
        }

        .card-dark .price-difference {
            color: #a7f3d0;
        }

        .card-dark .price-difference.savings {
            color: #fca5a5;
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
            @include('components.alert-messages')
            <div class="badge-wrapper">
                <div class="pricing-badge {{ $isUpgrade ? 'upgrade-badge' : ($isDowngrade ? 'downgrade-badge' : '') }}">
                    @if($isUpgrade)
                    Upgrade Subscription
                    @elseif($isDowngrade)
                    Downgrade Subscription
                    @else
                    Pricing Plans
                    @endif
                </div>
            </div>

            @if ($isUpgrade)
            <div class="upgrade-info">
                <h3>Upgrade Your Subscription</h3>
                <p>You're currently on the <strong>{{ $currentPackage }}</strong> plan. Choose a higher tier below to upgrade your subscription.</p>
                @if ($userOriginalGateway)
                <p style="margin-top: 8px;">Your upgrade will be processed through <strong>{{ $userOriginalGateway }}</strong> (your original payment gateway).</p>
                @endif
            </div>
            <h2 class="section-title">Available Upgrades</h2>
            <p class="section-subtitle">Select a higher tier plan to upgrade your subscription with prorated billing.</p>
            @elseif ($isDowngrade)
            <div class="downgrade-info">
                <h3>Downgrade Your Subscription</h3>
                <p>You're currently on the <strong>{{ $currentPackage }}</strong> plan. Choose a lower tier below to downgrade your subscription.</p>
                @if ($userOriginalGateway)
                <p style="margin-top: 8px;">Your downgrade will be processed through <strong>{{ $userOriginalGateway }}</strong> (your original payment gateway).</p>
                @endif
                <p style="margin-top: 8px;"><strong>Note:</strong> Downgrades typically take effect at the end of your current billing cycle.</p>
            </div>
            <h2 class="section-title">All Available Plans</h2>
            <p class="section-subtitle">Your current plan is highlighted in green. Only lower-priced plans can be selected for downgrade.</p>
            @else
            <h2 class="section-title">Plans For Every Type of Business</h2>
            <p class="section-subtitle">SYNTOPIA creates hyperrealistic, interactive AI avatars that revolutionize how
                businesses and individuals connect with their audiences. Our avatars can:</p>
            @endif

            @if ($allPackages->isEmpty())
            <div class="no-upgrades-message">
                <h3>No Plans Available</h3>
                <p>There are currently no subscription plans available. Please contact support for assistance.</p>
            </div>
            @elseif ($isDowngrade && $packages->isEmpty())
            <div class="no-upgrades-message">
                <h3>You're on the Lowest Plan!</h3>
                <p>{{ $upgradeMessage ?? 'You are already on the lowest available plan. No downgrades are available at this time.' }}</p>
            </div>
            @elseif ($isUpgrade && $packages->isEmpty())
            <div class="no-upgrades-message">
                <h3>You're on the Top Plan!</h3>
                <p>{{ $upgradeMessage ?? 'You are already on the highest available plan. No upgrades are available at this time.' }}</p>
            </div>
            @else
            <div class="pricing-grid">
                @php
                // For downgrade page, show all packages, otherwise show filtered packages
                $packagesToShow = $isDowngrade ? $allPackages : $packages;
                @endphp

                @foreach ($packagesToShow as $package)
                @php
                $isCurrentPackage = $currentPackage === $package->name;
                $priceDifference = 0;
                $canSelect = true;
                $buttonClass = 'dark';
                $buttonText = 'Get Started';

                if ($isUpgrade || $isDowngrade) {
                $priceDifference = $package->price - $currentPackagePrice;

                if ($isUpgrade) {
                $canSelect = $package->price > $currentPackagePrice;
                $buttonClass = $canSelect ? 'upgrade' : 'disabled-plan';
                $buttonText = $canSelect ? "Upgrade to {$package->name}" : 'Not Available for Upgrade';
                } elseif ($isDowngrade) {
                $canSelect = $package->price < $currentPackagePrice;
                    $buttonClass=$isCurrentPackage ? 'current' : ($canSelect ? 'downgrade' : 'disabled-plan' );
                    $buttonText=$isCurrentPackage ? 'Current Plan' : ($canSelect ? "Downgrade to {$package->name}" : 'Higher Priced Plan' );
                    }
                    } else {
                    $canSelect=!$isCurrentPackage;
                    $buttonClass=$isCurrentPackage ? 'current' : 'dark' ;
                    $buttonText=$isCurrentPackage ? 'Current Plan' : 'Get Started' ;
                    }

                    if ($package->name == 'Enterprise') {
                    $buttonText = 'Get in Touch';
                    $canSelect = true;
                    $buttonClass = 'purple';
                    }
                    @endphp

                    <div class="card {{ $loop->iteration % 2 == 1 ? 'card-dark' : 'card-light' }} {{ $isCurrentPackage ? 'card-current' : '' }} {{ !$canSelect && !$isCurrentPackage ? 'card-disabled' : '' }}">
                        @if ($isCurrentPackage)
                        <div class="current-plan-indicator">Current Plan</div>
                        @endif

                        <h3>{{ $package->name }}</h3>
                        <p class="price">${{ number_format($package->price, 0) }} <span
                                class="per-month">/{{ $package->duration }}</span></p>

                        @if (($isUpgrade || $isDowngrade) && $priceDifference != 0 && !$isCurrentPackage)
                        <div class="price-difference {{ $priceDifference < 0 ? 'savings' : '' }}">
                            @if ($priceDifference > 0)
                            +${{ number_format($priceDifference, 0) }} from current plan
                            @else
                            Save ${{ number_format(abs($priceDifference), 0) }} from current plan
                            @endif
                        </div>
                        @endif

                        <button class="btn {{ $buttonClass }} checkout-button"
                            data-package="{{ $package->name }}"
                            data-package-id="{{ $package->id }}"
                            {{ (!$canSelect || $isCurrentPackage) && $package->name != 'Enterprise' ? 'disabled' : '' }}>
                            {{ $buttonText }}
                        </button>

                        <p class="included-title">What's included</p>
                        <ul class="features">
                            @php
                            $features = is_string($package->features) ? json_decode($package->features, true) : $package->features;
                            $features = is_array($features) ? $features : [];
                            @endphp
                            @foreach ($features as $feature)
                            <li><span class="icon"></span> {{ $feature }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endforeach
            </div>
            @endif
        </div>
    </div>

    @if (!($isUpgrade || $isDowngrade))
    @include('subscription.includes._addons')
    @endif

    <footer>
        Having trouble? Contact us at
        <a href="mailto:support@syntopia.ai">support@syntopia.ai</a>
    </footer>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const isUpgrade = {{ ($isUpgrade ?? false) ? 'true' : 'false' }};
        const isDowngrade = {{ ($isDowngrade ?? false) ? 'true' : 'false' }};
        const userOriginalGateway = "{{ $userOriginalGateway ?? '' }}";
        const activeGatewaysByAdmin = @json($activeGatewaysByAdmin ?? []);

        // For upgrades/downgrades, always use the user's original gateway
        // For new subscriptions, use the first active gateway
        const selectedGateway = (isUpgrade || isDowngrade) && userOriginalGateway ?
            userOriginalGateway :
            (activeGatewaysByAdmin.length > 0 ? activeGatewaysByAdmin[0] : null);

        // Global variables for FastSpring
        let currentSelectedPackageId = null;
        let currentSelectedPackageName = null;

        document.addEventListener("DOMContentLoaded", function() {
            const currentPackage = "{{ $currentPackage ?? '' }}";

            document.querySelectorAll('.checkout-button').forEach(button => {
                button.addEventListener('click', function() {
                    if (this.disabled) return;

                    this.disabled = true;
                    const productPath = this.getAttribute('data-package');
                    const packageId = this.getAttribute('data-package-id');

                    // Set global variables for FastSpring
                    currentSelectedPackageId = packageId;
                    currentSelectedPackageName = productPath.replace('-plan', '').toLowerCase();

                    console.log('Button clicked:', {
                        productPath: productPath,
                        packageId: packageId,
                        packageName: currentSelectedPackageName,
                        isUpgrade: isUpgrade,
                        isDowngrade: isDowngrade
                    });

                    // Add loading state
                    const originalText = this.textContent;
                    if (isUpgrade) {
                        this.textContent = 'Processing Upgrade...';
                    } else if (isDowngrade) {
                        this.textContent = 'Processing Downgrade...';
                    } else {
                        this.textContent = 'Processing...';
                    }

                    processCheckout(productPath);

                    // Re-enable button after 3 seconds
                    setTimeout(() => {
                        this.disabled = false;
                        this.textContent = originalText;
                    }, 3000);
                });
            });

            const packageFromURL = "{{ $currentPackage ?? '' }}";
            if (packageFromURL && packageFromURL !== '' && packageFromURL !== currentPackage) {
                processCheckout(packageFromURL.toLowerCase() + "-plan");
            }
        });

        function showLoadingSpinner() {
            let spinner = document.getElementById('loading-spinner');
            if (!spinner) {
                spinner = document.createElement('div');
                spinner.id = 'loading-spinner';
                spinner.style.position = 'fixed';
                spinner.style.top = '50%';
                spinner.style.left = '50%';
                spinner.style.transform = 'translate(-50%, -50%)';
                spinner.style.zIndex = '100000';
                spinner.innerHTML = `
            <div style="display: flex; flex-direction: column; align-items: center;">
                <div style="width: 48px; height: 48px; border: 5px solid #000; border-bottom-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <p style="margin-top: 10px; color: #333;">Processing...</p>
            </div>
        `;
                document.body.appendChild(spinner);

                // Add CSS for the spinner animation
                const style = document.createElement('style');
                style.innerHTML = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
                document.head.appendChild(style);
            }
            spinner.style.display = 'block';
        }

        function hideLoadingSpinner() {
            const spinner = document.getElementById('loading-spinner');
            if (spinner) {
                spinner.style.display = 'none';
            }
        }

        function processCheckout(productPath) {
            try {
                if (!selectedGateway) {
                    throw new Error('No payment gateway selected');
                }

                console.log('Processing checkout:', {
                    productPath: productPath,
                    gateway: selectedGateway,
                    isUpgrade: isUpgrade,
                    isDowngrade: isDowngrade,
                    packageId: currentSelectedPackageId
                });

                showLoadingSpinner(); // Show spinner before initiating checkout

                switch (selectedGateway) {
                    case 'FastSpring':
                        processFastSpring(productPath, isUpgrade || isDowngrade);
                        break;
                    case 'Paddle':
                        processPaddle(productPath, isUpgrade || isDowngrade);
                        break;
                    case 'Pay Pro Global':
                        processPayProGlobal(productPath, isUpgrade || isDowngrade);
                        break;
                    default:
                        throw new Error(`Unsupported payment gateway: ${selectedGateway}`);
                }
            } catch (error) {
                hideLoadingSpinner(); // Hide spinner on error
                console.error('Checkout error:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment Gateway Error',
                        text: error.message || 'Payment gateway error. Please try again later or contact support.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert('Payment Gateway Error: ' + (error.message || 'Payment gateway error. Please try again later or contact support.'));
                }
            }
        }

        function processFastSpring(productPath, isPlanChange = false) {
            try {
                if (isPlanChange) {
                    // For upgrades/downgrades, call the API directly
                    const packageName = productPath.replace('-plan', '');
                    const apiUrl = `/api/payments/fastspring/checkout/${packageName}`;

                    const headers = {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    };

                    if (isUpgrade) {
                        headers['X-Is-Upgrade'] = 'true';
                    } else if (isDowngrade) {
                        headers['X-Is-Downgrade'] = 'true';
                    }

                    const requestBody = {
                        is_upgrade: isUpgrade,
                        is_downgrade: isDowngrade
                    };

                    fetch(apiUrl, {
                            method: 'POST',
                            headers: headers,
                            credentials: 'same-origin',
                            body: JSON.stringify(requestBody)
                        })
                        .then(response => {
                            hideLoadingSpinner(); // Hide spinner after response
                            if (!response.ok) {
                                return response.json().then(err => {
                                    throw new Error(err.message || err.error || `HTTP ${response.status}: ${response.statusText}`);
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (!data.success) {
                                throw new Error(data.message || data.error || 'Plan change failed');
                            }

                            const actionText = isUpgrade ? 'Upgrade' : 'Downgrade';
                            Swal.fire({
                                icon: 'success',
                                title: `${actionText} Successful!`,
                                text: data.message || `Your subscription has been ${isUpgrade ? 'upgraded' : 'downgraded'} successfully.`,
                                confirmButtonText: 'Go to Dashboard'
                            }).then(() => {
                                window.location.href = '/user/dashboard';
                            });
                        })
                        .catch(error => {
                            hideLoadingSpinner(); // Hide spinner on error
                            console.error('FastSpring plan change error:', error);
                            const actionText = isUpgrade ? 'upgrade' : 'downgrade';
                            Swal.fire({
                                icon: 'error',
                                title: `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Failed`,
                                text: error.message || `Failed to ${actionText} subscription. Please try again.`,
                                confirmButtonText: 'OK'
                            });
                        });
                } else {
                    // Regular FastSpring checkout
                    if (typeof fastspring === 'undefined' || !fastspring.builder) {
                        hideLoadingSpinner();
                        throw new Error('FastSpring is not properly initialized');
                    }
                    fastspring.builder.reset();
                    const packageName = productPath.replace('-plan', '').toLowerCase();

                    // Set the current package info for the popup callback
                    currentSelectedPackageName = packageName;

                    console.log('FastSpring checkout initiated:', {
                        productPath: productPath,
                        packageName: packageName,
                        packageId: currentSelectedPackageId
                    });

                    fastspring.builder.add(packageName);
                    setTimeout(() => {
                        hideLoadingSpinner(); // Hide spinner before opening FastSpring popup
                        fastspring.builder.checkout();
                    }, 500);
                }
            } catch (error) {
                hideLoadingSpinner();
                throw error;
            }
        }

        // processPaddle to handle spinner
        function processPaddle(productPath, isPlanChange = false) {
            const packageName = productPath.replace('-plan', '');
            const apiUrl = `/api/payments/paddle/checkout/${packageName}`;

            const requestBody = {};
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer {{ auth()->user() ? auth()->user()->createToken('
                api ')->plainTextToken : '
                ' }}',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (isPlanChange) {
                if (isUpgrade) {
                    headers['X-Is-Upgrade'] = 'true';
                    requestBody.is_upgrade = true;
                } else if (isDowngrade) {
                    headers['X-Is-Downgrade'] = 'true';
                    requestBody.is_downgrade = true;
                }
            }

            showLoadingSpinner(); // Show spinner before fetch
            fetch(apiUrl, {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin',
                    body: JSON.stringify(requestBody)
                })
                .then(response => {
                    hideLoadingSpinner(); // Hide spinner after response
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || err.error || `HTTP ${response.status}: ${response.statusText}`);
                        });
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
                                console.log('Paddle event:', eventData.data.event);
                                if (eventData.data?.event?.name === 'checkout.completed') {
                                    let redirectUrl = `/payments/paddle/verify?transaction_id=${data.transaction_id}`;
                                    if (isUpgrade) {
                                        redirectUrl += '&is_upgrade=true';
                                    } else if (isDowngrade) {
                                        redirectUrl += '&is_downgrade=true';
                                    }
                                    window.location.href = redirectUrl;
                                } else if (eventData.data?.event?.name === 'checkout.failed') {
                                    const actionText = isUpgrade ? 'Upgrade' : (isDowngrade ? 'Downgrade' : 'Payment');
                                    Swal.fire({
                                        icon: 'error',
                                        title: `${actionText} Failed`,
                                        text: eventData.data.error || 'An error occurred during payment. Please try again.',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else if (eventData.data?.event?.name === 'checkout.closed' && !eventData.data.success) {
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
                    hideLoadingSpinner(); // Hide spinner on error
                    console.error('Paddle checkout error:', error);
                    const actionText = isUpgrade ? 'Upgrade' : (isDowngrade ? 'Downgrade' : 'Checkout');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: `${actionText} Failed`,
                            text: error.message || 'An error occurred while processing your request. Please try again later.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert(`${actionText} Failed: ` + (error.message || 'An error occurred while processing your checkout. Please try again later.'));
                    }
                });
        }

        // Modified processPayProGlobal to handle spinner
        function processPayProGlobal(productPath, isPlanChange = false) {
            console.log('[PayProGlobal] Starting payment process for product:', productPath);

            const packageName = productPath.replace('-plan', '');
            const apiUrl = `/api/payments/payproglobal/checkout/${packageName}`;

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

            const requestBody = {
                package: packageName
            };
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (isPlanChange) {
                if (isUpgrade) {
                    headers['X-Is-Upgrade'] = 'true';
                    requestBody.is_upgrade = true;
                } else if (isDowngrade) {
                    headers['X-Is-Downgrade'] = 'true';
                    requestBody.is_downgrade = true;
                }
            }

            window.paymentSuccess = function() {
                console.log('[PayProGlobal] Payment successful for package:', packageName);
                const actionText = isUpgrade ? 'upgraded' : (isDowngrade ? 'downgraded' : 'processed');
                const message = `Your subscription has been ${actionText} successfully!`;
                Swal.fire({
                    icon: 'success',
                    title: isUpgrade ? 'Upgrade Successful' : (isDowngrade ? 'Downgrade Successful' : 'Payment Successful'),
                    text: message,
                    confirmButtonText: 'Go to Dashboard'
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

            showLoadingSpinner(); // Show spinner before fetch
            fetch(apiUrl, {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin',
                    body: JSON.stringify(requestBody)
                })
                .then(response => {
                    hideLoadingSpinner(); // Hide spinner after response
                    console.log('[PayProGlobal] Response status:', response.status);
                    if (response.status === 401) {
                        console.error('[PayProGlobal] Authentication error: User not logged in');
                        throw new Error('You must be logged in to make a purchase. Please log in and try again.');
                    }
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || err.error || `HTTP ${response.status}: ${response.statusText}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('[PayProGlobal] Checkout response:', data);
                    if (!data.success || !data.checkoutUrl) {
                        console.error('[PayProGlobal] Checkout failed:', data.message || 'No checkout URL received');
                        throw new Error(data.message || 'No checkout URL received');
                    }
                    console.log('[PayProGlobal] Opening checkout popup with URL:', data.checkoutUrl);
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
                    hideLoadingSpinner(); // Hide spinner on error
                    console.error('[PayProGlobal] Error during checkout:', error.message);
                    const actionText = isUpgrade ? 'Upgrade' : (isDowngrade ? 'Downgrade' : 'Checkout');
                    Swal.fire({
                        icon: 'error',
                        title: `${actionText} Error`,
                        text: error.message || 'Failed to initiate payment. Please try again or contact support.',
                        confirmButtonText: 'OK'
                    });
                });
        }

        // Updated FastSpring popup closed handler (for non-upgrade/downgrade FastSpring checkouts)
        function onFSPopupClosed(orderData) {
            try {
                if (orderData && (orderData.reference || orderData.id)) {
                    const orderId = orderData.reference || orderData.id;

                    console.log('FastSpring popup closed with order data:', {
                        orderData: orderData,
                        orderId: orderId,
                        currentSelectedPackageId: currentSelectedPackageId,
                        currentSelectedPackageName: currentSelectedPackageName
                    });

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

                    // Use the dynamic package ID instead of hardcoded 3
                    const packageIdInput = document.createElement('input');
                    packageIdInput.type = 'hidden';
                    packageIdInput.name = 'package_id';
                    packageIdInput.value = currentSelectedPackageId || '';
                    form.appendChild(packageIdInput);

                    // Also send the package name for additional validation
                    const packageNameInput = document.createElement('input');
                    packageNameInput.type = 'hidden';
                    packageNameInput.name = 'package_name';
                    packageNameInput.value = currentSelectedPackageName || '';
                    form.appendChild(packageNameInput);

                    const paymentGatewayIdInput = document.createElement('input');
                    paymentGatewayIdInput.type = 'hidden';
                    paymentGatewayIdInput.name = 'payment_gateway_id';
                    paymentGatewayIdInput.value = "{{ $activeGateway->id ?? '' }}";
                    form.appendChild(paymentGatewayIdInput);

                    console.log('Submitting FastSpring success form with data:', {
                        orderId: orderId,
                        packageId: currentSelectedPackageId,
                        packageName: currentSelectedPackageName,
                        paymentGatewayId: "{{ $activeGateway->id ?? '' }}"
                    });

                    document.body.appendChild(form);
                    form.submit();
                } else {
                    console.log('FastSpring popup closed without order data');
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
                console.error('Error in FastSpring popup closed handler:', err);
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