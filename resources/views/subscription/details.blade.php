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
                                    <a class="btn btn-primary" href="{{ route('subscriptions.index', ['type' => 'upgrade']) }}">Upgrade Subscription</a>
                                @elseif ($hasActiveSubscription && !$canUpgrade)
                                    <a class="btn btn-success" href="{{ route('subscriptions.index', ['type' => 'downgrade']) }}">Downgrade Subscription</a>
                                @elseif (!$hasActiveSubscription)
                                    <a class="btn btn-danger" href="{{ route('subscriptions.index', ['type' => 'cancel']) }}">Cancel Subscription</a>
                                @else
                                    <a class="btn btn-warning" href="{{ route('subscriptions.index', ['type' => 'new']) }}">Buy Subscription</a>
                                    @endif
                            </div>
                        </div>
                        <div class="card-body">
                            {{-- Subscription Status Alert --}}
                            @if (!$hasActiveSubscription)
                                <div class="alert alert-warning alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <h5><i class="icon fas fa-exclamation-triangle"></i> No Active Subscription!</h5>
                                    @if ($isExpired)
                                        Your subscription has expired. Please purchase a new subscription to continue using our services.
                                    @else
                                        You don't have an active subscription. Please purchase a subscription to access our services.
                                    @endif
                                </div>
                            @elseif ($hasActiveSubscription && !$canUpgrade && strtolower($currentPackage) === 'free')
                                <div class="alert alert-info alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <h5><i class="icon fas fa-info-circle"></i> Free Plan Active</h5>
                                    You're currently on the free plan. Upgrade to a paid subscription to unlock premium features.
                                </div>
                            @elseif ($hasActiveSubscription && $canUpgrade)
                                <div class="alert alert-success alert-dismissible">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <h5><i class="icon fas fa-check-circle"></i> Subscription Active</h5>
                                    Your subscription is active. You can upgrade to a higher plan anytime.
                                </div>
                            @endif

                            <div class="row">
                                <div class="modal fade" id="cancelSubscriptionModal" tabindex="-1" role="dialog" aria-labelledby="cancelSubscriptionModalLabel" aria-hidden="true">
                                    <div class="modal-dialog" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="cancelSubscriptionModalLabel">Confirm Subscription Cancellation</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body">
                                                Are you sure you want to cancel your subscription? This action cannot be undone, and you will lose access to premium features.
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                <form id="cancelSubscriptionForm" action="{{ route('payments.cancel') }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
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
                                                        <p class="mb-2">{{ $user->subscription_starts_at->format('F j, Y') }}</p>
                                                    @endif
                                                    @if ($calculatedEndDate)
                                                        <p class="text-muted mb-2"><strong>End Date:</strong></p>
                                                        <p class="mb-2 {{ $isExpired ? 'text-danger' : '' }}">
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
                                                        <input type="text" class="form-control" value="{{ $user->license_key }}" readonly id="licenseKey">
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('licenseKey')">
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
                                                <a href="{{ route('subscriptions.index') }}" class="btn btn-light">
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
                                                <a href="{{ route('subscriptions.index') }}" class="btn btn-light">
                                                    <i class="fas fa-star"></i> View Premium Plans
                                                </a>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($user->orders->count() > 0)
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h3 class="card-title">Order History</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Package</th>
                                                            <th>Amount</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($user->orders->take(5) as $order)
                                                        <tr>
                                                            <td>{{ $order->created_at->format('M j, Y') }}</td>
                                                            <td>{{ $order->package->name }}</td>
                                                            <td>${{ number_format($order->amount, 2) }}</td>
                                                            <td>
                                                                <span class="badge {{ $order->status === 'completed' ? 'badge-success' : 'badge-warning' }}">
                                                                    {{ ucfirst($order->status) }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                                @if ($user->orders->count() > 5)
                                                    <p class="text-muted text-center mt-2">
                                                        Showing latest 5 orders. <a href="#">View all orders</a>
                                                    </p>
                                                @endif
                                            </div>
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
<script>
    document.getElementById('cancelSubscriptionForm').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Canceling...';

        fetch('{{ route('payments.cancel') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + document.querySelector('meta[name="api-token"]').content,
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Subscription Canceled', data.message, () => {
                    window.location.href = '{{ route('subscriptions.index') }}';
                });
            } else {
                showAlert('error', 'Cancellation Error', data.error || 'Failed to cancel subscription.');
                submitButton.disabled = false;
                submitButton.innerHTML = 'Confirm Cancellation';
            }
        })
        .catch(error => {
            console.error('Cancellation error:', error);
            showAlert('error', 'Cancellation Error', 'Failed to cancel subscription.');
            submitButton.disabled = false;
            submitButton.innerHTML = 'Confirm Cancellation';
        });
    });

    function showAlert(type, title, message, callback) {
        // Assuming you have a showAlert function (e.g., using SweetAlert or Bootstrap alert)
        alert(`${title}: ${message}`); // Replace with your actual alert implementation
        if (callback) callback();
    }
</script>
