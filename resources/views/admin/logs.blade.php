@include('dashboard.includes/header')
@include('dashboard.includes/sidebar')
<style>
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 0.5rem !important;
        margin: 0 2px;
        padding: 0.3rem 0.8rem;
        background: #f8f9fa;
        color: #0d6efd !important;
        border: none !important;
        font-weight: 600;
        transition: background 0.2s, color 0.2s;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: linear-gradient(90deg, #0d6efd 0%, #0dcaf0 100%) !important;
        color: #fff !important;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(13,110,253,0.10);
    }
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        padding: 0.3rem 0.7rem;
        background: #f8fafd;
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    .card .card-body {
        box-shadow: 0 8px 32px rgba(13,110,253,0.07);
        border-radius: 1.2rem;
        padding: 2rem 1.5rem 1.5rem 1.5rem;
    }
    table.dataTable {
        border-radius: 1rem !important;
        overflow: hidden !important;
        background: #fff;
        box-shadow: 0 4px 24px rgba(13,110,253,0.07);
    }
    table.dataTable thead th {
        background: linear-gradient(90deg, #e3eafc 0%, #f1f5fb 100%);
        color: #0d6efd;
        font-weight: 800;
        border-bottom: 2px solid #e3eafc;
        font-size: 1.08rem;
        letter-spacing: 0.5px;
        padding-top: 1rem;
        padding-bottom: 1rem;
    }
    table.dataTable tbody tr {
        transition: background 0.18s, transform 0.18s;
    }
    table.dataTable tbody tr:hover {
        background: #e9f3ff;
        transform: scale(1.012);
        box-shadow: 0 2px 8px rgba(13,110,253,0.07);
    }
    table.dataTable td, table.dataTable th {
        vertical-align: middle;
        padding: 0.85rem 0.75rem;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    {{-- <h1>Users</h1>--}}
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
                            <table id="example1" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Activity</th>
                                        <th>IP Address</th>
                                        <th>User Agent</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($logs as $log)
                                    <tr>
                                        <td>{{ $log->user->name ?? 'Unknown' }}</td>
                                        <td>{{ $log->activity }}</td>
                                        <td>{{ $log->ip_address }}</td>
                                        <td>{{ $log->user_agent }}</td>
                                        <td>{{ $log->created_at->setTimezone('Asia/Karachi')->format('j F Y, H:i') }}</td>

                                    </tr>
                                    @endforeach
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
@include('dashboard.includes/footer')
<!-- Control Sidebar -->
<!-- /.control-sidebar -->
</div>
<script>
    $(function() {
        $("#example1").DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
        $('#example2').DataTable({
            "paging": true,
            "lengthChange": false,
            "searching": false,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
        });
    });
</script>
</body>

</html>
