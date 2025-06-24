
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
                            <a href="{{ route('subscription.upgrade') }}" class="btn btn-primary float-right">Upgrade Subscription</a>
                        </div>
                        <div class="card-body">
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
                                                    <p class="mb-2">{{ $currentPackage }}</p>
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
                                                    <p class="mb-2">{{ $user->license_key }}</p>
                                                </div>
                                            </div>
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
                                                        @foreach($user->orders as $order)
                                                        <tr>
                                                            <td>{{ $order->created_at->format('F j, Y') }}</td>
                                                            <td>{{ $order->package->name }}</td>
                                                            <td>${{ number_format($order->amount, 2) }}</td>
                                                            <td>
                                                                <span class="badge {{ $order->status === 'completed' ? 'bg-success' : 'bg-warning' }}">
                                                                    {{ ucfirst($order->status) }}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
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
