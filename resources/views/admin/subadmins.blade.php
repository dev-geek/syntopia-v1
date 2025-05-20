@include('admin.includes.header')
@include('admin.includes.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    {{--                    <h1>Users</h1> --}}
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
                            <h3 class="card-title">Sub Admins</h3>
                        </div>

                        {{-- Component --}}
                        @include('components.alert-messages')
                        <!-- /.card-header -->
                        <div class="card-body">
                            <a href="{{ route('sub-admins.create') }}" class="btn btn-success mb-3"><i
                                    class="fa fa-plus mx-2"></i> Add</a>

                            <table id="example1" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        @if (Auth::check() && Auth::user()->role == 1)
                                            <th>
                                                Action
                                        @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        @foreach ($users as $user)
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }} </td>
                                            @if ($user->role == 1)
                                                <td>Admin</td>
                                            @elseif($user->role == 2)
                                                <td>Editor</td>
                                            @else
                                                <td>Subscriber</td>
                                            @endif
                                            @if ($user->status == 1)
                                                <td>Active</td>
                                            @else
                                                <td>Deactive</td>
                                            @endif
                                            @if (Auth::check() && Auth::user()->role == 1)
                                                <td class="d-flex align-items-center gap-2">
                                                    <!-- Edit Button -->
                                                    <a href="{{ route('sub-admins.edit', $user->id) }}"
                                                        class="btn btn-sm btn-primary mx-2" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                    <!-- Delete Button -->
<button type="button"
    class="btn btn-sm btn-danger"
    onclick="confirmDelete({{ $user->id }}, '{{ route('sub-admins.destroy', $user->id) }}')"
    title="Delete">
    <i class="fas fa-trash-alt"></i>
</button>

                                                </td>
                                            @endif

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
@include('admin.includes.footer')
<!-- Control Sidebar -->
<!-- /.control-sidebar -->
</div>
<script>
    $(function() {
        $("#example1").DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"],
            "order": [],
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet" />

<script>
    function confirmDelete(id, deleteUrl) {
        toastr.clear(); // Remove existing toasts

        toastr.warning(
            `<div class="text-center">
                <p>Are you sure you want to delete this record?</p>
                <button class="btn btn-sm btn-danger mt-2" onclick="deleteRecord('${deleteUrl}')">Yes</button>
                <button class="btn btn-sm btn-secondary mt-2 ml-2" onclick="toastr.clear()">No</button>
            </div>`,
            'Confirm Deletion',
            {
                timeOut: 0, // Keeps the toast open until action
                closeButton: true,
                tapToDismiss: false,
                allowHtml: true,
                positionClass: 'toast-top-center' // Centers the toast
            }
        );
    }

    function deleteRecord(url) {
        const form = document.createElement('form');
        form.action = url;
        form.method = 'POST';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';

        const method = document.createElement('input');
        method.type = 'hidden';
        method.name = '_method';
        method.value = 'DELETE';

        form.appendChild(csrf);
        form.appendChild(method);
        document.body.appendChild(form);
        form.submit();
    }
</script>

</body>

</html>
