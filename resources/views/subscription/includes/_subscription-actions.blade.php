@if (empty($hasActiveAddon) || !($hasActiveAddon ?? false))
    @php
        $isFreePlan = $isFreePlan ?? false;
    @endphp

    <a class="btn btn-primary" href="{{ route('subscription') }}">
        <i class="fas fa-edit mr-1"></i>Update Subscription
    </a>

    @if(!$isFreePlan)
        <button class="btn btn-danger" id="cancelSubscriptionBtn">
            <i class="fas fa-times mr-1"></i>Cancel Subscription
        </button>
    @endif
@endif

