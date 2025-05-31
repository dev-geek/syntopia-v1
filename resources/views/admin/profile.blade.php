@include('admin.includes.header')
@include('admin.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper ">


    <section class="content">
        <div class="row justify-content-center mt-2 ">
            <div class="col-md-6" style="margin-top: 50px;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Edit Profile</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    {{-- Component --}}
                    @include('components.alert-messages')
                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf

                        <div class="card-body">
                            <div class="form-group">
                                <label for="inputName">Email</label>
                                <input id="email" type="email" class="form-control" name="email"
                                    value="{{ $user->email }}" disabled>
                            </div>
                            <div class="form-group">
                                <label for="inputClientCompany">Name</label>
                                <input type="text" id="name" type="text"
                                    class="form-control @error('name') is-invalid @enderror" name="name"
                                    value="{{ old('name', $user->name) }}" autocomplete="name" autofocus>
                            </div>
                            <div class="form-group">
                                <label for="inputProjectLeader">Password</label>
                                <input id="password" type="password"
                                    class="form-control @error('password') is-invalid @enderror" name="password"
                                    autocomplete="new-password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
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
                        <!-- /.card-body -->
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
