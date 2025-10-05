@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper ">


    <section class="content pt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card card-primary shadow-sm">
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
                    <form method="POST" action="{{ route('admin.profile.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="card-body">
                            <div class="form-group mb-3">
                                <label for="inputName" class="form-label fw-bold">Email Address</label>
                                <input id="email" type="email" class="form-control form-control-lg" name="email"
                                    value="{{ $user->email }}" disabled>
                                <small class="form-text text-muted">Email cannot be changed</small>
                            </div>
                            <div class="form-group mb-3">
                                <label for="inputClientCompany" class="form-label fw-bold">Full Name</label>
                                <input type="text" id="name"
                                    class="form-control form-control-lg @error('name') is-invalid @enderror" name="name"
                                    value="{{ old('name', $user->name) }}" autocomplete="name" autofocus placeholder="Enter your full name">
                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="form-group mb-3">
                                <label for="inputProjectLeader" class="form-label fw-bold">New Password</label>
                                <input id="password" type="password"
                                    class="form-control form-control-lg @error('password') is-invalid @enderror" name="password"
                                    autocomplete="new-password" placeholder="Enter new password (leave blank to keep current)">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                            <div class="form-group mb-3">
                                <label for="password_confirmation" class="form-label fw-bold">Confirm New Password</label>
                                <input id="password_confirmation" type="password"
                                    class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror"
                                    name="password_confirmation" autocomplete="new-password" placeholder="Confirm new password">
                                @error('password_confirmation')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary btn-lg px-4">
                                    <i class="fas fa-save mr-2"></i>{{ __('Update Profile') }}
                                </button>
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
@include('dashboard.includes.footer')

<!-- Control Sidebar -->

<!-- /.control-sidebar -->
</div>

<script>
$(document).ready(function() {
    const passwordField = $('#password');
    const confirmPasswordField = $('#password_confirmation');
    const submitButton = $('button[type="submit"]');

    function validatePasswordConfirmation() {
        const password = passwordField.val();
        const confirmPassword = confirmPasswordField.val();

        if (password && confirmPassword && password !== confirmPassword) {
            confirmPasswordField.addClass('is-invalid');
            confirmPasswordField.siblings('.invalid-feedback').remove();
            confirmPasswordField.after('<span class="invalid-feedback" role="alert"><strong>Password confirmation does not match.</strong></span>');
            submitButton.prop('disabled', true);
        } else {
            confirmPasswordField.removeClass('is-invalid');
            confirmPasswordField.siblings('.invalid-feedback').remove();
            submitButton.prop('disabled', false);
        }
    }

    // Validate on input
    passwordField.on('input', validatePasswordConfirmation);
    confirmPasswordField.on('input', validatePasswordConfirmation);

    // Clear validation when password field is empty
    passwordField.on('input', function() {
        if (!$(this).val()) {
            confirmPasswordField.removeClass('is-invalid');
            confirmPasswordField.siblings('.invalid-feedback').remove();
            submitButton.prop('disabled', false);
        }
    });
});
</script>

</body>

</html>
