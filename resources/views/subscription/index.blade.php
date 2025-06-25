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
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    @endif
    @if (in_array('Pay Pro Global', $activeGateways))
    <script src="https://secure.payproglobal.com/js/custom/checkout.js"></script>
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
                            window.history.back()
                        });
                    } else {
                        alert('Payment Cancelled: Your payment was cancelled. You can try again anytime.');
                        window.history.back()
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
                        window.history.back()
                    });
                } else {
                    alert('Processing Error: There was an error processing your payment. Please contact support.');
                    window.history.back()
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
                // Set the Paddle environment
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
    @endif

    <style>
        .btn.active {
            background: #22c55e !important;
            color: white !important;
            cursor: not-allowed !important;
            opacity: 0.9;
        }

        .btn.active:hover {
            background: #22c55e !important;
            color: white !important;
        }

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
                    <p class="price">${{ number_format($package->price, 0) }} <span
                            class="per-month">/{{ $package->duration }}</span></p>

                    <button class="btn {{ $currentPackage == $package->name ? 'active' : 'dark' }} checkout-button"
                        data-package="{{ $package->name }}"
                        {{ $currentPackage == $package->name ? 'disabled' : '' }}>
                        {{ $package->name == 'Enterprise'
                    ? 'Get in Touch'
                    : ($currentPackage == $package->name
                        ? '✓ Current Plan'
                        : (isset($isUpgrade) && $isUpgrade ? 'Upgrade to ' . $package->name : 'Get Started')) }}
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
            const currentPackagePrice = parseFloat("{{ $currentPackagePrice ?? 0 }}");
            const userOriginalGateway = "{{ $userOriginalGateway ?? '' }}";
            const activeGatewaysByAdmin = @json($activeGatewaysByAdmin ?? []);
            const isUpgrade = '{{ isset($isUpgrade) && $isUpgrade ? 'true' : 'false' }}';
            const upgradeEligible = '{{ isset($upgradeEligible) && $upgradeEligible ? 'true' : 'false' }}';

            console.log('Page configuration:', {
                currentPackage,
                currentPackagePrice,
                userOriginalGateway,
                activeGatewaysByAdmin,
                isUpgrade,
                upgradeEligible
            });

            // Gateway selection logic - UNIFIED for both new and upgrade
            let selectedGateway;
            if (isUpgrade && userOriginalGateway) {
                // For upgrades, ALWAYS use the user's original gateway
                selectedGateway = userOriginalGateway;
                console.log(`[${isUpgrade ? 'UPGRADE' : 'NEW'}] Using user's original gateway:`, selectedGateway);
            } else {
                // For new subscriptions, use the first available active gateway
                selectedGateway = activeGatewaysByAdmin.length > 0 ? activeGatewaysByAdmin[0] : null;
                console.log(`[${isUpgrade ? 'UPGRADE' : 'NEW'}] Using active gateway:`, selectedGateway);
            }

            if (!selectedGateway) {
                console.error('No payment gateway available!', {
                    isUpgrade,
                    userOriginalGateway,
                    activeGatewaysByAdmin
                });
            }

            // Add upgrade-specific styling and messaging if needed
            if (isUpgrade) {
                console.log('Setting up upgrade UI...');
                setupUpgradeUI();
            }

            // UNIFIED button click handler
            console.log('Setting up checkout button listeners...');

            document.querySelectorAll('.checkout-button').forEach((button, index) => {
                console.log(`Setting up button ${index + 1}:`, {
                    package: button.getAttribute('data-package'),
                    disabled: button.disabled,
                    hasActiveClass: button.classList.contains('active')
                });

                button.addEventListener('click', function() {
                    const packageName = this.getAttribute('data-package');
                    console.log(`Button clicked for package: ${packageName}`);

                    // Prevent clicking on active/disabled buttons
                    if (this.disabled || this.classList.contains('active')) {
                        console.warn('Button click ignored - button is disabled or active', {
                            disabled: this.disabled,
                            hasActiveClass: this.classList.contains('active'),
                            package: packageName
                        });
                        return false;
                    }

                    console.log(`Processing checkout for: ${packageName}`);
                    this.disabled = true;

                    // UNIFIED checkout processing
                    processCheckout(packageName, isUpgrade);

                    // Re-enable button after timeout
                    setTimeout(() => {
                        this.disabled = false;
                        console.log(`Re-enabled button for: ${packageName}`);
                    }, 3000);
                });
            });

            function processCheckout(packageName, isUpgradeRequest = false) {
                console.log('=== PROCESSING CHECKOUT ===', {
                    packageName,
                    isUpgradeRequest,
                    selectedGateway,
                    currentPackage
                });

                try {
                    if (!selectedGateway) {
                        throw new Error('No payment gateway available');
                    }

                    console.log(`Starting ${isUpgradeRequest ? 'upgrade' : 'new subscription'} checkout for ${packageName} with ${selectedGateway}`);

                    // Show confirmation for upgrades
                    if (isUpgradeRequest) {
                        console.log('Showing upgrade confirmation dialog...');
                        showUpgradeConfirmation(packageName, selectedGateway);
                    } else {
                        console.log('Proceeding directly to checkout...');
                        executeCheckout(packageName, isUpgradeRequest);
                    }
                } catch (error) {
                    console.error('Checkout error:', error);
                    showError('Checkout Error', error.message || 'Failed to process checkout. Please try again.');
                }
            }

            function showUpgradeConfirmation(packageName, gateway) {
                console.log('Displaying upgrade confirmation:', {
                    from: currentPackage,
                    to: packageName,
                    gateway
                });

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Confirm Upgrade',
                        html: `
                    <p>You're about to upgrade from <strong>${currentPackage}</strong> to <strong>${packageName}</strong></p>
                    <p style="color: #666; font-size: 14px;">Your current subscription will be prorated and the new plan will take effect immediately.</p>
                `,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Proceed with Upgrade',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#5b0dd5'
                    }).then((result) => {
                        console.log('Upgrade confirmation result:', result);
                        if (result.isConfirmed) {
                            console.log('User confirmed upgrade - proceeding...');
                            executeCheckout(packageName, true);
                        } else {
                            console.log('User cancelled upgrade');
                        }
                    });
                } else {
                    console.log('SweetAlert not available, using native confirm dialog');
                    if (confirm(`Upgrade from ${currentPackage} to ${packageName} using ${gateway}?`)) {
                        console.log('User confirmed upgrade via native dialog - proceeding...');
                        executeCheckout(packageName, true);
                    } else {
                        console.log('User cancelled upgrade via native dialog');
                    }
                }
            }

            function executeCheckout(packageName, isUpgradeRequest) {
                console.log('=== EXECUTING CHECKOUT ===', {
                    packageName,
                    isUpgradeRequest,
                    selectedGateway
                });

                switch (selectedGateway) {
                    case 'FastSpring':
                        console.log('Executing FastSpring checkout...');
                        processFastSpring(packageName, isUpgradeRequest);
                        break;
                    case 'Paddle':
                        console.log('Executing Paddle checkout...');
                        processPaddle(packageName, isUpgradeRequest);
                        break;
                    case 'Pay Pro Global':
                        console.log('Executing PayProGlobal checkout...');
                        processPayProGlobal(packageName, isUpgradeRequest);
                        break;
                    default:
                        console.error('Unsupported payment gateway:', selectedGateway);
                        throw new Error(`Unsupported payment gateway: ${selectedGateway}`);
                }
            }

            function processFastSpring(packageName, isUpgradeRequest = false) {
                console.log('=== FASTSPRING PROCESSING ===', {
                    packageName,
                    isUpgradeRequest,
                    fastspringAvailable: typeof fastspring !== 'undefined'
                });

                try {
                    if (typeof fastspring === 'undefined' || !fastspring.builder) {
                        console.error('FastSpring not properly initialized');
                        throw new Error('FastSpring is not properly initialized');
                    }

                    console.log('FastSpring available, preparing checkout...');

                    fastspring.builder.reset();
                    console.log('FastSpring builder reset');

                    const productPath = packageName.toLowerCase();
                    console.log('Adding product to FastSpring cart:', productPath);
                    fastspring.builder.add(productPath);

                    // Set upgrade context in FastSpring custom data if needed
                    if (isUpgradeRequest) {
                        console.log('Setting upgrade context for FastSpring...');
                        window.fastspringUpgradeContext = {
                            isUpgrade: true,
                            currentPackage: currentPackage,
                            targetPackage: packageName
                        };
                        console.log('FastSpring upgrade context set:', window.fastspringUpgradeContext);
                    }

                    console.log('Launching FastSpring checkout...');
                    setTimeout(() => {
                        fastspring.builder.checkout();
                        console.log('FastSpring checkout launched');
                    }, 500);

                } catch (error) {
                    console.error('FastSpring processing error:', error);
                    throw error;
                }
            }

            /**
             * UNIFIED Paddle processing
             */
            function processPaddle(packageName, isUpgradeRequest = false) {
                console.log('=== PADDLE PROCESSING ===', {
                    packageName,
                    isUpgradeRequest
                });

                const apiUrl = `/api/payments/paddle/checkout/${packageName}`;
                console.log('Making Paddle API request to:', apiUrl);

                const requestBody = {
                    package: packageName,
                    is_upgrade: isUpgradeRequest
                };

                const requestHeaders = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Is-Upgrade': isUpgradeRequest ? 'true' : 'false'
                };

                console.log('Paddle request details:', {
                    url: apiUrl,
                    method: 'POST',
                    headers: requestHeaders,
                    body: requestBody
                });

                fetch(apiUrl, {
                        method: 'POST',
                        headers: requestHeaders,
                        credentials: 'same-origin',
                        body: JSON.stringify(requestBody)
                    })
                    .then(response => {
                        console.log('Paddle API response received:', {
                            status: response.status,
                            statusText: response.statusText,
                            ok: response.ok
                        });

                        if (!response.ok) {
                            return response.json().then(data => {
                                console.error('Paddle API error response:', data);
                                throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Paddle API success response:', data);

                        if (!data.success) {
                            console.error('Paddle checkout failed:', data.error);
                            throw new Error(data.error || 'Checkout failed');
                        }

                        if (data.transaction_id && typeof Paddle !== 'undefined') {
                            console.log('Opening Paddle checkout with transaction ID:', data.transaction_id);
                            Paddle.Checkout.open({
                                transactionId: data.transaction_id,
                                eventCallback: function(eventData) {
                                    console.log('Paddle event received:', eventData);
                                    handlePaddleEvent(eventData, isUpgradeRequest);
                                }
                            });
                        } else {
                            console.error('No transaction ID provided or Paddle not available:', {
                                hasTransactionId: !!data.transaction_id,
                                paddleAvailable: typeof Paddle !== 'undefined'
                            });
                            throw new Error('No transaction ID provided');
                        }
                    })
                    .catch(error => {
                        console.error('Paddle processing error:', error);
                        const actionText = isUpgradeRequest ? 'upgrade' : 'checkout';
                        showError(`Paddle ${actionText} Failed`, error.message || `Failed to process ${actionText}. Please try again.`);
                    });
            }

            /**
             * UNIFIED PayProGlobal processing
             */
            function processPayProGlobal(packageName, isUpgradeRequest = false) {
                console.log('=== PAYPROGLOBAL PROCESSING ===', {
                    packageName,
                    isUpgradeRequest
                });

                const apiUrl = `/api/payments/payproglobal/checkout/${packageName}`;
                console.log('Making PayProGlobal API request to:', apiUrl);

                const requestBody = {
                    package: packageName,
                    is_upgrade: isUpgradeRequest
                };

                const requestHeaders = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Is-Upgrade': isUpgradeRequest ? 'true' : 'false'
                };

                console.log('PayProGlobal request details:', {
                    url: apiUrl,
                    method: 'POST',
                    headers: requestHeaders,
                    body: requestBody
                });

                fetch(apiUrl, {
                        method: 'POST',
                        headers: requestHeaders,
                        credentials: 'same-origin',
                        body: JSON.stringify(requestBody)
                    })
                    .then(response => {
                        console.log('PayProGlobal API response received:', {
                            status: response.status,
                            statusText: response.statusText,
                            ok: response.ok
                        });

                        if (!response.ok) {
                            return response.json().then(data => {
                                console.error('PayProGlobal API error response:', data);
                                throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('PayProGlobal API success response:', data);

                        if (!data.success || !data.checkoutUrl) {
                            console.error('PayProGlobal checkout failed:', {
                                success: data.success,
                                hasCheckoutUrl: !!data.checkoutUrl,
                                error: data.error
                            });
                            throw new Error(data.error || 'No checkout URL received');
                        }

                        console.log('Opening PayProGlobal popup with URL:', data.checkoutUrl);
                        const popup = window.open(
                            data.checkoutUrl,
                            isUpgradeRequest ? 'PayProGlobal Upgrade' : 'PayProGlobal Checkout',
                            'width=800,height=600,location=no,toolbar=no,menubar=no,scrollbars=yes'
                        );

                        if (!popup) {
                            console.error('❌ PayProGlobal popup blocked');
                            showError('Popup Blocked', 'Please allow popups for this site and try again.');
                        } else {
                            console.log('✅ PayProGlobal popup opened successfully');
                        }
                    })
                    .catch(error => {
                        console.error('PayProGlobal processing error:', error);
                        const actionText = isUpgradeRequest ? 'upgrade' : 'checkout';
                        showError(`PayProGlobal ${actionText} Failed`, error.message || `Failed to process ${actionText}. Please try again.`);
                    });
            }

            function handlePaddleEvent(eventData, isUpgradeRequest) {
                console.log('=== PADDLE EVENT HANDLER ===', {
                    event: eventData.data?.event?.name,
                    isUpgradeRequest,
                    eventData
                });

                if (eventData.data?.event?.name === 'checkout.completed') {
                    console.log('Paddle checkout completed successfully');
                    const isUpgrade = isUpgradeRequest === 'true' || isUpgradeRequest === true;
                    const message = isUpgrade ? 'Upgrade Successful!' : 'Payment Successful!';
                    const text = isUpgrade ? 'Your subscription has been upgraded successfully.' : 'Your subscription has been activated successfully.';
                    
                    // Log detailed upgrade information
                    if (isUpgrade) {
                        console.group('UPGRADE SUCCESSFUL');
                        console.log('From Package:', currentPackage);
                        console.log('To Package:', eventData.data?.event?.data?.items?.[0]?.product?.name || 'Unknown');
                        console.log('Payment Method:', 'Paddle');
                        console.log('Amount:', eventData.data?.event?.data?.order?.total_formatted || 'N/A');
                        console.log('Order ID:', eventData.data?.event?.data?.order?.id || 'N/A');
                        console.log('Timestamp:', new Date().toISOString());
                        console.groupEnd();
                        
                        // You can also log this to your analytics or send to your backend
                        try {
                            fetch('/api/logs/upgrade', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    event: 'subscription_upgraded',
                                    from_package: currentPackage,
                                    to_package: eventData.data?.event?.data?.items?.[0]?.product?.name || 'Unknown',
                                    gateway: 'Paddle',
                                    amount: eventData.data?.event?.data?.order?.total,
                                    currency: eventData.data?.event?.data?.order?.currency,
                                    order_id: eventData.data?.event?.data?.order?.id,
                                    timestamp: new Date().toISOString()
                                })
                            });
                        } catch (logError) {
                            console.error('Failed to log upgrade:', logError);
                        }
                    }

                    showSuccess(message, text).then(() => {
                        console.log('🔄 Redirecting to dashboard...');
                        window.location.href = '/user/dashboard';
                    });

                } else if (eventData.data?.event?.name === 'checkout.failed') {
                    console.error('❌ Paddle checkout failed');
                    const actionText = isUpgradeRequest ? 'upgrade' : 'payment';
                    showError(`${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Failed`, `Your ${actionText} failed. Please try again.`);

                } else if (eventData.data?.event?.name === 'checkout.closed' && !eventData.data.success) {
                    console.log('ℹ️ Paddle checkout cancelled by user');
                    const actionText = isUpgradeRequest ? 'upgrade' : 'payment';
                    showInfo(`${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Cancelled`, `Your ${actionText} was cancelled. You can try again anytime.`);

                } else {
                    console.log('ℹ️ Paddle event not handled:', eventData.data?.event?.name);
                }
            }

            /**
             * Setup upgrade-specific UI elements
             */
            function setupUpgradeUI() {
                console.log('=== SETTING UP UPGRADE UI ===');

                // Add upgrade notice
                const container = document.querySelector('.container');
                if (container) {
                    const upgradeNotice = document.createElement('div');
                    upgradeNotice.className = 'upgrade-notice';
                    upgradeNotice.innerHTML = `
                <div style="background: #e0f2fe; border: 1px solid #0288d1; border-radius: 8px; padding: 16px; margin: 20px 0; text-align: center;">
                    <strong>🔄 Upgrade Mode</strong><br>
                    <span style="color: #0277bd;">You're upgrading from <strong>${currentPackage}</strong> using your original payment method: <strong>${selectedGateway}</strong></span>
                </div>
            `;
                    container.insertBefore(upgradeNotice, container.querySelector('.pricing-grid'));
                    console.log('✅ Upgrade notice added to page');
                }

                // Update button text and disable lower-tier packages
                let buttonsProcessed = 0;
                document.querySelectorAll('.checkout-button').forEach(button => {
                    const packageElement = button.closest('.card');
                    const packageName = button.getAttribute('data-package');
                    const priceElement = packageElement.querySelector('.price');
                    const priceText = priceElement.textContent;
                    const packagePrice = parseFloat(priceText.replace(/[^0-9.]/g, ''));

                    console.log(`Processing upgrade UI for button ${++buttonsProcessed}:`, {
                        packageName,
                        packagePrice,
                        currentPackagePrice,
                        isUpgrade: packagePrice > currentPackagePrice
                    });

                    // Disable packages that aren't upgrades (same or lower price)
                    if (packagePrice <= currentPackagePrice && packageName !== 'Enterprise') {
                        button.disabled = true;
                        button.textContent = 'Not an Upgrade';
                        button.classList.remove('dark');
                        button.classList.add('disabled-package');
                        packageElement.style.opacity = '0.6';
                        console.log(`Disabled ${packageName} - not an upgrade`);

                    } else if (packageName === currentPackage) {
                        // Current package
                        button.classList.add('active');
                        button.disabled = true;
                        console.log(`Marked ${packageName} as current package`);

                    } else {
                        // Valid upgrade
                        button.innerHTML = `Upgrade to ${packageName}`;
                        console.log(`Enabled ${packageName} as upgrade option`);
                    }
                });

                console.log(`Processed ${buttonsProcessed} buttons for upgrade UI`);
            }

            /**
             * Utility functions for showing alerts
             */
            function showSuccess(title, text) {
                console.log('Showing success message:', {
                    title,
                    text
                });
                if (typeof Swal !== 'undefined') {
                    return Swal.fire({
                        icon: 'success',
                        title: title,
                        text: text,
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert(`${title}: ${text}`);
                    return Promise.resolve();
                }
            }

            function showError(title, text) {
                console.error('Showing error message:', {
                    title,
                    text
                });
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: title,
                        text: text,
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert(`${title}: ${text}`);
                }
            }

            function showInfo(title, text) {
                console.log('Showing info message:', {
                    title,
                    text
                });
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: title,
                        text: text,
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert(`${title}: ${text}`);
                }
            }

            // Add CSS for disabled packages
            const style = document.createElement('style');
            style.textContent = `
        .btn.disabled-package {
            background: #6b7280 !important;
            color: white !important;
            cursor: not-allowed !important;
        }
        .btn.disabled-package:hover {
            background: #6b7280 !important;
        }
    `;
            document.head.appendChild(style);
            console.log('Upgrade CSS styles added');

            console.log('SUBSCRIPTION PAGE INITIALIZATION COMPLETE');
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