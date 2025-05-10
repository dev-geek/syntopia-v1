@include('subscription.includes.header')

@include('subscription.includes.nav')


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
 

  <!-- Main content -->
  <section class="content">
    <div class="container-fluid">
      <div class="row">
        <div class="col-12">

        <!-- Display success message if any -->
        
            
       
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
                                                <th>Order ID</th>
                                                <th>Package</th>
                                                <th>Amount</th>
                                                <th>Payment Status</th>
                                                <th>Start Date</th>
                                                <th>Renewal Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($orders as $order)
                                                @php
                                                    $startDate = $order->created_at;
                                                    $renewalDate = $startDate->copy()->addMonth();
                                                    $isActive = $renewalDate->isFuture();
                                                @endphp
                                                <tr>
                                                    <td>{{ $order->id }}</td>
                                                    <td>{{ $order->package }}</td>
                                                    <td>${{ number_format($order->amount, 2) }}</td>
                                                    <td>
                                                        @if($order->payment === 'Yes')
                                                            <span class="badge badge-success">Paid</span>
                                                        @elseif($order->payment === null)
                                                            <span class="badge badge-warning">Pending</span>
                                                        @else
                                                            <span class="badge badge-secondary">{{ $order->payment }}</span>
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
                                                    <td colspan="7" class="text-center">No subscriptions found.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
  


                   
             
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
@include('subscription.includes.footer')

<!-- Control Sidebar -->

<!-- /.control-sidebar -->
</div>
 
</body>

</html>