@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">
          <div class="overview-wrapper">
            <div class="container">
              <div class="row justify-content-center">
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-header">
                      <h3 class="card-title">Orders Details</h3>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive">
                        <table class="table table-bordered">
                          <thead>
                            <tr>
                              <th>Sr No</th>
                              <th>Package</th>
                              <th>Amount</th>
                              <th>Payment Status</th>
                              <th>Start Date</th>
                              <th>Renewal Date</th>
                              <th>Status</th>
                            </tr>
                          </thead>
                          <tbody>
                            @forelse ($orders as $index => $order)
                              @php
                                $startDate = \Carbon\Carbon::parse($order['created_at']);
                                $renewalDate = $startDate->copy()->addMonth();
                                $isActive = $renewalDate->isFuture();
                              @endphp
                              <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ ucfirst($order['package']['name']) }}</td>
                                <td>${{ number_format($order['amount'], 2) }}</td>
                                <td>
                                  @if($order->status === 'completed')
                                    <span class="badge badge-success">Completed</span>
                                  @elseif($order->status === 'pending')
                                    <span class="badge badge-warning">Pending</span>
                                  @else
                                    <span class="badge badge-secondary">{{ $order->status }}</span>
                                  @endif
                                </td>
                                <td>{{ $startDate->format('M d, Y') }}</td>
                                <td>{{ $renewalDate->format('M d, Y') }}</td>
                                <td>
                                  @if($isActive)
                                    <span class="badge badge-success">Active</span>
                                  @else
                                    <span class="badge badge-danger">Expired</span>
                                  @endif
                                </td>
                              </tr>
                            @empty
                              <tr>
                                <td colspan="8" class="text-center">No subscriptions found.</td>
                              </tr>
                            @endforelse
                          </tbody>
                        </table>
                      </div>
                      {{ $orders->links() }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
    </div>
    <!-- /.container-fluid -->
  </section>
  <!-- /.content -->
</div>

<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
  <!-- Control sidebar content goes here -->
</aside>
<!-- /.control-sidebar -->

@include('dashboard.includes.footer')
