@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper ">
    <section class="content">
        <div class="row justify-content-center mt-2 ">
            <div class="col-md-6" style="margin-top: 50px;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Update Sub Admin Record</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.sub-admins.update', $subadmin->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="card-body">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" value="{{ $subadmin->email }}" readonly>
                            </div>

                            <!-- Name with validation -->
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $subadmin->name) }}" required>
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <!-- Status with validation -->
                            <div class="form-group">
                                <label>Status</label>
                                <select name="status" class="custom-select @error('status') is-invalid @enderror">
                                    <option value="1"
                                        {{ old('status', $subadmin->status) == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0"
                                        {{ old('status', $subadmin->status) == 0 ? 'selected' : '' }}>Deactive</option>
                                </select>
                                @error('status')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                        </div>

                        <div class="card-footer text-right">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Update
                            </button>
                        </div>
                    </form>

                    <!-- /.card -->
                </div>
            </div>
    </section>
</div>
<!-- /.content-wrapper -->
@include('dashboard.includes.footer')

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
