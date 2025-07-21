@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Subscription Details</h1>
                </div>
                <div class="col-sm-6">
                </div>
            </div>
        </div>
    </div>
    <!-- /.content-header -->
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Current Subscription Status</h3>

                            <div class="float-right">
                                @if ($hasActiveSubscription && $canUpgrade)
                                <a class="btn btn-success"
                                        href="{{ route('pricing', ['type' => 'upgrade']) }}">Upgrade Subscription</a>
                                {{-- downgrade --}}
                                <a class="btn btn-info" href="{{ route('pricing', ['type' => 'downgrade']) }}">Downgrade Subscription</a>

                                    <button class="btn btn-danger" id="cancelSubscriptionBtn">
                                        Cancel Subscription
                                    </button>
                                @elseif ($hasActiveSubscription && !$canUpgrade)
                                    <a class="btn btn-success"
                                        href="{{ route('pricing', ['type' => 'upgrade']) }}">Upgrade Subscription</a>
                                        <a class="btn btn-info" href="{{ route('pricing', ['type' => 'downgrade']) }}">Downgrade Subscription</a>
                                    <button class="btn btn-danger" id="cancelSubscriptionBtn">
                                        Cancel Subscription
                                    </button>
                                @elseif (!$hasActiveSubscription)
                                    <a class="btn btn-warning" href="{{ route('pricing', ['type' => 'new']) }}">Buy
                                        Subscription</a>
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
                                <div class="col-md-6">
                                    <div class="card bg-light mb-3">
                                        <div class="card-header">
                                            <h3 class="card-title">Package Information</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12">
                                                    <p class="text-muted mb-2"><strong>Current Package:</strong></p>
                                                    <p class="mb-2">
                                                        {{ $currentPackage ?? 'No Package' }}
                                                        @if ($currentPackage && strtolower($currentPackage) === 'free')
                                                            <span class="badge badge-secondary ml-2">Free Plan</span>
                                                        @endif
                                                    </p>
                                                    <p class="text-muted mb-2"><strong>Status:</strong></p>
                                                    @if ($hasActiveSubscription)
                                                        <span class="badge badge-success px-3 py-2">
                                                            <i class="fas fa-check-circle"></i> Active
                                                        </span>
                                                    @elseif ($isExpired)
                                                        <span class="badge badge-danger px-3 py-2">
                                                            <i class="fas fa-times-circle"></i> Expired
                                                        </span>
                                                    @else
                                                        <span class="badge badge-warning px-3 py-2">
                                                            <i class="fas fa-exclamation-circle"></i> Inactive
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @if ($user->subscription_starts_at || $user->subscription_ends_at)
                                        <div class="card bg-light">
                                            <div class="card-header">
                                                <h3 class="card-title">Subscription Period</h3>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-12">
                                                        @if ($user->subscription_starts_at)
                                                            <p class="text-muted mb-2"><strong>Start Date:</strong></p>
                                                            <p class="mb-2">
                                                                {{ $user->subscription_starts_at->format('F j, Y') }}
                                                            </p>
                                                        @endif
                                                        @if ($calculatedEndDate)
                                                            <p class="text-muted mb-2"><strong>End Date:</strong></p>
                                                            <p class="mb-2 {{ $isExpired ? 'text-danger' : '' }}">
                                                                {{ $calculatedEndDate->format('F j, Y') }}
                                                                @if ($isExpired)
                                                                    <span
                                                                        class="badge badge-danger ml-2">Expired</span>
                                                                @elseif ($calculatedEndDate->diffInDays(now()) <= 7)
                                                                    <span class="badge badge-warning ml-2">Expires
                                                                        Soon</span>
                                                                @endif
                                                            </p>
                                                            @if (!$isExpired && $calculatedEndDate)
                                                                <p class="text-muted mb-2"><strong>Days
                                                                        Remaining:</strong></p>
                                                                <p class="mb-2">
                                                                    {{ $calculatedEndDate->diffInDays(now()) }} days
                                                                </p>
                                                            @endif
                                                        @else
                                                            <p class="text-muted mb-2"><strong>End Date:</strong></p>
                                                            <p class="mb-2">Not available</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    @if ($user->license_key)
                                        <div class="card bg-light mb-3">
                                            <div class="card-header">
                                                <h3 class="card-title">License Information</h3>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-12">
                                                        <p class="text-muted mb-2"><strong>License Key:</strong></p>
                                                        <div class="input-group">
                                                            <input type="text" class="form-control"
                                                                value="{{ $user->license_key }}" readonly
                                                                id="licenseKey">
                                                            <div class="input-group-append">
                                                                <button class="btn btn-outline-secondary"
                                                                    type="button"
                                                                    onclick="copyToClipboard('licenseKey')">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Action Cards based on subscription status --}}
                                    @if (!$hasActiveSubscription)
                                        <div class="card bg-warning text-white mb-3">
                                            <div class="card-header">
                                                <h3 class="card-title">
                                                    <i class="fas fa-shopping-cart"></i> Get Started
                                                </h3>
                                            </div>
                                            <div class="card-body">
                                                <p>Choose from our available subscription plans to get started.</p>
                                                <a href="{{ route('pricing') }}" class="btn btn-light">
                                                    <i class="fas fa-eye"></i> View Plans
                                                </a>
                                            </div>
                                        </div>
                                    @elseif ($hasActiveSubscription && strtolower($currentPackage) === 'free')
                                        <div class="card bg-info text-white mb-3">
                                            <div class="card-header">
                                                <h3 class="card-title">
                                                    <i class="fas fa-star"></i> Upgrade to Premium
                                                </h3>
                                            </div>
                                            <div class="card-body">
                                                <p>Unlock premium features with our paid plans.</p>
                                                <a href="{{ route('pricing') }}" class="btn btn-light">
                                                    <i class="fas fa-star"></i> View Premium Plans
                                                </a>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
<form id="cancelSubscriptionForm" action="{{ route('payments.cancel-subscription') }}" method="POST" style="display: none;">
    @csrf
</form>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text)
            .then(() => {
                Swal.fire({icon: 'success', title: 'Copied!', text: 'Text copied to clipboard!'});
            })
            .catch(err => {
                console.error('Failed to copy text: ', err);
                Swal.fire({icon: 'error', title: 'Error', text: 'Failed to copy text. Please try again.'});
            });
    }

    // Initialize SweetAlert for cancellation
    document.getElementById('cancelSubscriptionBtn')?.addEventListener('click', function() {
        Swal.fire({
            title: 'Confirm Subscription Cancellation',
            text: "Are you sure you want to cancel your subscription? This action cannot be undone, and you will lose access to premium features.",
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
                    title: 'Canceling...',
                    text: 'Your subscription is being canceled',
                    timer: 2000,
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
        });
    });
</script>

<!-- Add SweetAlert CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
