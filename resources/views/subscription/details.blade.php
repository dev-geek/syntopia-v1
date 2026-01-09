@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')
@include('components.spinner-overlay')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper subscription-details-page">
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
                        <div class="alert alert-info upgrade-notice subscription-notice">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock fa-2x mr-3 notice-icon"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="alert-heading mb-2 notice-heading">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Downgrade Scheduled
                                    </h5>
                                    <p class="mb-2 notice-text">
                                        Your subscription will downgrade to
                                        <strong>{{ $pendingDowngradeDetails['target_package'] ?? 'the selected plan' }}</strong>
                                        at the end of your current billing cycle. It will activate on <strong>{{ $pendingDowngradeDetails['scheduled_activation_date'] ?? 'N/A' }}</strong>.
                                    </p>
                                    <div class="mb-2 notice-text-small">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>What happens when the downgrade activates:</strong>
                                        <ul class="mb-0 mt-2 pl-4" style="list-style-type: disc;">
                                            <li>Payment of <strong>${{ number_format($pendingDowngradeDetails['target_package_price'] ?? 0, 2) }}</strong> will be automatically processed</li>
                                            <li>Your subscription will be updated to <strong>{{ $pendingDowngradeDetails['target_package'] ?? 'the selected plan' }}</strong></li>
                                            <li>A new license will be created and activated for the downgraded package</li>
                                            <li>Your access will continue seamlessly with the new plan features</li>
                                        </ul>
                                    </div>
                                    <p class="mb-0 notice-text-small">
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

            @include('subscription.includes._purchased-addons')

            {{-- Scheduled Cancellation Notice --}}
            @if ($hasScheduledCancellation && $calculatedEndDate)
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-info cancellation-notice subscription-notice">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-calendar-alt fa-2x mr-3 notice-icon"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="alert-heading mb-3 notice-heading">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Your Subscription Will End Soon
                                    </h5>
                                    <div class="mb-3">
                                        <p class="mb-2 notice-text">
                                            Your subscription is scheduled to end on <strong>{{ $calculatedEndDate->format('F j, Y') }}</strong>.
                                        </p>
                                        <div class="alert alert-light border-left-info pl-3 py-2 mb-2">
                                            <p class="mb-1">
                                                <i class="fas fa-check-circle text-success mr-2"></i>
                                                <strong>Good news!</strong> You still have <strong>{{ $calculatedEndDate->diffInDays(now()) }} {{ $calculatedEndDate->diffInDays(now()) == 1 ? 'day' : 'days' }}</strong> of full access remaining.
                                            </p>
                                            <p class="mb-0 small text-muted">
                                                Continue enjoying all premium features until {{ $calculatedEndDate->format('F j, Y') }}. No changes will occur before then.
                                            </p>
                                        </div>
                                    </div>
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
                                @if ($hasActiveSubscription && (empty($hasActiveAddon) || !($hasActiveAddon ?? false)))
                                    @php
                                        $isFreePlan = $currentPackage && strtolower($currentPackage) === 'free';
                                        $isBusinessPlan = $currentPackage && strtolower($currentPackage) === 'business';
                                    @endphp
                                    @include('subscription.includes._subscription-actions', ['isFreePlan' => $isFreePlan, 'isBusinessPlan' => $isBusinessPlan, 'hasScheduledCancellation' => $hasScheduledCancellation ?? false, 'calculatedEndDate' => $calculatedEndDate ?? null])
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

                                {{-- Package Information Card --}}
                                @if(empty($hasActiveAddon) || !($hasActiveAddon ?? false))
                                <div class="col-lg-6 col-md-12 mb-4">
                                    <div class="info-card">
                                        <h5 class="info-card-title">
                                            <i class="fas fa-box"></i>Package Information
                                        </h5>
                                        <div>
                                            <div class="info-item">
                                            <span class="info-label">Current Plan:</span>
                                            <span class="info-value">
                                                <strong>{{ $currentPackage ?? 'No Package' }}</strong>
                                                @php
                                                    $isFreePlan = $currentPackage && strtolower($currentPackage) === 'free';
                                                @endphp
                                                @if ($isFreePlan)
                                                    <span class="badge badge-secondary ml-2">Free Plan</span>
                                                @endif
                                            </span>
                                        </div>

                                        <div class="info-item">
                                            <span class="info-label">Subscription Status:</span>
                                            <span class="info-value">
                                                @if ($hasActiveSubscription)
                                                    @if ($hasScheduledCancellation)
                                                        <span class="badge badge-warning px-3 py-2">
                                                            <i class="fas fa-clock"></i> Active Until {{ $calculatedEndDate ? $calculatedEndDate->format('M j, Y') : 'Expiration' }} (Cancellation Scheduled)
                                                        </span>
                                                    @elseif ($hasPendingDowngrade)
                                                        <span class="badge badge-info px-3 py-2">
                                                            <i class="fas fa-check-circle"></i> Active Until {{ $calculatedEndDate ? $calculatedEndDate->format('M j, Y') : 'Expiration' }} (Downgrade to {{ $pendingDowngradeDetails['target_package'] ?? 'Starter' }} Scheduled)
                                                        </span>
                                                    @else
                                                        <span class="badge badge-success px-3 py-2">
                                                            <i class="fas fa-check-circle"></i> Active
                                                        </span>
                                                    @endif
                                                @elseif ($isExpired)
                                                    <span class="badge badge-danger px-3 py-2">
                                                        <i class="fas fa-times-circle"></i> Expired
                                                    </span>
                                                @else
                                                    <span class="badge badge-warning px-3 py-2">
                                                        <i class="fas fa-exclamation-circle"></i> Inactive
                                                    </span>
                                                @endif
                                            </span>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                {{-- License Information Card --}}
                                @if(empty($hasActiveAddon) || !($hasActiveAddon ?? false))
                                @if ($user->userLicence)
                                    <div class="col-lg-6 col-md-12 mb-4">
                                        <div class="info-card">
                                            <h5 class="info-card-title">
                                                <i class="fas fa-key"></i>License Information
                                            </h5>
                                            <div>
                                                @if ($user->userLicence->license_key)
                                                    <div class="info-item">
                                                        <span class="info-label">License Key:</span>
                                                        <div class="info-value">
                                                            <div class="input-group">
                                                                <input type="text" class="form-control" value="{{ $user->userLicence->license_key }}" readonly id="licenseKey">
                                                                <div class="input-group-append">
                                                                    <button class="btn btn-outline-secondary" type="button" data-copy="element" data-copy-element="licenseKey" data-toast="true" data-success-text="License key copied to clipboard!">
                                                                        <i class="fas fa-copy"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="info-item">
                                                    <span class="info-label">License Status:</span>
                                                    <span class="info-value">
                                                        @if ($user->userLicence->is_active)
                                                            <span class="badge badge-success px-3 py-2">
                                                                <i class="fas fa-check-circle"></i> Active
                                                            </span>
                                                        @else
                                                            <span class="badge badge-warning px-3 py-2">
                                                                <i class="fas fa-exclamation-circle"></i> Inactive
                                                            </span>
                                                        @endif
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @endif

                                {{-- Subscription Period Card --}}
                                @if(empty($hasActiveAddon) || !($hasActiveAddon ?? false))
                                @if ($user->userLicence)
                                    <div class="col-lg-6 col-md-12 mb-4">
                                        <div class="info-card">
                                            <h5 class="info-card-title">
                                                <i class="fas fa-calendar-alt"></i>Subscription Period
                                            </h5>
                                            <div>
                                                @if ($user->userLicence->activated_at)
                                                    <div class="info-item">
                                                        <span class="info-label">Start Date:</span>
                                                        <span class="info-value">
                                                            <i class="fas fa-calendar-check text-success"></i>
                                                            {{ $user->userLicence->activated_at->format('F j, Y') }}
                                                        </span>
                                                    </div>
                                                @endif

                                                @if (!$isFreePlan)
                                                    @if ($calculatedEndDate)
                                                        <div class="info-item">
                                                            <span class="info-label">End Date:</span>
                                                            <span class="info-value {{ $isExpired ? 'text-danger' : '' }}">
                                                                <i class="fas fa-calendar-times text-danger"></i>
                                                                {{ $calculatedEndDate->format('F j, Y') }}
                                                                @if ($isExpired)
                                                                    <span class="badge badge-danger ml-2">Expired</span>
                                                                @elseif ($calculatedEndDate->diffInDays(now()) <= 7)
                                                                    <span class="badge badge-warning ml-2">Expires Soon</span>
                                                                @endif
                                                            </span>
                                                        </div>
                                                        @if (!$isExpired && $calculatedEndDate)
                                                            <div class="info-item">
                                                                <span class="info-label">Days Remaining:</span>
                                                                <span class="info-value">
                                                                    <i class="fas fa-clock text-info"></i>
                                                                    {{ $calculatedEndDate->diffInDays(now()) }} days
                                                                </span>
                                                            </div>
                                                        @endif
                                                    @else
                                                        <div class="info-item">
                                                            <span class="info-label">End Date:</span>
                                                            <span class="info-value">
                                                                <i class="fas fa-question-circle text-muted"></i>
                                                                Not available
                                                            </span>
                                                        </div>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
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

            @include('subscription.includes._purchased-addons')
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

<script>
    // Enable Bootstrap tooltips
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
    function makeNoticeUndismissable(selector, noticeName) {
        const notice = document.querySelector(selector);
        if (!notice) return;

        const closeButtons = notice.querySelectorAll('.close, .btn-close, [data-dismiss="alert"]');
        closeButtons.forEach(btn => btn.remove());

        const originalDisplay = notice.style.display;
        Object.defineProperty(notice.style, 'display', {
            get: function() {
                return originalDisplay || 'block';
            },
            set: function(value) {
                if (value === 'none') {
                    console.log(`Attempt to hide ${noticeName} notice blocked`);
                    return;
                }
                originalDisplay = value;
            }
        });

        const originalRemove = notice.remove;
        notice.remove = function() {
            console.log(`Attempt to remove ${noticeName} notice blocked`);
            return false;
        };

        const parent = notice.parentElement;
        if (parent) {
            const originalParentRemove = parent.remove;
            parent.remove = function() {
                console.log(`Attempt to remove parent of ${noticeName} notice blocked`);
                return false;
            };
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        makeNoticeUndismissable('.upgrade-notice', 'upgrade');
        makeNoticeUndismissable('.cancellation-notice', 'cancellation');
    });

    // Initialize SweetAlert for cancellation
    const cancelBtn = document.getElementById('cancelSubscriptionBtn');
    if (cancelBtn && !cancelBtn.disabled) {
        cancelBtn.addEventListener('click', function() {
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
                        if (window.SpinnerUtils && typeof SpinnerUtils.show === 'function') {
                            SpinnerUtils.show('Scheduling cancellation...');
                        }
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
    }
</script>

{{-- FirstPromoter Referral Tracking (after successful payment) --}}
@if(session('success') && Auth::check())
    <script>
        fpr("referral", {email: "{{ Auth::user()->email }}"});
    </script>
@endif

<!-- Add SweetAlert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/spinner-utils.js') }}"></script>
