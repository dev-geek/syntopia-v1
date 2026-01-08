<div class="pricing-grid">
    @foreach ($packages as $package)
        @php
            $isCurrentPackage = $currentPackage == $package->name;
            $isAvailable = isset($packageAvailability[$package->name]) ? $packageAvailability[$package->name] : true;

            // Disable Free plan selection once user has purchased any paid plan
            $hasPaidPlan = isset($purchaseHistory['total_spent']) && $purchaseHistory['total_spent'] > 0;
            $isFreePackage = strtolower($package->name) === 'free';
            if ($isFreePackage && $hasPaidPlan) {
                $isAvailable = false;
            }

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

                // Apply same Free plan disabling logic for button state
                $hasPaidPlan = isset($purchaseHistory['total_spent']) && $purchaseHistory['total_spent'] > 0;
                $isFreePackage = strtolower($package->name) === 'free';
                if ($isFreePackage && $hasPaidPlan) {
                    $isAvailable = false;
                }

                $isDisabled = $isCurrentPackage || !$isAvailable;

                $buttonClass = $isCurrentPackage ? 'active' : ($isDisabled ? 'disabled' : 'dark');

                // Determine button action
                if ($isCurrentPackage) {
                    $buttonAction = 'current';
                } else {
                    $buttonAction = 'new';
                }
            @endphp

            <button class="btn {{ $buttonClass }} checkout-button"
                data-package="{{ $package->name }}"
                data-action="{{ $buttonAction }}"
                data-price="{{ $package->price }}"
                {{ $isDisabled ? 'disabled' : '' }}
                @if ($package->name == 'Enterprise') onclick="window.location.href='https://syntopia.ai/contact-us/'" @endif
                >
                @if ($package->name == 'Enterprise')
                    Get in Touch
                @elseif ($isCurrentPackage)
                    ✓ Current Plan
                @elseif (!$isAvailable)
                    Not Available
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

