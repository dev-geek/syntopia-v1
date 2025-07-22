@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">My Orders</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('user.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Orders</li>
                    </ol>
                </div>
            </div>
        </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Order History</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            @include('components.alert-messages')

                            <table id="ordersTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Package</th>
                                        <th>Amount</th>
                                        <th>Payment Status</th>
                                        <th>Order Status</th>
                                        <th>Created Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($orders as $order)
                                        <tr>
                                            <td>
                                                <strong>#{{ $loop->iteration }}</strong>
                                            </td>
                                            <td>
                                                @if($order->package)
                                                    <span class="badge badge-info">{{ $order->package->name }}</span>
                                                @else
                                                    <span class="text-muted">{{ $order->package ?? 'N/A' }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong>${{ number_format($order->amount, 2) }}</strong>
                                            </td>
                                            <td>
                                                @if($order->status === 'completed')
                                                    <span class="badge badge-success">Paid</span>
                                                @elseif($order->status === 'pending')
                                                    <span class="badge badge-warning">Pending</span>
                                                @elseif($order->status === 'failed')
                                                    <span class="badge badge-danger">Failed</span>
                                                @else
                                                    <span class="badge badge-secondary">{{ ucfirst($order->status ?? 'Unknown') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($order->status === 'completed')
                                                    <span class="badge badge-success">Completed</span>
                                                @elseif($order->status === 'pending')
                                                    <span class="badge badge-warning">Processing</span>
                                                @elseif($order->status === 'failed')
                                                    <span class="badge badge-danger">Failed</span>
                                                @else
                                                    <span class="badge badge-secondary">{{ ucfirst($order->status ?? 'Unknown') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <i class="fas fa-calendar-alt text-muted mr-1"></i>
                                                {{ $order->created_at->format('M d, Y H:i A') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                                    <p class="mb-0">No orders found.</p>
                                                    <small>Your order history will appear here once you make a purchase.</small>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <!-- /.card-body -->
                    </div>
                    <!-- /.card -->
                </div>
                <!-- /.col -->
            </div>
            <!-- /.row -->
        </div>
        <!-- /.container-fluid -->
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->

@include('dashboard.includes.footer')

<x-datatable
    tableId="ordersTable"
    :pageLength="10"
    :order="'[[5, \"desc\"]]'"
    :language="json_encode([
        'lengthMenu' => 'Show _MENU_ orders per page',
        'zeroRecords' => 'No orders found',
        'info' => 'Showing _START_ to _END_ of _TOTAL_ orders',
        'infoEmpty' => 'Showing 0 to 0 of 0 orders',
        'infoFiltered' => '(filtered from _MAX_ total orders)',
        'search' => 'Search orders:',
        'paginate' => [
            'first' => 'First',
            'last' => 'Last',
            'next' => 'Next',
            'previous' => 'Previous'
        ]
    ])"
/>
