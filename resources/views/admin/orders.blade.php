@include('dashboard.includes/header')
@include('dashboard.includes/sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
{{--          <h1>Subscriptions</h1>--}}
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
              <h3 class="card-title">Subscriptions</h3>
            </div>
            <!-- /.card-header -->
            <div class="card-body">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>Order ID</th>
                    <th>Name</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    @foreach($orders as $order)
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $order->user->name ?? 'Unknown' }}</td>
                    <td>{{$order->package}}</td>
                    <td>{{ number_format($order->amount, 0) }}</td>
                    <td>{{ $order->created_at->format('d F Y') }}</td>
                  </tr>
                  @endforeach

                  </tfoot>
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
@include('dashboard.includes/footer')

<!-- Control Sidebar -->

<!-- /.control-sidebar -->
</div>
<x-datatable
    tableId="example1"
    :lengthChange="true"
    :language="json_encode([
        'lengthMenu' => 'Show _MENU_ subscriptions per page',
        'zeroRecords' => 'No subscriptions found',
        'info' => 'Showing _START_ to _END_ of _TOTAL_ subscriptions',
        'infoEmpty' => 'Showing 0 to 0 of 0 subscriptions',
        'infoFiltered' => '(filtered from _MAX_ total subscriptions)',
        'search' => 'Search subscriptions:',
        'paginate' => [
            'first' => 'First',
            'last' => 'Last',
            'next' => 'Next',
            'previous' => 'Previous'
        ]
    ])"
/>
</body>

</html>
