@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<style>
    /* Subscription Details Page Styling - Consistent with DataTables Theme */
    .subscription-card {
        border-radius: 1rem !important;
        overflow: hidden !important;
        background: #fff;
        box-shadow: 0 4px 24px rgba(13, 110, 253, 0.07);
        border: none;
        margin-bottom: 1.5rem;
    }

    .subscription-card .card-header {
        background: linear-gradient(90deg, #e3eafc 0%, #f1f5fb 100%);
        color: #0d6efd;
        font-weight: 800;
        border-bottom: 2px solid #e3eafc;
        font-size: 1.08rem;
        letter-spacing: 0.5px;
        padding: 1rem 1.5rem;
    }

    .subscription-card .card-body {
        padding: 2rem 1.5rem;
        box-shadow: 0 8px 32px rgba(13, 110, 253, 0.07);
        border-radius: 1.2rem;
    }

    .subscription-card .card-title {
        color: #0d6efd;
        font-weight: 700;
        margin: 0;
    }

    /* Badge Styling - Consistent with DataTables */
    .badge {
        font-size: 0.75rem;
        padding: 0.4rem 0.8rem;
        border-radius: 0.5rem;
        font-weight: 600;
    }

    /* Ensure long status badges wrap inside their container */
    .status-badge {
        display: inline-flex !important;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: wrap;
        max-width: 100%;
        white-space: normal;
        word-break: break-word;
        line-height: 1.25;
    }

    .status-badge i {
        flex: 0 0 auto;
        align-self: center;
        line-height: 1;
        vertical-align: middle;
        transform: translateY(1px);
    }

    .badge-success {
        background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
        color: #fff;
    }

    .badge-warning {
        background: linear-gradient(90deg, #ffc107 0%, #fd7e14 100%);
        color: #fff;
    }

    .badge-secondary {
        background: linear-gradient(90deg, #6c757d 0%, #495057 100%);
        color: #fff;
    }

    .badge-danger {
        background: linear-gradient(90deg, #dc3545 0%, #e74c3c 100%);
        color: #fff;
    }

    .badge-info {
        background: linear-gradient(90deg, #17a2b8 0%, #20c997 100%);
        color: #fff;
    }

    .badge-primary {
        background: linear-gradient(90deg, #0d6efd 0%, #0dcaf0 100%);
        color: #fff;
    }

    /* Button Styling - Consistent with DataTables */
    .btn {
        border-radius: 0.5rem;
        font-weight: 600;
        transition: all 0.2s;
        padding: 0.5rem 1rem;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-success {
        background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
        border: none;
        color: #fff;
    }

    .btn-info {
        background: linear-gradient(90deg, #17a2b8 0%, #20c997 100%);
        border: none;
        color: #fff;
    }

    .btn-warning {
        background: linear-gradient(90deg, #ffc107 0%, #fd7e14 100%);
        border: none;
        color: #fff;
    }

    .btn-danger {
        background: linear-gradient(90deg, #dc3545 0%, #e74c3c 100%);
        border: none;
        color: #fff;
    }

    .btn-primary {
        background: linear-gradient(90deg, #0d6efd 0%, #0dcaf0 100%);
        border: none;
        color: #fff;
    }

    /* Info Cards Styling */
    .info-card {
        background: linear-gradient(135deg, #f8fafd 0%, #e9f3ff 100%);
        border: 1px solid #e3eafc;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }

    .info-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(13, 110, 253, 0.1);
    }

    .info-card .card-title {
        color: #0d6efd;
        font-weight: 700;
        margin-bottom: 1rem;
        font-size: 1.1rem;
    }

    .info-card .text-muted {
        color: #6c757d !important;
        font-weight: 600;
    }

    .info-card p {
        color: #495057;
        margin-bottom: 0.5rem;
    }

    /* Action Cards */
    .action-card {
        border-radius: 1rem;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    }

    .action-card.bg-warning {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
    }

    .action-card.bg-info {
        background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%) !important;
    }

    .action-card .card-header {
        background: rgba(255, 255, 255, 0.1);
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
    }

    .action-card .card-body {
        color: #fff;
    }

    .action-card .btn-light {
        background: rgba(255, 255, 255, 0.9);
        border: none;
        color: #495057;
        font-weight: 600;
    }

    .action-card .btn-light:hover {
        background: #fff;
        transform: translateY(-1px);
    }

    /* License Key Input Group */
    .input-group {
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .input-group .form-control {
        border: 1px solid #dee2e6;
        border-radius: 0.5rem 0 0 0.5rem;
        padding: 0.75rem;
        background: #f8fafd;
    }

    .input-group .form-control:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        outline: none;
    }

    .input-group .btn {
        border-radius: 0 0.5rem 0.5rem 0;
        background: linear-gradient(90deg, #6c757d 0%, #495057 100%);
        border: none;
        color: #fff;
    }

    .input-group .btn:hover {
        background: linear-gradient(90deg, #495057 0%, #343a40 100%);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .subscription-card .card-body {
            padding: 1.5rem 1rem;
        }

        .info-card {
            padding: 1rem;
        }

        .btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.9rem;
        }
    }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Subscription Details</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Subscription Details</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">

            @if($hasActiveSubscription)
                {{-- Scheduled Downgrade Notice --}}
                @if ($hasPendingDowngrade && $pendingDowngradeDetails)
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info upgrade-notice"
                                 style="background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%); border: none; color: white; border-radius: 1rem; box-shadow: 0 4px 24px rgba(23, 162, 184, 0.15); position: relative;">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-clock fa-2x mr-3" style="color: rgba(255,255,255,0.8);"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="alert-heading mb-2" style="color: white; font-weight: 700;">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            Downgrade Scheduled
                                        </h5>
                                        <p class="mb-2" style="color: white; font-size: 1.1rem; line-height: 1.5;">
                                            Your subscription will downgrade to
                                            <strong>{{ $pendingDowngradeDetails['target_package'] ?? 'the selected plan' }}</strong>
                                            at the end of your current billing cycle. It will activate on <strong>{{ $pendingDowngradeDetails['scheduled_activation_date'] ?? 'N/A' }}</strong>.
                                        </p>
                                        <p class="mb-0" style="color: rgba(255,255,255,0.9); font-size: 1rem;">
                                            <i class="fas fa-exclamation-triangle mr-2"></i>
                                            <strong>Important:</strong> Your current plan remains active until
                                            expiration{{ $calculatedEndDate ? ' on ' . $calculatedEndDate->format('F j, Y') : '' }}.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                @endif

                @if(isset($purchasedAddons) && $purchasedAddons->count())
                    <div class="row">
                        <div class="col-12">
                            <div class="info-card">
                                {{--                            <h3 class="card-title">--}}
                                {{--                                <i class="fas fa-puzzle-piece mr-2"></i>Active Add-ons--}}
                                {{--                            </h3>--}}
                                <div class="row">
                                    <div class="col-12">
                                        <ul class="list-group">
                                            @foreach($purchasedAddons as $addonOrder)
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>{{ $addonOrder->package->name ?? (is_array($addonOrder->metadata) && isset($addonOrder->metadata['addon']) ? ucwords(str_replace(['_', '-'], ' ', $addonOrder->metadata['addon'])) : 'Add-on') }}</strong>
                                                        <br>
                                                        <small class="text-muted">Purchased on {{ $addonOrder->created_at->format('F j, Y') }}</small>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="badge badge-success px-3 py-2">Active</span>
                                                        <span class="badge badge-info px-3 py-2 ml-1">No expiry</span>
                                                        @if(!empty($addonOrder->amount))
                                                            <br>
                                                            <small class="text-muted">Paid ${{ number_format($addonOrder->amount, 2) }} {{ $addonOrder->currency }}</small>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Scheduled Cancellation Notice --}}
                @if ($hasScheduledCancellation && $calculatedEndDate)
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-warning cancellation-notice"
                                 style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); border: none; color: white; border-radius: 1rem; box-shadow: 0 4px 24px rgba(255, 193, 7, 0.15); position: relative;">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle fa-2x mr-3"
                                           style="color: rgba(255,255,255,0.8);"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="alert-heading mb-2" style="color: white; font-weight: 700;">
                                            <i class="fas fa-clock mr-2"></i>
                                            Cancellation Scheduled
                                        </h5>
                                        <p class="mb-2" style="color: white; font-size: 1.1rem; line-height: 1.5;">
                                            Your subscription cancellation has been scheduled and will take effect on
                                            <strong>{{ $calculatedEndDate->format('F j, Y') }}</strong>.
                                        </p>
                                        <p class="mb-0" style="color: rgba(255,255,255,0.9); font-size: 1rem;">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            <strong>Important:</strong> Your subscription remains fully active until the end
                                            of your current billing period. You can continue using all premium features
                                            until {{ $calculatedEndDate->format('F j, Y') }}.
                                            <br><small
                                                style="color: rgba(255,255,255,0.8);">({{ $calculatedEndDate->diffInDays(now()) }}
                                                days remaining)</small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="row">
                    <div class="col-12">
                        <div class="card subscription-card">
                            <div class="card-header">

                                <div class="float-right">
                                    @if ($hasActiveSubscription && $canUpgrade)
                                        @php
                                            $lockTooltip = '';
                                            if ($isUpgradeLocked) {
                                                $lockTooltip = 'Further plan changes are locked until your upgraded plan expires' . ($calculatedEndDate ? ' on ' . $calculatedEndDate->format('F j, Y') : '');
                                            } elseif ($hasPendingDowngrade && $pendingDowngradeDetails) {
                                                $lockTooltip = 'Further plan changes are locked due to a pending downgrade scheduled for ' . ($pendingDowngradeDetails['scheduled_activation_date'] ?? 'the end of your billing period');
                                            }
                                            $lockButtons = $isUpgradeLocked || ($hasPendingDowngrade && $pendingDowngradeDetails);
                                        @endphp
                                        @if($lockButtons)
                                            <span data-toggle="tooltip" data-placement="bottom" title="{{ $lockTooltip }}">
                                            <a class="btn btn-success disabled" href="javascript:void(0);" aria-disabled="true" tabindex="-1" style="pointer-events: none;">
                                                <i class="fas fa-arrow-up mr-1"></i>Upgrade Subscription
                                            </a>
                                        </span>
                                            @if(!$isFreePackage)
                                                <span data-toggle="tooltip" data-placement="bottom" title="{{ $lockTooltip }}">
                                            <a class="btn btn-info disabled" href="javascript:void(0);" aria-disabled="true" tabindex="-1" style="pointer-events: none;">
                                                <i class="fas fa-arrow-down mr-1"></i>Downgrade Subscription
                                            </a>
                                        </span>
                                            @endif
                                        @else
                                            <a class="btn btn-success" href="{{ route('subscription', ['type' => 'upgrade']) }}">
                                                <i class="fas fa-arrow-up mr-1"></i>Upgrade Subscription
                                            </a>
                                            @if(!$isFreePackage)
                                                <a class="btn btn-info" href="{{ route('subscription', ['type' => 'downgrade']) }}">
                                                    <i class="fas fa-arrow-down mr-1"></i>Downgrade Subscription
                                                </a>
                                            @endif
                                        @endif
                                        @if(!$isFreePackage)
                                            @if ($hasScheduledCancellation)
                                                <button class="btn btn-secondary" disabled>
                                                    <i class="fas fa-clock mr-1"></i>Cancellation Scheduled
                                                </button>
                                            @else
                                                <button class="btn btn-danger" id="cancelSubscriptionBtn">
                                                    <i class="fas fa-times mr-1"></i>Cancel Subscription
                                                </button>
                                            @endif
                                        @endif
                                    @elseif ($hasActiveSubscription && !$canUpgrade)
                                        @php
                                            $lockTooltip = '';
                                            if ($isUpgradeLocked) {
                                                $lockTooltip = 'Further plan changes are locked until your upgraded plan expires' . ($calculatedEndDate ? ' on ' . $calculatedEndDate->format('F j, Y') : '');
                                            } elseif ($hasPendingDowngrade && $pendingDowngradeDetails) {
                                                $lockTooltip = 'Further plan changes are locked due to a pending downgrade scheduled for ' . ($pendingDowngradeDetails['scheduled_activation_date'] ?? 'the end of your billing period');
                                            }
                                            $lockButtons = $isUpgradeLocked || ($hasPendingDowngrade && $pendingDowngradeDetails);
                                        @endphp
                                        @if($lockButtons)
                                            <span data-toggle="tooltip" data-placement="bottom" title="{{ $lockTooltip }}">
                                            <a class="btn btn-success disabled" href="javascript:void(0);" aria-disabled="true" tabindex="-1" style="pointer-events: none;">
                                                <i class="fas fa-arrow-up mr-1"></i>Upgrade Subscription
                                            </a>
                                        </span>
                                            @if(!$isFreePackage)
                                                <span data-toggle="tooltip" data-placement="bottom" title="{{ $lockTooltip }}">
                                            <a class="btn btn-info disabled" href="javascript:void(0);" aria-disabled="true" tabindex="-1" style="pointer-events: none;">
                                                <i class="fas fa-arrow-down mr-1"></i>Downgrade Subscription
                                            </a>
                                        </span>
                                            @endif
                                        @else
                                            <a class="btn btn-success" href="{{ route('subscription', ['type' => 'upgrade']) }}">
                                                <i class="fas fa-arrow-up mr-1"></i>Upgrade Subscription
                                            </a>
                                            @if(!$isFreePackage)
                                                <a class="btn btn-info" href="{{ route('subscription', ['type' => 'downgrade']) }}">
                                                    <i class="fas fa-arrow-down mr-1"></i>Downgrade Subscription
                                                </a>
                                            @endif
                                        @endif
                                        @if(!$isFreePackage)
                                            @if ($hasScheduledCancellation)
                                                <button class="btn btn-secondary" disabled>
                                                    <i class="fas fa-clock mr-1"></i>Cancellation Scheduled
                                                </button>
                                            @else
                                                <button class="btn btn-danger" id="cancelSubscriptionBtn">
                                                    <i class="fas fa-times mr-1"></i>Cancel Subscription
                                                </button>
                                            @endif
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="card-body">
                                {{-- Subscription Status Alert --}}
                                {{-- Alerts removed: now handled by SWAL --}}

                                <div class="row">
                                    <div class="modal fade" id="cancelSubscriptionModal" tabindex="-1" role="dialog"
                                         aria-labelledby="cancelSubscriptionModalLabel" aria-hidden="true">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="cancelSubscriptionModalLabel">Confirm
                                                        Subscription Cancellation</h5>
                                                    <button type="button" class="close" data-dismiss="modal"
                                                            aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    Are you sure you want to cancel your subscription? This action cannot be
                                                    undone, and you will lose access to premium features.
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary"
                                                            data-dismiss="modal">Close</button>
                                                    <form action="{{ route('payments.cancel-subscription') }}"
                                                          method="POST">
                                                        @csrf
                                                        <button type="submit" class="btn btn-danger">Confirm
                                                            Cancellation</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- First Row: Package and License Information --}}
                                    <div class="col-lg-6 col-md-12 mb-4">
                                        <div class="info-card">
                                            {{--                                        <h3 class="card-title">--}}
                                            {{--                                            <i class="fas fa-box mr-2"></i>Package Information--}}
                                            {{--                                        </h3>--}}
                                            <div class="row">
                                                <div class="col-12">
                                                    @php
                                                        $displayCurrentPackage = $currentPackage;
                                                    @endphp

                                                    <p class="text-muted mb-2"><strong>Current Plan:</strong></p>
                                                    <p class="mb-2">
                                                        {{ $displayCurrentPackage ?? 'No Package' }}
                                                        @if ($displayCurrentPackage && strtolower($displayCurrentPackage) === 'free')
                                                            <span class="badge badge-secondary ml-2">Free Plan</span>
                                                        @endif
                                                    </p>

                                                    @if ($hasPendingDowngrade && !empty($pendingDowngradeDetails['target_package']))
                                                        <p class="text-muted mb-2"><strong>Plan after downgrade:</strong>
                                                        </p>
                                                        <p class="mb-2">
                                                            {{ $pendingDowngradeDetails['target_package'] }}
                                                            <span class="badge badge-info ml-2"><i
                                                                    class="fas fa-arrow-down mr-1"></i> Downgrading</span>
                                                        </p>
                                                    @endif

                                                    <p class="text-muted mb-2"><strong>Status:</strong></p>
                                                    @if ($hasActiveSubscription)
                                                        @if ($hasScheduledCancellation)
                                                            <div>
                                                            <span class="badge badge-success px-3 py-2">
                                                                <i class="fas fa-check-circle"></i> Active Until
                                                                Expiration
                                                            </span>
                                                                <br>
                                                                <span class="badge badge-warning px-3 py-2 mt-2">
                                                                <i class="fas fa-clock"></i> Cancellation Scheduled
                                                            </span>
                                                                <p class="text-muted mt-2 small">
                                                                    <i class="fas fa-info-circle"></i>
                                                                    <strong>Your subscription remains fully active!</strong>
                                                                    You can continue using all premium features until
                                                                    {{ $calculatedEndDate ? $calculatedEndDate->format('F j, Y') : 'the end of your billing cycle' }}.
                                                                </p>
                                                            </div>
                                                        @elseif ($hasPendingDowngrade)
                                                            <div>
                                                            <span class="badge badge-success px-3 py-2 status-badge">
                                                                <i class="fas fa-check-circle"></i> Active Until
                                                                Expiration
                                                            </span>
                                                                <br>
                                                                <span class="badge badge-info px-3 py-2 mt-2 status-badge">
                                                                <i class="fas fa-arrow-down"></i> Downgrade to
                                                                {{ $pendingDowngradeDetails['target_package'] ?? 'next plan' }}
                                                                Scheduled
                                                            </span>
                                                                <p class="text-muted mt-2 small">
                                                                    <i class="fas fa-info-circle"></i>
                                                                    Your plan will change to
                                                                    {{ $pendingDowngradeDetails['target_package'] ?? 'the selected plan' }}
                                                                    at period end on <strong>{{ $pendingDowngradeDetails['scheduled_activation_date'] ?? 'N/A' }}</strong>.
                                                                </p>
                                                            </div>
                                                        @else
                                                            <span class="badge badge-success px-3 py-2">
                                                            <i class="fas fa-check-circle"></i> Active
                                                        </span>
                                                        @endif
                                                    @elseif ($isExpired)
                                                        <span class="badge badge-danger px-3 py-2 status-badge">
                                                        <i class="fas fa-times-circle"></i> Expired
                                                    </span>
                                                    @else
                                                        <span class="badge badge-warning px-3 py-2 status-badge">
                                                        <i class="fas fa-exclamation-circle"></i> Inactive
                                                    </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- License Information Section --}}
                                    <div class="col-lg-6 col-md-12 mb-4">
                                        @if ($user->userLicence && $user->userLicence->license_key)
                                            <div class="info-card">
                                                {{--                                            <h3 class="card-title">--}}
                                                {{--                                                <i class="fas fa-key mr-2"></i>License Information--}}
                                                {{--                                            </h3>--}}
                                                <div class="row">
                                                    <div class="col-12">
                                                        <p class="text-muted mb-2"><strong>License Key:</strong></p>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control"
                                                                   value="{{ $user->userLicence->license_key }}" readonly
                                                                   id="licenseKey">
                                                            <div class="input-group-append">
                                                                <button class="btn btn-outline-secondary" type="button"
                                                                        data-copy="element" data-copy-element="licenseKey"
                                                                        data-toast="true"
                                                                        data-success-text="License key copied to clipboard!">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Second Row: License Period and Action Cards --}}
                                    @if ($user->userLicence)
                                        <div class="col-lg-6 col-md-12 mb-4">
                                            <div class="info-card">
                                                {{--                                            <h3 class="card-title">--}}
                                                {{--                                                <i class="fas fa-calendar-alt mr-2"></i>License Period--}}
                                                {{--                                            </h3>--}}
                                                <div class="row">
                                                    <div class="col-12">
                                                        @if ($user->userLicence->activated_at)
                                                            <p class="text-muted mb-2"><strong>Activated Date:</strong></p>
                                                            <p class="mb-2">
                                                                <i class="fas fa-play-circle text-success mr-1"></i>
                                                                {{ $user->userLicence->activated_at->format('F j, Y') }}
                                                            </p>
                                                        @endif
                                                        @if ($calculatedEndDate)
                                                            <p class="text-muted mb-2"><strong>End Date:</strong></p>
                                                            <p class="mb-2 {{ $isExpired ? 'text-danger' : '' }}">
                                                                <i class="fas fa-stop-circle text-danger mr-1"></i>
                                                                {{ $calculatedEndDate->format('F j, Y') }}
                                                                @if ($isExpired)
                                                                    <span class="badge badge-danger ml-2">Expired</span>
                                                                @elseif ($calculatedEndDate->diffInDays(now()) <= 7)
                                                                    <span class="badge badge-warning ml-2">Expires
                                                                    Soon</span>
                                                                @endif
                                                            </p>
                                                            @if (!$isExpired && $calculatedEndDate)
                                                                <p class="text-muted mb-2"><strong>Days Remaining:</strong>
                                                                </p>
                                                                <p class="mb-2">
                                                                    <i class="fas fa-clock text-info mr-1"></i>
                                                                    {{ $calculatedEndDate->diffInDays(now()) }} days
                                                                </p>
                                                            @endif
                                                        @else
                                                            <p class="text-muted mb-2"><strong>End Date:</strong></p>
                                                            <p class="mb-2">
                                                                <i class="fas fa-question-circle text-muted mr-1"></i>
                                                                {{ $isFreePackage ? 'None' : 'Not available' }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="row">
                    <div class="col-12">
                        <div class="info-card">
                            <div class="row">
                                <div class="col-12">
                                    <p class="text-muted mb-2"><strong>Status:</strong></p>
                                    <span class="badge badge-warning px-3 py-2 status-badge">
                                    <i class="fas fa-exclamation-circle"></i> No active subscription
                                </span>
                                    <p class="mt-3 mb-3">
                                        You don’t have an active subscription. Choose a plan to get started.
                                    </p>
                                    <a class="btn btn-primary" href="{{ route('subscription') }}">
                                        <i class="fas fa-box-open mr-1"></i> Choose a plan
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(isset($purchasedAddons) && $purchasedAddons->count())
                    <div class="row">
                        <div class="col-12">
                            <div class="info-card">
                                <div class="row">
                                    <div class="col-12">
                                        <ul class="list-group">
                                            @foreach($purchasedAddons as $addonOrder)
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>{{ $addonOrder->package->name ?? (is_array($addonOrder->metadata) && isset($addonOrder->metadata['addon']) ? ucwords(str_replace(['_', '-'], ' ', $addonOrder->metadata['addon'])) : 'Add-on') }}</strong>
                                                        <br>
                                                        <small class="text-muted">Purchased on {{ $addonOrder->created_at->format('F j, Y') }}</small>
                                                    </div>
                                                    <div class="text-right">
                                                        <span class="badge badge-success px-3 py-2">Active</span>
                                                        <span class="badge badge-info px-3 py-2 ml-1">No expiry</span>
                                                        @if(!empty($addonOrder->amount))
                                                            <br>
                                                            <small class="text-muted">Paid ${{ number_format($addonOrder->amount, 2) }} {{ $addonOrder->currency }}</small>
                                                        @endif
                                                    </div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </section>
    <!-- /.content -->
</div>



<!-- /.content-wrapper -->
<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
</aside>
<!-- /.control-sidebar -->
@include('dashboard.includes.footer')

<!-- Hidden form for cancellation -->
<form id="cancelSubscriptionForm" action="{{ route('payments.cancel-subscription') }}" method="POST"
      style="display: none;">
    @csrf
</form>

<style>
    /* Undismissable Upgrade Notice Styles */
    .upgrade-notice {
        position: relative !important;
        z-index: 1000;
        animation: pulse-glow 2s ease-in-out infinite alternate;
    }

    .upgrade-notice .close {
        display: none !important;
    }

    .upgrade-notice:hover {
        transform: translateY(-2px);
        transition: transform 0.3s ease;
        animation: none;
    }

    /* Prevent any dismissal attempts */
    .upgrade-notice[style*="display: none"] {
        display: block !important;
    }

    .upgrade-notice.fade {
        opacity: 1 !important;
    }

    /* Pulsing glow animation */
    @keyframes pulse-glow {
        0% {
            box-shadow: 0 4px 24px rgba(23, 162, 184, 0.15);
        }

        100% {
            box-shadow: 0 4px 32px rgba(23, 162, 184, 0.3);
        }
    }

    /* Cancellation Notice Styles */
    .cancellation-notice {
        position: relative !important;
        z-index: 1000;
        animation: pulse-glow-cancellation 2s ease-in-out infinite alternate;
    }

    .cancellation-notice .close {
        display: none !important;
    }

    .cancellation-notice:hover {
        transform: translateY(-2px);
        transition: transform 0.3s ease;
        animation: none;
    }

    /* Prevent any dismissal attempts */
    .cancellation-notice[style*="display: none"] {
        display: block !important;
    }

    .cancellation-notice.fade {
        opacity: 1 !important;
    }

    /* Pulsing glow animation for cancellation */
    @keyframes pulse-glow-cancellation {
        0% {
            box-shadow: 0 4px 24px rgba(255, 193, 7, 0.15);
        }

        100% {
            box-shadow: 0 4px 32px rgba(255, 193, 7, 0.3);
        }
    }
</style>

<script>
    // Enable Bootstrap tooltips
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
    // Make upgrade notice undismissable
    document.addEventListener('DOMContentLoaded', function() {
        const upgradeNotice = document.querySelector('.upgrade-notice');
        if (upgradeNotice) {
            // Remove any close buttons
            const closeButtons = upgradeNotice.querySelectorAll('.close, .btn-close, [data-dismiss="alert"]');
            closeButtons.forEach(btn => btn.remove());

            // Prevent hiding via JavaScript
            const originalDisplay = upgradeNotice.style.display;
            Object.defineProperty(upgradeNotice.style, 'display', {
                get: function() {
                    return originalDisplay || 'block';
                },
                set: function(value) {
                    if (value === 'none') {
                        console.log('Attempt to hide upgrade notice blocked');
                        return;
                    }
                    originalDisplay = value;
                }
            });

            // Prevent removal from DOM
            const originalRemove = upgradeNotice.remove;
            upgradeNotice.remove = function() {
                console.log('Attempt to remove upgrade notice blocked');
                return false;
            };

            // Prevent parent from removing it
            const parent = upgradeNotice.parentElement;
            if (parent) {
                const originalParentRemove = parent.remove;
                parent.remove = function() {
                    console.log('Attempt to remove parent of upgrade notice blocked');
                    return false;
                };
            }
        }
    });

    // Make cancellation notice undismissable
    document.addEventListener('DOMContentLoaded', function() {
        const cancellationNotice = document.querySelector('.cancellation-notice');
        if (cancellationNotice) {
            // Remove any close buttons
            const closeButtons = cancellationNotice.querySelectorAll(
                '.close, .btn-close, [data-dismiss="alert"]');
            closeButtons.forEach(btn => btn.remove());

            // Prevent hiding via JavaScript
            const originalDisplay = cancellationNotice.style.display;
            Object.defineProperty(cancellationNotice.style, 'display', {
                get: function() {
                    return originalDisplay || 'block';
                },
                set: function(value) {
                    if (value === 'none') {
                        console.log('Attempt to hide cancellation notice blocked');
                        return;
                    }
                    originalDisplay = value;
                }
            });

            // Prevent removal from DOM
            const originalRemove = cancellationNotice.remove;
            cancellationNotice.remove = function() {
                console.log('Attempt to remove cancellation notice blocked');
                return false;
            };

            // Prevent parent from removing it
            const parent = cancellationNotice.parentElement;
            if (parent) {
                const originalParentRemove = parent.remove;
                parent.remove = function() {
                    console.log('Attempt to remove parent of cancellation notice blocked');
                    return false;
                };
            }
        }
    });

    // Initialize SweetAlert for cancellation
    document.getElementById('cancelSubscriptionBtn')?.addEventListener('click', function() {
        Swal.fire({
            title: 'Schedule Subscription Cancellation',
            text: "Your subscription will remain active until the end of your current billing period. You can continue using all premium features until then. Are you sure you want to schedule the cancellation?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, cancel it!',
            cancelButtonText: 'No, keep it',
            reverseButtons: true,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return new Promise((resolve) => {
                    // Submit the form
                    document.getElementById('cancelSubscriptionForm').submit();
                });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Scheduling Cancellation...',
                    text: 'Your subscription cancellation is being scheduled',
                    timer: 2000,
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
        });
    });

    // Handle PayProGlobal payment processing when redirected from PayProGlobal
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const processPayment = urlParams.get('process_payment');
        const gateway = urlParams.get('gateway');
        const orderId = urlParams.get('OrderId') || urlParams.get('order_id');

        // If we have PayProGlobal payment parameters, process the payment
        if (processPayment === 'true' && gateway === 'payproglobal') {
            console.log('PayProGlobal payment processing detected', {
                processPayment,
                gateway,
                orderId,
                allParams: Object.fromEntries(urlParams)
            });

            // Redirect to payments.success to process the payment
            // It will then redirect back to subscription-details
            const paymentParams = new URLSearchParams();
            paymentParams.set('gateway', 'payproglobal');
            if (urlParams.get('user_id')) paymentParams.set('user_id', urlParams.get('user_id'));
            if (urlParams.get('package')) paymentParams.set('package', urlParams.get('package'));
            if (urlParams.get('pending_order_id')) paymentParams.set('pending_order_id', urlParams.get('pending_order_id'));
            if (urlParams.get('action')) paymentParams.set('action', urlParams.get('action'));
            if (orderId) paymentParams.set('OrderId', orderId);

            window.location.href = '/payments/success?' + paymentParams.toString();
        }
    });
</script>

<!-- Add SweetAlert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
