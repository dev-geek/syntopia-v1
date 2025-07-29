@include('dashboard.includes.header')
@include('dashboard.includes.sidebar')

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper ">
    <section class="content">
        <div class="row justify-content-center mt-2 ">
            <div class="col-md-6" style="margin-top: 50px;">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">My Profile</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>

                    @include('components.alert-messages')

                    <form method="POST" action="{{ route('profile.update') }}" id="profileForm">
                        @csrf

                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" autofocus placeholder="Enter your full name">
                                @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <small class="form-text text-muted">Your name must be at least 2 characters long.</small>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <input id="email" type="email" class="form-control" name="email" value="{{ $user->email }}" disabled>
                            </div>

                            @if($user->package)
                            <div class="form-group">
                                <label for="package">Package</label>
                                <input id="package" type="text" class="form-control" name="package" value="{{ $user->package->name }}" disabled>
                            </div>
                            @endif

                            @if($user->license_key)
                            <div class="form-group">
                                <label for="license_key">License Key</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" value="{{ $user->license_key }}" readonly id="licenseKey">
                                    <div class="input-group-append">
                                        <button class="btn btn-outline-secondary" type="button" data-copy="element" data-copy-element="licenseKey" data-toast="true" data-success-text="License key copied to clipboard!">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="new-password" placeholder="Leave blank to keep current password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <small class="form-text text-muted">Password must be 8-30 characters long and contain at least one number, uppercase letter, lowercase letter, and special character (,.<>{}~!@#$%^&_). Leave blank if you don't want to change it.</small>
                                <div class="password-strength mt-2" style="display: none;">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Password strength: <span class="strength-text">Weak</span></small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="password_confirmation">Confirm New Password</label>
                                <input id="password_confirmation" type="password" class="form-control @error('password_confirmation') is-invalid @enderror" name="password_confirmation" autocomplete="new-password" placeholder="Confirm your new password">
                                @error('password_confirmation')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save mr-2"></i>Update Profile
                            </button>
                            <button type="button" class="btn btn-secondary ml-2" onclick="resetForm()">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>

@include('dashboard.includes.footer')

<script>
    // Show SWAL for success message
    @if(session('success'))
        window.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: @json(session('success')),
                confirmButtonColor: '#3085d6',
            });
        });
    @endif

    // Password strength indicator
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password');
        const passwordStrength = document.querySelector('.password-strength');
        const progressBar = document.querySelector('.password-strength .progress-bar');
        const strengthText = document.querySelector('.password-strength .strength-text');

        if (passwordInput && passwordStrength) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;

                if (password.length === 0) {
                    passwordStrength.style.display = 'none';
                    return;
                }

                passwordStrength.style.display = 'block';

                let strength = 0;
                let feedback = '';

                // Length check
                if (password.length >= 8) strength += 25;
                if (password.length >= 12) strength += 10;

                // Character variety checks
                if (/[a-z]/.test(password)) strength += 15;
                if (/[A-Z]/.test(password)) strength += 15;
                if (/[0-9]/.test(password)) strength += 15;
                if (/[,.<>{}~!@#$%^&_]/.test(password)) strength += 20;

                // Determine strength level
                if (strength < 40) {
                    feedback = 'Weak';
                    progressBar.className = 'progress-bar bg-danger';
                } else if (strength < 70) {
                    feedback = 'Fair';
                    progressBar.className = 'progress-bar bg-warning';
                } else if (strength < 90) {
                    feedback = 'Good';
                    progressBar.className = 'progress-bar bg-info';
                } else {
                    feedback = 'Strong';
                    progressBar.className = 'progress-bar bg-success';
                }

                progressBar.style.width = Math.min(strength, 100) + '%';
                strengthText.textContent = feedback;
            });
        }

        // Form reset function
        window.resetForm = function() {
            if (confirm('Are you sure you want to reset the form? All changes will be lost.')) {
                document.querySelector('form').reset();
                document.querySelector('.password-strength').style.display = 'none';
                // Reset to original values
                document.getElementById('name').value = @json($user->name);
            }
        }

        // Form validation
        const form = document.getElementById('profileForm');
        const submitBtn = document.getElementById('submitBtn');

        if (form && submitBtn) {
            form.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const password = document.getElementById('password').value;
                const passwordConfirmation = document.getElementById('password_confirmation').value;

                // Basic client-side validation
                if (!name || name.length < 2) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please enter a valid name (at least 2 characters).',
                        confirmButtonColor: '#dc3545'
                    });
                    return false;
                }

                if (password && (password.length < 8 || password.length > 30)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Password must be between 8 and 30 characters long.',
                        confirmButtonColor: '#dc3545'
                    });
                    return false;
                }

                if (password && !/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/.test(password)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Password must contain at least one number, uppercase letter, lowercase letter, and special character (,.<>{}~!@#$%^&_).',
                        confirmButtonColor: '#dc3545'
                    });
                    return false;
                }

                if (password && password !== passwordConfirmation) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Password confirmation does not match.',
                        confirmButtonColor: '#dc3545'
                    });
                    return false;
                }

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
            });
        }
    });
</script>
