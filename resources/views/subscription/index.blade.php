<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy"
        content="
        default-src 'self' data: gap: https://ssl.gstatic.com http://livebuzzstudio.test https://livebuzzstudio.test;
        style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com;
        font-src 'self' https://fonts.gstatic.com;
        script-src 'self' http://livebuzzstudio.test https://livebuzzstudio.test https://somedomain.com https://sbl.onfastspring.com https://cdn.jsdelivr.net https://cdn.paddle.com https://sandbox-cdn.paddle.com https://secure.payproglobal.com 'unsafe-inline' 'unsafe-eval';
        img-src 'self' https://syntopia.ai https://sbl.onfastspring.com data:;
        connect-src 'self' http://livebuzzstudio.test https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://sandbox-api.paddle.com https://sandbox-cdn.paddle.com;
        frame-src 'self' http://livebuzzstudio.test https://livebuzzstudio.test https://livebuzzstudio.test.onfastspring.com https://sbl.onfastspring.com https://cdn.paddle.com https://sandbox-cdn.paddle.com;
        media-src 'self' data: https://sbl.onfastspring.com;">
    <title>Syntopia Pricing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Payment Gateway Scripts -->
    @php
        $activeGateways = $payment_gateways->pluck('name')->toArray();
    @endphp
    @if (in_array('FastSpring', $activeGateways))
        <script src="https://sbl.onfastspring.com/js/checkout/button.js"
            data-button-id="{{ $currentLoggedInUserPaymentGateway ?? 'FastSpring' }}"></script>
    @endif
    @if (in_array('Paddle', $activeGateways))
        <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
    @endif
    @if (in_array('PayPro Global', $activeGateways))
        <script src="https://secure.payproglobal.com/js/custom/checkout.js"></script>
    @endif

    <!-- FastSpring Integration -->
    @if ($activeGateway && $activeGateway->name === 'FastSpring')
        <script id="fsc-api" src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js" type="text/javascript"
            data-storefront="livebuzzstudio.test.onfastspring.com/popup-test-87654-payment" data-popup-closed="onFSPopupClosed"
            data-data-callback="handleFastSpringSuccess" data-debug="true"></script>
        <script>
            let currentProductPath = ''; // Store productPath globally

            function processFastSpring(productPath) {
                try {
                    if (typeof fastspring === 'undefined' || !fastspring.builder) {
                        throw new Error('FastSpring is not properly initialized');
                    }
                    fastspring.builder.reset();
                    const packageName = productPath.replace('-plan', '').toLowerCase();
                    console.log('Adding FastSpring product:', packageName);
                    currentProductPath = productPath; // Store productPath
                    fastspring.builder.add(packageName);
                    setTimeout(() => {
                        console.log('Opening FastSpring checkout...');
                        fastspring.builder.checkout();
                    }, 500);
                } catch (error) {
                    console.error('FastSpring processing error:', error);
                    throw error;
                }
            }

            function onFSPopupClosed(orderData) {
                try {
                    console.log('FastSpring popup closed with data:', orderData);
                    if (orderData && (orderData.reference || orderData.id)) {
                        const orderId = orderData.reference || orderData.id;

                        if (typeof fastspring !== 'undefined' && fastspring.builder) {
                            fastspring.builder.reset();
                        }
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '/api/payment/success';
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
                        console.warn("FastSpring popup closed without order data");
                        Swal.fire({
                            icon: 'info',
                            title: 'Payment Cancelled',
                            text: 'Your payment was cancelled. You can try again anytime.',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            window.location.href = "/all-subscriptions";
                        });
                    }
                } catch (err) {
                    console.error("Error in onFSPopupClosed:", err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Processing Error',
                        text: 'There was an error processing your payment. Please contact support if your payment was charged.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = "/all-subscriptions";
                    });
                }
            }
        </script>
    @endif

    <!-- Paddle Integration -->
    @if ($activeGateway && $activeGateway->name === 'Paddle')
        <script src="https://cdn.paddle.com/paddle/paddle.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                try {
                    // Set the Paddle environment (sandbox or production)
                    Paddle.Environment.set('{{ config('paddle.env', 'sandbox') }}');

                    // Initialize Paddle with your vendor ID
                    Paddle.Setup({
                        vendor: 31861,
                        eventCallback: function(event) {
                            console.log('Paddle Event:', event);

                            // Handle specific events
                            switch (event.name) {
                                case 'checkout.completed':
                                    // Redirect or show success message
                                    window.location.href = '{{ route('subscriptions.index') }}';
                                    break;
                                case 'checkout.closed':
                                    // Optional: Handle when user closes checkout
                                    console.log('Checkout was closed by user');
                                    break;
                                case 'checkout.loaded':
                                    console.log('Checkout loaded successfully');
                                    break;
                                case 'checkout.error':
                                    console.error('Checkout error:', event.error);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Payment Error',
                                        text: 'An error occurred during checkout. Please try again or contact support.',
                                        confirmButtonText: 'OK'
                                    });
                                    break;
                            }
                        }
                    });


                    console.log('Paddle initialized successfully for vendor: 31861');
                } catch (error) {
                    console.error('Paddle initialization error:', error);

                    // Fallback UI for Paddle loading failure
                    const paddleContainer = document.getElementById('paddle-checkout-container');
                    if (paddleContainer) {
                        paddleContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <h5>Payment System Unavailable</h5>
                                <p>We're experiencing issues with our payment processor. Please try again later or contact support.</p>
                                <a href="" class="btn btn-outline-danger">
                                    Contact Support
                                </a>
                            </div>
                        `;
                    }

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
    @if ($activeGateway && $activeGateway->name === 'PayPro Global')
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

        /* addons style  */

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
    </style>
</head>

<body>
    <div class="pricing-header">
        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
        <button type="button" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
            Log out
        </button>
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
                        <button class="btn dark checkout-button" data-package="{{ $package->name }}"
                            {{ $currentPackage == $package->name ? 'disabled' : '' }}>
                            {{ $package->name == 'Enterprise' ? 'Get in Touch' : ($currentPackage == $package->name ? 'Activated' : 'Get Started') }}
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
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // Payment Gateway Processing
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
                            throw new Error(Unsupported payment gateway: $ {
                                selectedGateway
                            });
                    }
                } catch (error) {
                    console.error('Checkout error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment Gateway Error',
                        text: error.message ||
                            'Payment gateway error. Please try again later or contact support.',
                        confirmButtonText: 'OK'
                    });
                }
            }

            function processFastSpring(productPath) {
                try {
                    if (typeof fastspring === 'undefined' || !fastspring.builder) {
                        throw new Error('FastSpring is not properly initialized');
                    }
                    fastspring.builder.reset();
                    const packageName = productPath.replace('-plan', '').toLowerCase();
                    console.log('Adding FastSpring product:', packageName);
                    fastspring.builder.add(packageName);
                    setTimeout(() => {
                        console.log('Opening FastSpring checkout...');
                        fastspring.builder.checkout();
                    }, 500);
                } catch (error) {
                    console.error('FastSpring processing error:', error);
                    throw error;
                }
            }

            function processPaddle(productPath) {
                const packageName = productPath.replace('-plan', '');
                const apiUrl = /api/paddle / checkout / $ {
                    packageName
                };
                fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(HTTP $ {
                                response.status
                            }: $ {
                                response.statusText
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || data.error || 'Unknown error occurred');
                        }
                        if (data.checkout_url) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Opening Checkout',
                                text: 'Payment window is loading...',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            setTimeout(() => {
                                window.location.href = data.checkout_url;
                            }, 2000);
                        } else if (data.transaction_id && typeof Paddle !== 'undefined') {
                            Paddle.Checkout.open({
                                transactionId: data.transaction_id
                            });
                        } else {
                            throw new Error('No transaction ID or checkout URL provided');
                        }
                    })
                    .catch(error => {
                        console.error('Checkout error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Checkout Failed',
                            text: error.message ||
                                'An error occurred while processing your checkout. Please try again later.',
                            confirmButtonText: 'OK'
                        });
                    });
            }

            function processPayProGlobal(productPath) {
                const packageName = productPath.replace('-plan', '');
                const apiUrl = /api/payproglobal / checkout / $ {
                    packageName
                };

                // Open a blank popup synchronously to avoid popup blocker
                const width = 1000;
                const height = 700;
                const left = (screen.width - width) / 2;
                const top = (screen.height - height) / 2;
                const paymentWindow = window.open(
                    'about:blank',
                    'PayProGlobalCheckout',
                    width = $ {
                        width
                    }, height = $ {
                        height
                    }, top = $ {
                        top
                    }, left = $ {
                        left
                    }
                );

                if (!paymentWindow) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Popup Blocked',
                        text: 'Please allow popups for this site in your browser settings and try again.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Show a loading message in the popup
                paymentWindow.document.write('<html><body><p>Loading payment page...</p></body></html>');

                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).then(response => {
                    if (!response.ok) {
                        paymentWindow.close();
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                }).then(data => {
                    console.log('PayProGlobal checkout URL:', data.checkoutUrl);
                    if (!data.checkoutUrl) {
                        paymentWindow.close();
                        throw new Error('Invalid checkout URL received from server');
                    }
                    // Redirect the popup to the checkout URL
                    paymentWindow.location.href = data.checkoutUrl;

                    // Monitor if the popup is closed
                    const checkWindowClosed = setInterval(() => {
                        if (paymentWindow.closed) {
                            clearInterval(checkWindowClosed);
                            console.log(
                                'PayProGlobal payment window closed. Calling save-details API.');

                            // Call the save-details API
                            fetch('/api/payment/save-details', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    payment_gateway_id: "{{ $activeGateway->id ?? '' }}", // Ensure this is the correct ID
                                    package_id: 3
                                })
                            }).then(response => {
                                if (!response.ok) {
                                    throw new Error('Failed to save payment details');
                                }
                                return response.json();
                            }).then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Payment Details Saved',
                                        text: data.message ||
                                            'Your payment details have been saved successfully.',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        window.location
                                    .reload(); // Reload the page after success
                                    });
                                } else {
                                    throw new Error(data.error ||
                                        'Failed to save payment details');
                                }
                            }).catch(error => {
                                console.error('Error saving payment details:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: error.message ||
                                        'An error occurred while saving payment details. Please try again or contact support.',
                                    confirmButtonText: 'OK'
                                });
                            });
                        }
                    }, 500);
                }).catch(error => {
                    console.error('PayProGlobal checkout error:', error);
                    paymentWindow.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Payment Processing Error',
                        text: error.message ||
                            'Payment processing error. Please try again or contact support.',
                        confirmButtonText: 'OK'
                    });
                });
            }
        });
    </script>
</body>

</html>
