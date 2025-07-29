<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <title>Update Password</title>
    <style>
        body {
            background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            text-align: center;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .logo {
            width: 150px;
            margin-bottom: 20px;
        }


        input {
            max-width: 100%;
            width: 100%;
            padding: 10px;
            margin: 10px 0 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #E7E7E9;
        }

        .primary-button {
            width: 100%;
            padding: 10px;
            background: #5B0DD5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        /* Password toggle styles - identical to main CSS */
        .password-field-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #e0e0e0;
            color: #6c757d;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
            font-size: 14px;
            height: 32px;
            width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .password-toggle-btn:hover {
            background: rgba(255, 255, 255, 1);
            color: #0d6efd;
            border-color: #0d6efd;
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.15);
            transform: translateY(-50%) scale(1.05);
        }

        .password-toggle-btn:active {
            transform: translateY(-50%) scale(0.95);
        }

        .password-toggle-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
            border-color: #0d6efd;
        }

        .password-toggle-btn i {
            font-size: 16px;
            line-height: 1;
            transition: all 0.2s ease;
        }

        .password-toggle-btn:hover i {
            transform: scale(1.1);
        }

        /* Ensure password input has right padding to accommodate the toggle button */
        .password-field-wrapper input[type="password"],
        .password-field-wrapper input[type="text"] {
            padding-right: 50px !important;
        }

        /* Animation for icon change */
        .password-toggle-btn i.fa-eye,
        .password-toggle-btn i.fa-eye-slash {
            transition: all 0.3s ease;
        }

        .password-toggle-btn:hover i.fa-eye {
            animation: eyeWink 0.6s ease;
        }

        .password-toggle-btn:hover i.fa-eye-slash {
            animation: eyeWink 0.6s ease;
        }

        @keyframes eyeWink {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(0.8);
            }
        }

        /* Focus state for accessibility */
        .password-field-wrapper:focus-within .password-toggle-btn {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
        }

        /* Validation styles */
        .form-control.is-valid {
            border-color: #198754;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='m2.3 6.73.94-.94 3.03-3.03-1.06-1.06-3.03 3.03-.94.94z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 1.4 1.4m0-1.4-1.4 1.4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }

        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #198754;
        }

        .primary-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>

<body>

    <div class="login-container">
        <img src="https://syntopia.ai/wp-content/uploads/2025/02/Syntopiaa-logo.webp" alt="Logo" class="logo">
        <h2>Create New Password</h2>
        <p>Your new password must be different from previous used passwords.</p>
        @if ($errors->any())
            <div class="alert alert-danger" style="text-align: left; margin-bottom: 20px;">
                <strong>Please fix the following errors:</strong><br>
                @foreach ($errors->all() as $error)
                    • {{ $error }}<br>
                @endforeach
            </div>
        @endif

        @if (session('status'))
            <div class="alert alert-success" style="text-align: left; margin-bottom: 20px;">
                {{ session('status') }}
            </div>
        @endif

        <div class="alert alert-info" style="text-align: left; margin-bottom: 20px;">
            <strong>Password Requirements:</strong><br>
            • At least 8 characters long<br>
            • Must contain uppercase and lowercase letters<br>
            • Must contain at least one number<br>
            • Must contain at least one special character (,.<>{}~!@#$%^&_)
        </div>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <input readonly id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus>

            @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
            <div class="password-field-wrapper">
                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                    name="password" required autocomplete="new-password" placeholder="Enter new password">
                <button type="button" class="password-toggle-btn" aria-label="Show password"
                    title="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            @error('password')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror

            <div class="password-field-wrapper">
                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required
                    autocomplete="new-password" placeholder="Confirm new password">
                <button type="button" class="password-toggle-btn" aria-label="Show password"
                    title="Toggle password visibility">
                    <i class="fas fa-eye"></i>
                </button>
            </div>

            <button type="submit" class="primary-button">Reset Password</button>
        </form>


    </div>

    <!-- Password Toggle Script -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>

    <!-- Client-side Validation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password-confirm');
            const submitButton = document.querySelector('.primary-button');

            // Password validation regex
            const passwordRegex = /^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/;

            // Real-time password validation
            function validatePassword(password) {
                const errors = [];

                if (password.length < 8) {
                    errors.push('Password must be at least 8 characters long');
                }
                if (password.length > 30) {
                    errors.push('Password cannot exceed 30 characters');
                }
                if (!/[0-9]/.test(password)) {
                    errors.push('Password must contain at least one number');
                }
                if (!/[A-Z]/.test(password)) {
                    errors.push('Password must contain at least one uppercase letter');
                }
                if (!/[a-z]/.test(password)) {
                    errors.push('Password must contain at least one lowercase letter');
                }
                if (!/[,.<>{}~!@#$%^&_]/.test(password)) {
                    errors.push('Password must contain at least one special character (,.<>{}~!@#$%^&_)');
                }

                return errors;
            }

            // Update password field styling based on validation
            function updatePasswordField(password) {
                const errors = validatePassword(password);
                const isValid = errors.length === 0;

                passwordInput.classList.remove('is-valid', 'is-invalid');
                passwordInput.classList.add(isValid ? 'is-valid' : 'is-invalid');

                // Remove existing error messages
                const existingError = passwordInput.parentNode.querySelector('.password-error');
                if (existingError) {
                    existingError.remove();
                }

                // Add error messages if any
                if (errors.length > 0) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback password-error';
                    errorDiv.innerHTML = '<strong>' + errors.join('<br>') + '</strong>';
                    passwordInput.parentNode.appendChild(errorDiv);
                }
            }

            // Update password confirmation field
            function updatePasswordConfirmField() {
                const password = passwordInput.value;
                const confirmPassword = passwordConfirmInput.value;

                passwordConfirmInput.classList.remove('is-valid', 'is-invalid');

                if (confirmPassword.length > 0) {
                    if (password === confirmPassword) {
                        passwordConfirmInput.classList.add('is-valid');
                    } else {
                        passwordConfirmInput.classList.add('is-invalid');
                    }
                }
            }

            // Event listeners
            passwordInput.addEventListener('input', function() {
                updatePasswordField(this.value);
                updatePasswordConfirmField();
            });

            passwordConfirmInput.addEventListener('input', updatePasswordConfirmField);

            // Form submission validation
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = passwordConfirmInput.value;
                const passwordErrors = validatePassword(password);

                if (passwordErrors.length > 0) {
                    e.preventDefault();
                    alert('Please fix the password validation errors before submitting.');
                    return false;
                }

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Password confirmation does not match.');
                    return false;
                }

                // Disable submit button to prevent double submission
                submitButton.disabled = true;
                submitButton.textContent = 'Resetting Password...';
            });
        });
    </script>
</body>

</html>
