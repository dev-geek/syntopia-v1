@include('admin.includes.header')
@include('admin.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper ">


    <section class="content">
        <div class="row justify-content-center mt-2 ">
            <div class="col-md-6" style="margin-top: 50px;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Manage Sub Admin</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    @if (session('success'))
                    <div class="alert alert-success mt-3">
                        {{ session('success') }}
                    </div>
                    @endif
                    <form method="POST" action="{{ route('manage-admin-profile.update', $user->id) }}">
    @csrf

    <div class="card-body">
        <div class="form-group">
            <label for="inputName">Email</label>
            <input id="email" type="email" class="form-control" name="email"
                value="{{ $user->email }}" disabled>
        </div>
        <div class="form-group">
            <label for="inputClientCompany">Name</label>
            <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" name="name" disabled
                value="{{ old('name', $user->name) }}" autocomplete="name" autofocus>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select class="custom-select" name="status">
                <option value="1" {{ $user->status == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ $user->status == 0 ? 'selected' : '' }}>Deactive</option>
            </select>
        </div>
        <div class="form-group">
            <label for="inputProjectLeader">Password</label>
            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password"
                autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="inputProjectLeader">Confirm Password</label>
            <input id="password_confirmation" type="password" class="form-control" name="password_confirmation"
                autocomplete="new-password">
        </div>
    </div>
    <div class="row mb-0">
        <div class="col-md-2 ml-4">
            <div class="d-grid gap-3" style="padding-bottom: 20px;">
                <button type="submit" class="btn btn-primary btn-block">
                    {{ __('Update') }}
                </button>
            </div>
        </div>
    </div>
</form>

                <!-- /.card -->
            </div>
        </div>
    </section>
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