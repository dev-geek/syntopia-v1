@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<style>
    /* Subscription Details Page Styling - Consistent with DataTables Theme */
    .subscription-card {
        border-radius: 1rem !important;
        overflow: hidden !important;
        background: #fff;
        box-shadow: 0 4px 24px rgba(13,110,253,0.07);
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
        box-shadow: 0 8px 32px rgba(13,110,253,0.07);
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
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
        box-shadow: 0 8px 25px rgba(13,110,253,0.1);
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
        box-shadow: 0 12px 30px rgba(0,0,0,0.15);
    }

    .action-card.bg-warning {
        background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
    }

    .action-card.bg-info {
        background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%) !important;
    }

    .action-card .card-header {
        background: rgba(255,255,255,0.1);
        border-bottom: 1px solid rgba(255,255,255,0.2);
        color: #fff;
    }

    .action-card .card-body {
        color: #fff;
    }

    .action-card .btn-light {
        background: rgba(255,255,255,0.9);
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
        box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.25);
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
            <div class="row">
                <div class="col-12">
                    <div class="card subscription-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-credit-card mr-2"></i>
                                Current Subscription Status
                            </h3>

                            <div class="float-right">
                                @if ($hasActiveSubscription && $canUpgrade)
                                <a class="btn btn-success"
                                        href="{{ route('pricing', ['type' => 'upgrade']) }}">
                                    <i class="fas fa-arrow-up mr-1"></i>Upgrade Subscription
                                </a>
                                <a class="btn btn-info" href="{{ route('pricing', ['type' => 'downgrade']) }}">
                                    <i class="fas fa-arrow-down mr-1"></i>Downgrade Subscription
                                </a>
                                <button class="btn btn-danger" id="cancelSubscriptionBtn">
                                    <i class="fas fa-times mr-1"></i>Cancel Subscription
                                </button>
                                @elseif ($hasActiveSubscription && !$canUpgrade)
                                    <a class="btn btn-success"
                                        href="{{ route('pricing', ['type' => 'upgrade']) }}">
                                        <i class="fas fa-arrow-up mr-1"></i>Upgrade Subscription
                                    </a>
                                    <a class="btn btn-info" href="{{ route('pricing', ['type' => 'downgrade']) }}">
                                        <i class="fas fa-arrow-down mr-1"></i>Downgrade Subscription
                                    </a>
                                    <button class="btn btn-danger" id="cancelSubscriptionBtn">
                                        <i class="fas fa-times mr-1"></i>Cancel Subscription
                                    </button>
                                @elseif (!$hasActiveSubscription)
                                    <a class="btn btn-warning" href="{{ route('pricing', ['type' => 'new']) }}">
                                        <i class="fas fa-shopping-cart mr-1"></i>Buy Subscription
                                    </a>
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
                                    <div class="info-card">
                                        <h3 class="card-title">
                                            <i class="fas fa-box mr-2"></i>Package Information
                                        </h3>
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
                                    @if ($user->subscription_starts_at || $user->subscription_ends_at)
                                        <div class="info-card">
                                            <h3 class="card-title">
                                                <i class="fas fa-calendar-alt mr-2"></i>Subscription Period
                                            </h3>
                                            <div class="row">
                                                <div class="col-12">
                                                    @if ($user->subscription_starts_at)
                                                        <p class="text-muted mb-2"><strong>Start Date:</strong></p>
                                                        <p class="mb-2">
                                                            <i class="fas fa-play-circle text-success mr-1"></i>
                                                            {{ $user->subscription_starts_at->format('F j, Y') }}
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
                                                                <span class="badge badge-warning ml-2">Expires Soon</span>
                                                            @endif
                                                        </p>
                                                        @if (!$isExpired && $calculatedEndDate)
                                                            <p class="text-muted mb-2"><strong>Days Remaining:</strong></p>
                                                            <p class="mb-2">
                                                                <i class="fas fa-clock text-info mr-1"></i>
                                                                {{ $calculatedEndDate->diffInDays(now()) }} days
                                                            </p>
                                                        @endif
                                                    @else
                                                        <p class="text-muted mb-2"><strong>End Date:</strong></p>
                                                        <p class="mb-2">
                                                            <i class="fas fa-question-circle text-muted mr-1"></i>
                                                            Not available
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    @if ($user->license_key)
                                        <div class="info-card">
                                            <h3 class="card-title">
                                                <i class="fas fa-key mr-2"></i>License Information
                                            </h3>
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
                                                                data-copy="element" data-copy-element="licenseKey" data-toast="true" data-success-text="License key copied to clipboard!">
                                                                <i class="fas fa-copy"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Action Cards based on subscription status --}}
                                    @if (!$hasActiveSubscription)
                                        <div class="card action-card bg-warning text-white mb-3">
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
                                        <div class="card action-card bg-info text-white mb-3">
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
