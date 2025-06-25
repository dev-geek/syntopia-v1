@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')
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
                            <h3 class="card-title">Users</h3>
                        </div>

                        {{-- Component --}}
                        @include('components.alert-messages')
                        <!-- /.card-header -->
                        <div class="card-body">
                            @if (Auth::user()->hasRole('Super Admin'))
                                <a href="{{ route('add-users') }}" class="btn btn-success mb-3">
                                    <i class="fas fa-plus"></i> Add User
                                </a>
                            @endif
                            <table id="example1" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        @if (Auth::check() && Auth::user()->hasRole('Super Admin'))
                                            <th>
                                                Action
                                        @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        @foreach ($users as $user)
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }} </td>
                                            <td>
                                                @if ($user->hasRole('User'))
                                                    User
                                                @else
                                                    No Role
                                                @endif
                                            </td>

                                            @if ($user->status == 1)
                                                <td>Active</td>
                                            @else
                                                <td>Deactive</td>
                                            @endif
                                            @if (Auth::check() && Auth::user()->hasRole('Super Admin'))
                                                <td>
                                                    <a href="{{ route('manage.profile', $user->id) }}"
                                                        class="btn btn-sm btn-primary mx-2" title="Edit">
                                                        <i class="fas fa-edit"></i></a>
                                                    <form action="{{ route('admin.users.destroy', $user) }}" method="POST"
                                                        class="delete-form" style="display:inline-block;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm"
                                                            title="Delete User">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
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
@include('dashboard.includes.footer')
<!-- Control Sidebar -->
<!-- /.control-sidebar -->
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
<script>
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>

</body>

</html>
