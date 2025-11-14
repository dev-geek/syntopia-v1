@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper ">
    <section class="content">
        <div class="row justify-content-center mt-2 ">
            <div class="col-md-6" style="margin-top: 50px;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Create a User</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.store.user') }}">
                        @csrf

                        <div class="card-body">
                            <div class="form-group">
                                <label for="inputClientCompany">Name</label>
                                <input type="text" id="name"
                                    class="form-control @error('name') is-invalid @enderror" name="name"
                                    value="{{ old('name') }}" autocomplete="name" autofocus>
                            </div>
                            <div class="form-group">
                                <label for="inputName">Email</label>
                                <input id="email" type="email" class="form-control" name="email"
                                    value="{{ old('email') }}">
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status" style="width: 100%;">
                                    <option value="1">Active</option>
                                    <option value="0">Deactive</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <x-password-toggle id="password" name="password" />
                            </div>

                            <div class="form-group">
                                <label for="password_confirmation">Confirm Password</label>
                                <x-password-toggle id="password_confirmation" name="password_confirmation" />
                            </div>
                        </div>
                        <div class="row mb-0">
                            <div class="col-md-2 ml-4">
                                <div class="d-grid gap-3" style="padding-bottom: 20px;">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        {{ __('Save') }}
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

    // Password toggle functionality is now handled by the dedicated password-toggle.js script

    // Show spinner on form submission
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form[action="{{ route('admin.store.user') }}"]');
        if (form) {
            form.addEventListener('submit', function(e) {
                // Show spinner when submitting form
                if (window.SpinnerUtils) {
                    window.SpinnerUtils.show('Creating user...');
                } else if (document.getElementById('spinnerOverlay')) {
                    const spinner = document.getElementById('spinnerOverlay');
                    spinner.classList.add('active');
                    const spinnerText = document.getElementById('spinnerText');
                    if (spinnerText) spinnerText.textContent = 'Creating user...';
                } else if (document.getElementById('globalSpinner')) {
                    document.getElementById('globalSpinner').style.display = 'flex';
                }
            });
        }

        // Show spinner on link clicks (e.g., back to users list)
        document.querySelectorAll('a[href]').forEach(link => {
            if (!link.hasAttribute('data-no-spinner')) {
                link.addEventListener('click', function(e) {
                    const href = this.getAttribute('href');
                    const target = this.getAttribute('target');

                    // Only show spinner for internal links
                    if (href && href.startsWith('/') && (!target || target === '_self')) {
                        if (window.SpinnerUtils) {
                            window.SpinnerUtils.show('Loading...');
                        } else if (document.getElementById('spinnerOverlay')) {
                            const spinner = document.getElementById('spinnerOverlay');
                            spinner.classList.add('active');
                        } else if (document.getElementById('globalSpinner')) {
                            document.getElementById('globalSpinner').style.display = 'flex';
                        }
                    }
                });
            }
        });
    });
</script>
