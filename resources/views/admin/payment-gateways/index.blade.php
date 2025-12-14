@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    {{-- <h1>Users</h1> --}}
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
                            <h3 class="card-title">Payment Gateways</h3>
                        </div>

                        {{-- Component --}}
                        @include('components.alert-messages')

                        <div class="card-body">
                            <table id="payment-gateways-table" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($payment_gateways as $gateway)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $gateway->name }}</td>
                                            <td>
                                                <form action="{{ route('admin.payment-gateways.toggleStatus') }}"
                                                    method="POST" onsubmit="return confirm('Set this as active?')">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $gateway->id }}">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="is_active"
                                                            {{ $gateway->is_active == 1 ? 'checked' : '' }}
                                                            onchange="this.form.submit()">
                                                        <label class="form-check-label">
                                                            {{ $gateway->is_active == 1 ? 'Active' : 'In Active' }}
                                                        </label>
                                                    </div>
                                                </form>
                                            </td>
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

@include('dashboard.includes.footer')

<script>
$(function() {
    console.log('Initializing DataTable...');

    $("#payment-gateways-table").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"],
        "pageLength": 10,
        "lengthMenu": [5, 10, 25, 50],
        "order": [[0, "asc"]],
        "language": {
            "lengthMenu": "Show _MENU_ payment gateways per page",
            "zeroRecords": "No payment gateways found",
            "info": "Showing _START_ to _END_ of _TOTAL_ payment gateways",
            "infoEmpty": "Showing 0 to 0 of 0 payment gateways",
            "infoFiltered": "(filtered from _MAX_ total payment gateways)",
            "search": "Search payment gateways:",
            "paginate": {
                "first": "First",
                "last": "Last",
                "next": "Next",
                "previous": "Previous"
            }
        }
    }).buttons().container().appendTo('#payment-gateways-table_wrapper .col-md-6:eq(0)');

    console.log('DataTable initialized successfully');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>

</html>
