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
                                @php
                                    // Check if user has an active subscription and package
                                    $hasActiveSubscription = $user->package && $user->subscription_starts_at && $user->is_subscribed;
                                    $currentPackagePrice = optional($user->package)->price ?? 0;
                                    
                                    // Check for available upgrades (packages with higher price)
                                    $hasUpgrades = false;
                                    $hasDowngrades = false;
                                    
                                    if ($hasActiveSubscription) {
                                        $hasUpgrades = \App\Models\Package::where('price', '>', $currentPackagePrice)->exists();
                                        $hasDowngrades = \App\Models\Package::where('price', '<', $currentPackagePrice)->exists();
                                    }
                                @endphp

                                @if($hasActiveSubscription)
                                    @if($hasDowngrades)
                                        <a href="{{ route('subscription.downgrade') }}" class="btn btn-warning mr-2">
                                            <i class="fas fa-arrow-down"></i> Downgrade Plan
                                        </a>
                                    @endif
                                    
                                    @if($hasUpgrades)
                                        <a href="{{ route('subscription.upgrade') }}" class="btn btn-primary mr-2">
                                            <i class="fas fa-arrow-up"></i> Upgrade Plan
                                        </a>
                                    @endif
                                    
                                    <button class="btn btn-danger cancel-subscription-btn" data-csrf-token="{{ csrf_token() }}">
                                        <i class="fas fa-times"></i> Cancel Subscription
                                    </button>
                                    
                                    @if(!$hasUpgrades && !$hasDowngrades)
                                        <span class="badge badge-info px-3 py-2">
                                            <i class="fas fa-crown"></i> Only Plan Available
                                        </span>
                                    @endif
                                @else
                                    <a href="{{ route('subscriptions.index') }}" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Get Subscription
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>
                            @endif

                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>
                            @endif

                            @if(session('warning'))
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </div>
                            @endif

                            <div class="row">
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
                                                        @if($user->package)
                                                            <span class="badge badge-secondary ml-2">
                                                                ${{ number_format($user->package->price, 0) }}/{{ $user->package->duration }}
                                                            </span>
                                                        @endif
                                                    </p>
                                                    <p class="text-muted mb-2"><strong>Status:</strong></p>
                                                    <span class="badge {{ $user->is_subscribed ? 'badge-success' : 'badge-warning' }} px-3 py-2">
                                                        {{ $user->is_subscribed ? 'Active' : 'In Active' }}
                                                    </span>
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
                                                        <p class="text-muted mb-2"><strong>Calculated End Date:</strong></p>
                                                        <p class="mb-2">{{ $calculatedEndDate->format('F j, Y') }}</p>
                                                        
                                                        @php
                                                            $daysRemaining = now()->diffInDays($calculatedEndDate, false);
                                                        @endphp
                                                        
                                                        @if($daysRemaining > 0)
                                                            <p class="text-muted mb-2"><strong>Days Remaining:</strong></p>
                                                            <span class="badge {{ $daysRemaining > 30 ? 'badge-success' : ($daysRemaining > 7 ? 'badge-warning' : 'badge-danger') }} px-3 py-2">
                                                                {{ $daysRemaining }} days
                                                            </span>
                                                        @elseif($daysRemaining < 0)
                                                            <span class="badge badge-danger px-3 py-2">
                                                                Expired {{ abs($daysRemaining) }} days ago
                                                            </span>
                                                        @else
                                                            <span class="badge badge-warning px-3 py-2">
                                                                Expires today
                                                            </span>
                                                        @endif
                                                    @else
                                                        <p class="text-muted mb-2"><strong>Calculated End Date:</strong></p>
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
                                                            <button class="btn btn-outline-secondary" type="button" onclick="copyLicenseKey()">
                                                                <i class="fas fa-copy"></i> Copy
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Plan Change Options -->
                                    @if($hasActiveSubscription && ($hasUpgrades || $hasDowngrades))
                                    <div class="card bg-light mb-3">
                                        <div class="card-header">
                                            <h3 class="card-title">Plan Management</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-12">
                                                    @if($hasUpgrades)
                                                        <div class="mb-3">
                                                            <h6 class="text-success"><i class="fas fa-arrow-up"></i> Upgrade Available</h6>
                                                            <p class="text-muted small mb-2">Get access to higher-tier features and benefits.</p>
                                                            <a href="{{ route('subscription.upgrade') }}" class="btn btn-success btn-sm">
                                                                View Upgrade Options
                                                            </a>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($hasDowngrades)
                                                        <div class="mb-3">
                                                            <h6 class="text-warning"><i class="fas fa-arrow-down"></i> Downgrade Available</h6>
                                                            <p class="text-muted small mb-2">Switch to a lower-cost plan. Changes typically take effect at the end of your current billing cycle.</p>
                                                            <a href="{{ route('subscription.downgrade') }}" class="btn btn-warning btn-sm">
                                                                View Downgrade Options
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    @if ($user->orders->count() > 0)
                                    <div class="card bg-light">
                                        <div class="card-header">
                                            <h3 class="card-title">Recent Order History</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-hover table-sm">
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
                                                            <td>{{ $order->package->name ?? 'N/A' }}</td>
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
                                            </div>
                                            @if($user->orders->count() > 5)
                                                <div class="text-center mt-2">
                                                    <small class="text-muted">Showing 5 most recent orders</small>
                                                </div>
                                            @endif
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

<script>
function copyLicenseKey() {
    const licenseKeyInput = document.getElementById('licenseKey');
    licenseKeyInput.select();
    licenseKeyInput.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        // Show success message
        const button = event.target.closest('button');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
        button.classList.remove('btn-outline-secondary');
        button.classList.add('btn-success');
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    } catch (err) {
        console.error('Failed to copy license key: ', err);
        // Use SweetAlert2 for error if available, otherwise native alert
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Copy Failed',
                text: 'Failed to copy license key. Please try again.',
                confirmButtonText: 'OK'
            });
        } else {
            alert('Failed to copy license key. Please try again.');
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const cancelButton = document.querySelector('.cancel-subscription-btn');
    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            // Check if SweetAlert2 is loaded
            if (typeof Swal === 'undefined') {
                // Fallback to native confirm dialog
                if (confirm('Are you sure you want to cancel your subscription? It will be canceled at the end of the current billing period, and you will retain access until then.')) {
                    fetch('/api/payments/cancel-subscription', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.getAttribute('data-csrf-token'),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => {
                                throw new Error(err.message || err.error || `HTTP ${response.status}: ${response.statusText}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || data.error || 'Cancellation failed');
                        }
                        alert(data.message || 'Your subscription has been canceled successfully.');
                        window.location.href = '/user/dashboard';
                    })
                    .catch(error => {
                        console.error('Cancellation error:', error);
                        alert(error.message || 'Failed to cancel subscription. Please try again or contact support.');
                    });
                }
                return;
            }

            // Use SweetAlert2 for confirmation
            Swal.fire({
                title: 'Are you sure you want to cancel your subscription?',
                text: 'It will be canceled at the end of the current billing period, and you will retain access until then.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, cancel it!',
                cancelButtonText: 'No, keep it'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('/api/payments/cancel-subscription', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.getAttribute('data-csrf-token'),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => {
                                throw new Error(err.message || err.error || `HTTP ${response.status}: ${response.statusText}`);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            throw new Error(data.message || data.error || 'Cancellation failed');
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Subscription Canceled',
                            text: data.message || 'Your subscription has been canceled successfully.',
                            confirmButtonText: 'Go to Dashboard'
                        }).then(() => {
                            window.location.href = '/user/dashboard';
                        });
                    })
                    .catch(error => {
                        console.error('Cancellation error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Cancellation Failed',
                            text: error.message || 'Failed to cancel subscription. Please try again or contact support.',
                            confirmButtonText: 'OK'
                        });
                    });
                }
            });
        });
    }
});
</script>

@include('dashboard.includes.footer')