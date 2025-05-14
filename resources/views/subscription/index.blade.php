<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Syntopia Pricing</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
    <!-- Payment Gateway Scripts -->
    @php
        $activeGateways = $payment_gateways->pluck('name')->toArray();
    @endphp
    @if (in_array('FastSpring', $activeGateways))
        <script id="fsc-api" src="https://sbl.onfastspring.com/sbl/1.0.3/fastspring-builder.min.js" type="text/javascript"
            data-storefront="{{ config('payment.gateways.fastspring.storefront') }}" data-popup-closed="onFSPopupClosed">
        </script>
        <script>
            function onFSPopupClosed(orderData) {
                try {
                    if (orderData?.reference) {
                        console.log("Product purchased:", orderData.reference);
                        // Handle successful purchase
                        window.location.href = "/payment/success?gateway=fastspring&order=" + orderData.reference;
                    } else {
                        console.warn("Popup closed, but no order data returned.");
                        window.location.href = "/payment/cancel?gateway=fastspring";
                    }
                } catch (err) {
                    console.error("Error in onFSPopupClosed:", err);
                }
            }
        </script>
    @endif

    <!-- Paddle Integration -->
    @if (in_array('Paddle', $activeGateways))
        <script src="https://cdn.paddle.com/paddle/paddle.js"></script>
        <script>
            Paddle.Environment.set('{{ config('payment.gateways.Paddle.environment') }}');
            Paddle.Setup({
                vendor: {{ config('payment.gateways.Paddle.vendor_id') }}
            });
        </script>
    @endif

    <!-- PayProGlobal Integration -->
    @if (in_array('Pay Pro Global', $activeGateways))
        <script src="{{ config('payment.gateways.payproglobal.script_url') }}"></script>
    @endif

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

            <h2 class="section-title">Plans For Every Type of Business</h2>
            <p class="section-subtitle">SYNTOPIA creates hyperrealistic, interactive AI avatars that revolutionize how
                businesses and individuals connect with their audiences. Our avatars can:</p>
            <div class="pricing-grid">
                <!-- Free Plan -->
                <div class="card card-light">
                    <h3>Free</h3>
                    <p class="price">$0 <span class="per-month">/month</span></p>
                    <button class="btn dark checkout-button" data-package="free-plan"
                        {{ $currentPackage == 'Free' ? 'disabled' : '' }}>
                        {{ $currentPackage == 'Free' ? 'Activated' : 'Get Started' }}
                    </button>

                    <p class="included-title">What's included</p>
                    <ul class="features">
                        <li><span class="icon"></span> 1 user</li>
                        <li><span class="icon"></span> 1 livestream room</li>
                        <li><span class="icon"></span> 1 live broadcast (single anchor)</li>
                        <li><span class="icon"></span> <strong>Lite Live Stream (one anchor)</strong></li>
                        <li><span class="icon"></span> 1 Q&A base</li>
                        <li><span class="icon"></span> 10 min live stream duration</li>
                        <li><span class="icon"></span> 5MB storage</li>
                        <li><span class="icon"></span> 5 min video synthesis</li>
                    </ul>
                </div>

                <!-- Starter Plan -->
                <div class="card card-light">
                    <h3>Starter</h3>
                    <p class="price">$390 <span class="per-month">/60hrs a month</span></p>
                    <button class="btn dark checkout-button" data-package="starter-plan"
                        {{ $currentPackage == 'Starter' ? 'disabled' : '' }}>
                        {{ $currentPackage == 'Starter' ? 'Activated' : 'Get Started' }}
                    </button>


                    <p class="included-title">What's included</p>
                    <ul class="features">
                        <li><span class="icon"></span> 1 user</li>
                        <li><span class="icon"></span> 1 livestream room</li>
                        <li><span class="icon"></span> 1 live broadcast (single anchor)</li>
                        <li><span class="icon"></span> <strong>Lite Live Stream (one anchor)</strong></li>
                        <li><span class="icon"></span> 1 livestream account</li>
                        <li><span class="icon"></span> 1 Q&A base</li>
                        <li><span class="icon"></span> 60 hrs streaming</li>
                        <li><span class="icon"></span> 5MB storage</li>
                        <li><span class="icon"></span> AI: 10 creations, 10 rewrites</li>
                        <li><span class="icon"></span> 5 min video synthesis</li>
                    </ul>
                </div>

                <!-- Pro Plan -->
                <div class="card card-dark">
                    <h3>Pro</h3>
                    <p class="price">$780 <span class="per-month">/120hrs a month</span></p>
                    <button class="btn dark checkout-button" data-package="pro-plan"
                        {{ $currentPackage == 'Pro' ? 'disabled' : '' }}>
                        {{ $currentPackage == 'Pro' ? 'Activated' : 'Get Started' }}
                    </button>


                    <p class="included-title">What's included</p>
                    <ul class="features">
                        <li><span class="icon"></span> 2 users</li>
                        <li><span class="icon"></span> 3 livestream rooms</li>
                        <li><span class="icon"></span> 3 live broadcasts (single anchor)</li>
                        <li><span class="icon"></span> <strong>Dual Live Stream (two anchor in one live room)</strong>
                        </li>
                        <li><span class="icon"></span> Pro Live Stream</li>
                        <li><span class="icon"></span> 3 livestream accounts</li>
                        <li><span class="icon"></span> 3 Q&A base</li>
                        <li><span class="icon"></span> 120 hrs streaming</li>
                        <li><span class="icon"></span> 5MB storage</li>
                        <li><span class="icon"></span> AI: 30 creations, 30 rewrites</li>
                        <li><span class="icon"></span> 20 min video synthesis</li>
                    </ul>
                </div>

                <!-- Business Plan -->
                <div class="card card-light">
                    <h3>Business</h3>
                    <p class="price">$2800 <span class="per-month">/unlimited</span></p>
                    <button class="btn dark checkout-button" data-package="business-plan"
                        {{ $currentPackage == 'Business' ? 'disabled' : '' }}>
                        {{ $currentPackage == 'Business' ? 'Activated' : 'Get Started' }}
                    </button>
                    <p class="included-title">What's included</p>
                    <ul class="features">
                        <li><span class="icon"></span> 3 users</li>
                        <li><span class="icon"></span> 1 livestream room</li>
                        <li><span class="icon"></span> 1 live broadcast</li>
                        <li><span class="icon"></span> <strong>Dual Live Stream (two anchor in one live room)</strong>
                        </li>
                        <li><span class="icon"></span> Pro Live Stream</li>
                        <li><span class="icon"></span> Video Live Stream</li>
                        <li><span class="icon"></span> 3 livestream accounts</li>
                        <li><span class="icon"></span> 3 Q&A base</li>
                        <li><span class="icon"></span> Unlimited streaming</li>
                        <li><span class="icon"></span> 5MB storage</li>
                        <li><span class="icon"></span> AI: 90 creations, 90 rewrites</li>
                        <li><span class="icon"></span> 60 min video synthesis</li>
                    </ul>
                </div>

                <!-- Enterprise Plan -->
                <div class="card card-dark last">
                    <h3>Enterprise</h3>
                    <p class="price">Custom</p>
                    <button class="btn white">Get in Touch</button>
                    <p class="included-title">What's included</p>
                    <ul class="features">
                        <li><span class="icon"></span> Custom users & rooms</li>
                        <li><span class="icon"></span> Custom livestream features</li>
                        <li><span class="icon"></span> Custom Q&A bases</li>
                        <li><span class="icon"></span> Custom AI & video tools</li>
                        <li><span class="icon"></span> Unlimited resources</li>
                        <li><span class="icon"></span> Tailored support & solutions</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="addons-wrapper">
        <div class="container">
            <div class="badge-wrapper">
                <div class="pricing-badge">Addons</div>
            </div>
            <h2 class="section-title">Customized Addons</h2>
            <div class="addons-grid-wrapper">
                <div class="addons-grid">
                    <!-- Avatar Customization -->
                    <div class="addon-card">
                        <h3>Avatar Customization</h3>
                        <p class="addon-price">$2800</p>
                        <button class="btn dark">Get Started</button>
                        <p class="included-title">What's included</p>
                        <ul class="features">
                            <li><span class="icon"></span> 30+ min of training video recorded</li>
                            <li><span class="icon"></span> Digital avatar: 1 hairstyle, outfit</li>
                            <li><span class="icon"></span> Guide provided for video recording</li>
                            <li><span class="icon"></span> Customer handles processing & upload</li>
                            <li><span class="icon"></span> 1 optimization pass included</li>
                            <li><span class="icon"></span> Minor imperfections may remain</li>
                            <li><span class="icon"></span> One-time setup, no annual fee</li>
                        </ul>
                    </div>

                    <!-- Voice Customization -->
                    <div class="addon-card">
                        <h3>Voice Customization</h3>
                        <p class="addon-price">$2200</p>
                        <button class="btn dark">Get Started</button>
                        <p class="included-title">What's included</p>
                        <ul class="features">
                            <li><span class="icon"></span> 30+ min of valid audio recorded</li>
                            <li><span class="icon"></span> Customer handles voice processing</li>
                            <li><span class="icon"></span> Guide provided for best results</li>
                            <li><span class="icon"></span> Natural flaws may occur (noise, tone)</li>
                            <li><span class="icon"></span> One-time setup, no usage fee</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        Having trouble? Contact us at
        <a href="mailto:support@syntopia.ai">support@syntopia.ai</a>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Get current package and active gateways from backend
            const currentPackage = "{{ $currentPackage ?? '' }}";
            const activeGateways = @json($payment_gateways->pluck('name')->toArray() ?? []);

            // Display error message if no gateways available
            if (!activeGateways || activeGateways.length === 0) {
                console.error('No payment gateways configured');
            }

            // Add click event listeners to all checkout buttons
            document.querySelectorAll('.checkout-button').forEach(button => {
                button.addEventListener('click', function() {
                    // Don't proceed if button is disabled (current plan)
                    if (this.disabled) return;

                    // Disable button temporarily to prevent double-clicks
                    this.disabled = true;

                    // Get product package from button's data attribute
                    const productPath = this.getAttribute('data-package');

                    // Process checkout with the selected package
                    processCheckout(productPath);

                    // Re-enable button after delay
                    setTimeout(() => {
                        this.disabled = false;
                    }, 3000);
                });
            });

            // Check if a package was specified in the URL
            const packageFromURL = "{{ $currentPackage ?? '' }}";
            if (packageFromURL && packageFromURL !== '' && packageFromURL !== currentPackage) {
                processCheckout(packageFromURL.toLowerCase() + "-plan");
            }

            // Main checkout processing function
            function processCheckout(productPath) {
                if (!activeGateways || activeGateways.length === 0) {
                    alert('No payment gateways are available. Please contact support.');
                    return;
                }

                // Use the first active gateway
                const activeGateway = activeGateways[0];

                try {
                    switch (activeGateway) {
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
                            throw new Error(`Unsupported payment gateway: ${activeGateway}`);
                    }
                } catch (error) {
                    console.error('Checkout error:', error);
                    alert('Payment gateway error. Please try again later or contact support.');
                }
            }

            // FastSpring-specific processing
            function processFastSpring(productPath) {
                if (typeof fastspring === 'undefined' || !fastspring.builder) {
                    throw new Error('FastSpring is not properly initialized');
                }

                fastspring.builder.add(productPath);

                // Use timeout to ensure the product is added before checkout
                setTimeout(() => {
                    fastspring.builder.checkout();
                }, 100);
            }

            // Paddle-specific processing
            function processPaddle(productPath) {
                const packageName = productPath.replace('-plan', '');

                fetch(`/api/paddle/checkout/${packageName}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                            'Authorization': `Bearer ${localStorage.getItem('auth_token')}` // If using token auth
                        },
                        credentials: 'include' // Important for session cookies
                    })
                    .then(response => {
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            return response.text().then(text => {
                                throw new Error(`Expected JSON but got: ${text.substring(0, 100)}...`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.checkoutUrl) {
                            throw new Error('Invalid checkout URL received from server');
                        }

                        if (typeof Paddle === 'undefined') {
                            throw new Error('Paddle payment system not loaded');
                        }

                        Paddle.Checkout.open({
                            override: data.checkoutUrl
                        });
                    })
                    .catch(error => {
                        console.error('Paddle checkout error:', error);
                        alert(`Payment error: ${error.message}`);
                    });
            }

            // PayProGlobal-specific processing
            function processPayProGlobal(productPath) {
                // Extract package name (remove "-plan" suffix)
                const packageName = productPath.replace('-plan', '');

                // Request checkout URL from server
                fetch(`/api/payproglobal/checkout/${packageName}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.checkoutUrl) {
                            throw new Error('Invalid checkout URL received from server');
                        }

                        // Redirect to PayProGlobal checkout
                        window.location.href = data.checkoutUrl;
                    })
                    .catch(error => {
                        console.error('PayProGlobal checkout error:', error);
                        alert('Payment processing error. Please try again or contact support.');
                    });
            }
        });
    </script>
</body>

</html>
