@include('admin.includes.header')
@include('admin.includes.sidebar')

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
                            <table id="example1" class="table table-bordered table-striped">
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
                                                <form action="{{ route('payment-gateways.toggleStatus') }}"
                                                    method="POST" onsubmit="return confirm('Set this as active?')">
                                                    @csrf
                                                    <input type="hidden" name="id" value="{{ $gateway->id }}">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" name="status"
                                                            {{ $gateway->status == 1 ? 'checked' : '' }}
                                                            onchange="this.form.submit()">
                                                        <label class="form-check-label">
                                                            {{ $gateway->status == 1 ? 'Active' : 'Inactive' }}
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

@include('admin.includes.footer')

<!-- DataTable Scripts -->
<script>
    $(function() {
        $("#example1").DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"],
            "order": []
        }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
    });

    // Toggle Status
    $('.toggle-status').on('change', function() {
        let gatewayId = $(this).data('id');

        $.ajax({
            url: '{{ route('payment-gateways.toggleStatus') }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                id: gatewayId
            },
            success: function(res) {
                if (res.success) {
                    $('.toggle-status').not('[data-id="' + gatewayId + '"]').prop('checked', false);
                    $('.form-check-label').each(function() {
                        $(this).text($(this).siblings('.toggle-status').is(':checked') ?
                            'Active' : 'Inactive');
                    });
                } else {
                    alert('Failed to update status');
                }
            },
            error: function() {
                alert('Error occurred');
            }
        });
    });
</script>

</body>

</html>
