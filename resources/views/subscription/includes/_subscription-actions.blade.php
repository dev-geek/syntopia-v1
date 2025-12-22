@if (empty($hasScheduledCancellation) && (empty($hasActiveAddon) || !($hasActiveAddon ?? false)))
    @php
        $isFreePlan = $isFreePlan ?? false;
        $isBusinessPlan = $isBusinessPlan ?? false;
        $lockTooltip = '';
        if ($isUpgradeLocked) {
            $lockTooltip = 'Further plan changes are locked until your upgraded plan expires' . ($calculatedEndDate ? ' on ' . $calculatedEndDate->format('F j, Y') : '');
        } elseif ($hasPendingDowngrade && $pendingDowngradeDetails) {
            $lockTooltip = 'Further plan changes are locked due to a pending downgrade scheduled for ' . ($pendingDowngradeDetails['scheduled_activation_date'] ?? 'the end of your billing period');
        }
        $lockButtons = $isUpgradeLocked || ($hasPendingDowngrade && $pendingDowngradeDetails);
    @endphp

    @if($lockButtons)
        @if(!$isBusinessPlan)
            <span data-toggle="tooltip" data-placement="bottom" title="{{ $lockTooltip }}">
                <a class="btn btn-success disabled" href="javascript:void(0);" aria-disabled="true" tabindex="-1" style="pointer-events: none;">
                    <i class="fas fa-arrow-up mr-1"></i>Upgrade Subscription
                </a>
            </span>
        @endif
        @if(!$isFreePlan)
            <span data-toggle="tooltip" data-placement="bottom" title="{{ $lockTooltip }}">
                <a class="btn btn-info disabled" href="javascript:void(0);" aria-disabled="true" tabindex="-1" style="pointer-events: none;">
                    <i class="fas fa-arrow-down mr-1"></i>Downgrade Subscription
                </a>
            </span>
        @endif
    @else
        @if(!$isBusinessPlan)
            <a class="btn btn-success" href="{{ route('subscription', ['type' => 'upgrade']) }}">
                <i class="fas fa-arrow-up mr-1"></i>Upgrade Subscription
            </a>
        @endif
        @if(!$isFreePlan)
            <a class="btn btn-info" href="{{ route('subscription', ['type' => 'downgrade']) }}">
                <i class="fas fa-arrow-down mr-1"></i>Downgrade Subscription
            </a>
        @endif
    @endif

    @if(!$isFreePlan)
        <button class="btn btn-danger" id="cancelSubscriptionBtn">
            <i class="fas fa-times mr-1"></i>Cancel Subscription
        </button>
    @endif
@endif

