@include('admin.includes.header')
@include('admin.includes.sidebar')
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
{{--                    <h1>Users</h1>--}}
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
                        @if (session('success'))
                    <div class="alert alert-success mt-3">
                        {{ session('success') }}
                    </div>
                    @endif
                        <!-- /.card-header -->
                        <div class="card-body">
                            <a href="{{ route('subadmins.create') }}" class="btn btn-success mb-3 float-right"><i class="fa fa-plus mx-2"></i> Add</a>

                            <table id="example1" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        @if(Auth::check() && Auth::user()->role == 1)
                                        <th>
                                            Action
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        @foreach($users as $user)
                                        <td>{{$user->name}}</td>
                                        <td>{{$user->email}} </td>
                                        @if($user->role == 1)
                                        <td>Admin</td>
                                        @elseif($user->role == 2)
                                        <td>Editor</td>
                                        @else
                                        <td>Subscriber</td>
                                        @endif
                                        @if($user->status == 1)
                                        <td>Active</td>
                                        @else
                                        <td>Deactive</td>
                                        @endif
                                        @if(Auth::check() && Auth::user()->role == 1)
                                        <td>
                                            <a href="{{ route('manage.admin.profile', $user->id) }}"><i
                                                    class="bi bi-pencil-square"></i></a>
                                            @endif
                                        </td>
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
