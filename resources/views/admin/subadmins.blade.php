@include('dashboard.includes/header')
@include('dashboard.includes/sidebar')
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
                            <h3 class="card-title">Sub Admins</h3>
                        </div>

                        {{-- Component --}}
                        @include('components.alert-messages')
                        <!-- /.card-header -->
                        <div class="card-body">
                            <a href="{{ route('admin.sub-admins.create') }}" class="btn btn-success mb-3"><i
                                    class="fa fa-plus mx-2"></i> Add</a>

                            <table id="example1" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        @if (Auth::check() && Auth::user()->hasAnyRole(['Super Admin', 'Sub Admin']))
                                            <th>Action</th>
                                        @endif

                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($users as $user)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }} </td>
                                        <td>
                                            Sub Admin
                                        </td>

                                        @if ($user->status == 1)
                                            <td>Active</td>
                                        @else
                                            <td>Deactive</td>
                                        @endif
                                        @if (Auth::check() && Auth::user()->hasAnyRole(['Super Admin', 'Sub Admin']))
                                            <td class="d-flex align-items-center gap-2">
                                                <!-- Edit Button -->
                                                <a href="{{ route('admin.sub-admins.edit', $user->id) }}"
                                                    class="btn btn-sm btn-primary mx-2" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <!-- Delete Button -->
                                                <button type="button" class="btn btn-sm btn-danger"
                                                    onclick="confirmDelete({{ $user->id }}, '{{ route('admin.sub-admins.destroy', $user->id) }}')"
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
@include('dashboard.includes/footer')

<x-datatable
    tableId="example1"
    :language="json_encode([
        'lengthMenu' => 'Show _MENU_ sub-admins per page',
        'zeroRecords' => 'No sub-admins found',
        'info' => 'Showing _START_ to _END_ of _TOTAL_ sub-admins',
        'infoEmpty' => 'Showing 0 to 0 of 0 sub-admins',
        'infoFiltered' => '(filtered from _MAX_ total sub-admins)',
        'search' => 'Search sub-admins:',
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
    function confirmDelete(id, deleteUrl) {
        SwalUtils.showDeleteConfirm('This action cannot be undone!')
            .then((result) => {
                if (result.isConfirmed) {
                    deleteRecord(deleteUrl);
                }
            });
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
