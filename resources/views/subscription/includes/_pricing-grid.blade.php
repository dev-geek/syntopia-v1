<div class="pricing-grid">
    @foreach ($packages as $package)
        @php
            $isCurrentPackage = $currentPackage == $package->name;
            $isAvailable = isset($packageAvailability[$package->name]) ? $packageAvailability[$package->name] : true;
            $isDisabled = $isCurrentPackage || !$isAvailable;
            $cardClass = $loop->iteration % 2 == 1 ? 'card-dark' : 'card-light';
            if ($isDisabled && !$isCurrentPackage) {
                $cardClass .= ' disabled-package';
            }
        @endphp
        <div class="card {{ $cardClass }}">
            <h3>{{ $package->name }}</h3>
            @if ($package->name == 'Enterprise')
                <p class="price">Custom</p>
            @else
                <p class="price">${{ number_format($package->price, 0) }} <span
                        class="per-month">/{{ $package->duration }}</span></p>
            @endif

            @php
                $isCurrentPackage = $currentPackage == $package->name;
                $isAvailable = isset($packageAvailability[$package->name]) ? $packageAvailability[$package->name] : true;
                $isDisabled = $isCurrentPackage || !$isAvailable;

                $buttonClass = $isCurrentPackage ? 'active' : ($isDisabled ? 'disabled' : 'dark');
                $buttonAction = $isCurrentPackage ? 'current' :
                    (isset($isUpgrade) && $isUpgrade ? 'upgrade' :
                    (isset($pageType) && $pageType === 'downgrade' ? 'downgrade' : 'new'));
            @endphp

            <button class="btn {{ $buttonClass }} checkout-button"
                data-package="{{ $package->name }}"
                data-action="{{ $buttonAction }}"
                {{ $isDisabled ? 'disabled' : '' }}
                @if ($package->name == 'Enterprise') onclick="window.location.href='https://syntopia.ai/contact-us/'" @endif
                >
                @if ($package->name == 'Enterprise')
                    Get in Touch
                @elseif ($isCurrentPackage)
                    ✓ Current Plan
                @elseif (!$isAvailable)
                    @if (isset($isUpgrade) && $isUpgrade)
                        Not Available for Upgrade
                    @elseif (isset($pageType) && $pageType === 'downgrade')
                        Not Available for Downgrade
                    @else
                        Not Available
                    @endif
                @elseif (isset($isUpgrade) && $isUpgrade)
                    Upgrade to {{ $package->name }}
                @elseif (isset($pageType) && $pageType === 'downgrade')
                    Downgrade to {{ $package->name }}
                @else
                    Get Started
                @endif
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

