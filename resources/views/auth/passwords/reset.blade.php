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

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">
    <link rel="shortcut icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">

    <style>
        body {
            background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            text-align: center;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 40px 30px;
        }

        .logo {
            width: 150px;
            margin-bottom: 25px;
        }

        h2 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }

        p {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            background: #f8f9fa;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #5B0DD5;
            background: white;
            box-shadow: 0 0 0 3px rgba(91, 13, 213, 0.1);
        }

        input[readonly] {
            background: #f1f3f4;
            color: #666;
            cursor: not-allowed;
        }

        .primary-button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #5B0DD5 0%, #7C3AED 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .primary-button:hover {
            background: linear-gradient(135deg, #4C0BB8 0%, #6B21A8 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(91, 13, 213, 0.3);
        }

        .primary-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Password toggle styles */
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
            transition: all 0.3s ease;
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
            color: #5B0DD5;
            border-color: #5B0DD5;
            box-shadow: 0 4px 8px rgba(91, 13, 213, 0.15);
            transform: translateY(-50%) scale(1.05);
        }

        .password-field-wrapper input[type="password"],
        .password-field-wrapper input[type="text"] {
            padding-right: 50px !important;
        }

        /* Alert styles */
        .alert {
            border-radius: 8px;
            border: none;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-danger {
            background: #fef2f2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #f0fdf4;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .alert-info {
            background: #eff6ff;
            color: #2563eb;
            border-left: 4px solid #2563eb;
        }

        /* Validation styles */
        .form-control.is-valid {
            border-color: #16a34a;
            background-color: #f0fdf4;
        }

        .form-control.is-invalid {
            border-color: #dc2626;
            background-color: #fef2f2;
        }

        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 8px;
            font-size: 13px;
            color: #dc2626;
            font-weight: 500;
        }

        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 8px;
            font-size: 13px;
            color: #16a34a;
            font-weight: 500;
        }

        /* Password requirements list */
        .password-requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: left;
        }

        .password-requirements strong {
            color: #374151;
            font-size: 14px;
            margin-bottom: 8px;
            display: block;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: #6b7280;
            font-size: 13px;
        }

        .password-requirements li {
            margin-bottom: 4px;
        }

        /* Animation for icon change */
        .password-toggle-btn i {
            transition: all 0.3s ease;
        }

        .password-toggle-btn:hover i {
            transform: scale(1.1);
        }

        @keyframes eyeWink {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(0.8); }
        }

        .password-toggle-btn:hover i.fa-eye,
        .password-toggle-btn:hover i.fa-eye-slash {
            animation: eyeWink 0.6s ease;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <x-logo alt="Logo" />
        <h2>Create New Password</h2>
        <p>Your new password must be different from previous used passwords.</p>

        @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        <div class="password-requirements">
            <strong>Password Requirements:</strong>
            <ul>
                <li>At least 8 characters long</li>
                <li>Must contain uppercase and lowercase letters</li>
                <li>Must contain at least one number</li>
                <li>Must contain at least one special character (,.<>{}~!@#$%^&_)</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input readonly id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                    name="email" value="{{ $email ?? old('email') }}" required autocomplete="email">
                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
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
            </div>

            <div class="form-group">
                <label for="password-confirm">Confirm Password</label>
                <div class="password-field-wrapper">
                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required
                        autocomplete="new-password" placeholder="Confirm new password">
                    <button type="button" class="password-toggle-btn" aria-label="Show password"
                        title="Toggle password visibility">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
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
