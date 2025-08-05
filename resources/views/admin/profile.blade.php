@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

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
                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
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
                            <div class="form-group">
                                <label for="password_confirmation">Confirm Password</label>
                                <input id="password_confirmation" type="password"
                                    class="form-control @error('password_confirmation') is-invalid @enderror"
                                    name="password_confirmation" autocomplete="new-password">
                                @error('password_confirmation')
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
