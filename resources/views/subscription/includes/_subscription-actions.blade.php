@if (empty($hasActiveAddon) || !($hasActiveAddon ?? false))
    @php
        $isFreePlan = $isFreePlan ?? false;
    @endphp

    <a class="btn btn-primary" href="{{ route('subscription') }}">
        <i class="fas fa-edit mr-1"></i>Update Subscription
    </a>

    @if(!$isFreePlan)
        @php
            $isDisabled = $hasScheduledCancellation ?? false;
            $tooltipText = '';
            if ($isDisabled) {
                if ($calculatedEndDate ?? null) {
                    $tooltipText = 'Cancellation is already scheduled. Your subscription will remain active until ' . $calculatedEndDate->format('F j, Y') . '. You cannot schedule another cancellation while one is already pending.';
                } else {
                    $tooltipText = 'Cancellation is already scheduled. You cannot schedule another cancellation while one is already pending.';
                }
            }
        @endphp
        @if($isDisabled && $tooltipText)
            <span data-toggle="tooltip" data-placement="top" title="{{ $tooltipText }}" style="display: inline-block;">
                <button class="btn btn-danger" id="cancelSubscriptionBtn" disabled style="pointer-events: none;">
                    <i class="fas fa-times mr-1"></i>Cancel Subscription
                </button>
            </span>
        @else
            <button class="btn btn-danger" id="cancelSubscriptionBtn">
                <i class="fas fa-times mr-1"></i>Cancel Subscription
            </button>
        @endif
    @endif
@endif

