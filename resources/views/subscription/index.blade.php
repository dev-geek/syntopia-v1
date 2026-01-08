<!DOCTYPE html>
<html lang="en">
<head>
    @include('subscription.includes._head-meta')
    @include('subscription.includes._head-csp')

    <title>Syntopia Pricing</title>

    @include('subscription.includes._head-assets')
    <link rel="stylesheet" href="{{ asset('css/subscription.css') }}">

    @include('subscription.includes._payment-gateways')

    <x-facebook-pixel />

    <x-tiktok-pixel />
</head>

<body>
    @include('components.spinner-overlay')

    <div class="pricing-header">
        <x-logo />
        <div class="dropdown account-dropdown">
            <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-user-circle"></i>
                Account
            </button>
            <div class="dropdown-menu">
                <a href="{{ route('user.dashboard') }}" class="dropdown-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="#" class="dropdown-item logout-item"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
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
                Plans For Every Type of Business
            </h2>
            <p class="section-subtitle">
                SYNTOPIA creates hyperrealistic, interactive AI avatars that revolutionize how businesses and
                individuals connect with their audiences.
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

    <!-- Global Spinner Utilities -->
    <script src="{{ asset('js/spinner-utils.js') }}"></script>
</body>

</html>
