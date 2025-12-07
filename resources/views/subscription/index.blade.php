<!DOCTYPE html>
<html lang="en">
<head>
    @include('subscription.includes._head-meta')
    @include('subscription.includes._head-csp')

    <title>Syntopia Pricing</title>

    @include('subscription.includes._head-assets')
    <link rel="stylesheet" href="{{ asset('css/subscription.css') }}">

    @include('subscription.includes._payment-gateways')

    <!-- FirstPromoter Tracking Script -->
    <script>(function(w){w.fpr=w.fpr||function(){w.fpr.q = w.fpr.q||[];w.fpr.q[arguments[0]=='set'?'unshift':'push'](arguments);};})(window);

fpr("init", {cid:"s5acbg16"}); 

fpr("click");

</script>
<script src="https://cdn.firstpromoter.com/fpr.js" async></script>

    <!-- Facebook Pixel Code -->
    <script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '1908567163348981');
    fbq('track', 'PageView');
    </script>
    <noscript>
    <img height="1" width="1" 
    src="https://www.facebook.com/tr?id=1908567163348981&ev=PageView
    &noscript=1"/>
    </noscript>
    <!-- End Facebook Pixel Code -->
</head>

<body>
    @include('components.spinner-overlay')

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
            <h2 class="section-title">
                @if (isset($isUpgrade) && $isUpgrade)
                    Upgrade Your Subscription
                @elseif (isset($pageType) && $pageType === 'downgrade')
                    Downgrade Your Subscription
                @else
                    Plans For Every Type of Business
                @endif
            </h2>
            <p class="section-subtitle">
                @if (isset($isUpgrade) && $isUpgrade)
                    Choose a higher-tier plan to unlock more features. Your current subscription will be prorated.
                @elseif (isset($pageType) && $pageType === 'downgrade')
                    Select a lower-tier plan. The change will take effect at the end of your current billing cycle.
                @else
                    SYNTOPIA creates hyperrealistic, interactive AI avatars that revolutionize how businesses and
                    individuals connect with their audiences.
                @endif
            </p>
            @include('subscription.includes._pricing-grid')
        </div>
    </div>
    @include('subscription.includes._addons')
    <footer>
        Having trouble? Contact us at
        <a href="mailto:support@syntopia.ai">support@syntopia.ai</a>
    </footer>
    @include('subscription.includes._scripts')
</body>

</html>
