@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>User Logs ({{ $logs->count() }} total)</h1>
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
                            <h3 class="card-title">Users Logs</h3>
                        </div>
                        <!-- /.card-header -->
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped dataTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Activity</th>
                                        <th>IP Address</th>
                                        <th>User Agent</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $log->user->name ?? 'Unknown' }}</td>
                                        <td>{{ $log->activity }}</td>
                                        <td>{{ $log->ip_address }}</td>
                                        <td>{{ Str::limit($log->user_agent, 50) }}</td>
                                        <td>{{ $log->created_at->setTimezone('Asia/Karachi')->format('j F Y, H:i') }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No logs found</td>
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

<script>
$(document).ready(function() {
    console.log('Initializing DataTable directly...');

    try {
        $('#example1').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50],
            "order": [[0, "desc"]],
            "searching": true,
            "ordering": true,
            "info": true,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"],
            "dom": 'Bfrtip',
            "language": {
                "lengthMenu": "Show _MENU_ logs per page",
                "zeroRecords": "No logs found",
                "info": "Showing _START_ to _END_ of _TOTAL_ logs",
                "infoEmpty": "Showing 0 to 0 of 0 logs",
                "infoFiltered": "(filtered from _MAX_ total logs)",
                "search": "Search logs:",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Previous"
                }
            }
        }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');

        console.log('DataTable initialized successfully');
    } catch (error) {
        console.error('DataTable initialization failed:', error);
    }
});

// Debug script to check DataTable initialization
$(document).ready(function() {
    console.log('Document ready');
    console.log('jQuery version:', $.fn.jquery);
    console.log('DataTable plugin available:', typeof $.fn.DataTable !== 'undefined');
    console.log('Table element exists:', $('#example1').length);
    console.log('Table rows:', $('#example1 tbody tr').length);

    // Check if DataTable is already initialized
    if ($.fn.DataTable.isDataTable('#example1')) {
        console.log('DataTable is already initialized');
    } else {
        console.log('DataTable is not initialized yet');
    }
});
</script>

<!-- Control Sidebar -->
<!-- /.control-sidebar -->
</div>
</body>

</html>
