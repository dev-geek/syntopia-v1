@include('dashboard.includes/header')
@include('dashboard.includes/sidebar')
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
                                <a href="{{ route('admin.add-users') }}" class="btn btn-success mb-3">
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
                                    @foreach ($users as $user)
                                    <tr>
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
                                                <a href="{{ route('admin.manage.profile', $user->id) }}"
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
@include('dashboard.includes/footer')

<x-datatable
    tableId="example1"
    :language="json_encode([
        'lengthMenu' => 'Show _MENU_ users per page',
        'zeroRecords' => 'No users found',
        'info' => 'Showing _START_ to _END_ of _TOTAL_ users',
        'infoEmpty' => 'Showing 0 to 0 of 0 users',
        'infoFiltered' => '(filtered from _MAX_ total users)',
        'search' => 'Search users:',
        'paginate' => [
            'first' => 'First',
            'last' => 'Last',
            'next' => 'Next',
            'previous' => 'Previous'
        ]
    ])"
/>
<!-- Control Sidebar -->
<!-- /.control-sidebar -->
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/swal-utils.js') }}"></script>

<script>
    document.querySelectorAll('.delete-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            SwalUtils.showDeleteConfirm('This action cannot be undone!')
                .then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
        });
    });
</script>

</body>

</html>
