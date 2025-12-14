@extends('auth.layouts.auth')

@section('title', 'Update Password')

@push('fontawesome')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
@endpush

@push('password-toggle-css')
<link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
@endpush

@push('password-toggle-js')
<script src="{{ asset('js/password-toggle.js') }}"></script>
@endpush

@push('styles')
<style>
.login-container {
    width: 100%;
    max-width: 450px;
    text-align: center;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    padding: 40px 30px;
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
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password-confirm');
    const submitButton = document.querySelector('.primary-button');

    function validatePassword(password) {
        const errors = [];
        if (password.length < 8) errors.push('Password must be at least 8 characters long');
        if (password.length > 30) errors.push('Password cannot exceed 30 characters');
        if (!/[0-9]/.test(password)) errors.push('Password must contain at least one number');
        if (!/[A-Z]/.test(password)) errors.push('Password must contain at least one uppercase letter');
        if (!/[a-z]/.test(password)) errors.push('Password must contain at least one lowercase letter');
        if (!/[,.<>{}~!@#$%^&_]/.test(password)) errors.push('Password must contain at least one special character (,.<>{}~!@#$%^&_)');
        return errors;
    }

    function updatePasswordField(password) {
        const errors = validatePassword(password);
        const isValid = errors.length === 0;
        passwordInput.classList.remove('is-valid', 'is-invalid');
        passwordInput.classList.add(isValid ? 'is-valid' : 'is-invalid');
        const existingError = passwordInput.parentNode.querySelector('.password-error');
        if (existingError) existingError.remove();
        if (errors.length > 0) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback password-error';
            errorDiv.innerHTML = '<strong>' + errors.join('<br>') + '</strong>';
            passwordInput.parentNode.appendChild(errorDiv);
        }
    }

    function updatePasswordConfirmField() {
        const password = passwordInput.value;
        const confirmPassword = passwordConfirmInput.value;
        passwordConfirmInput.classList.remove('is-valid', 'is-invalid');
        if (confirmPassword.length > 0) {
            passwordConfirmInput.classList.add(password === confirmPassword ? 'is-valid' : 'is-invalid');
        }
    }

    passwordInput.addEventListener('input', function() {
        updatePasswordField(this.value);
        updatePasswordConfirmField();
    });

    passwordConfirmInput.addEventListener('input', updatePasswordConfirmField);

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

        submitButton.disabled = true;
        submitButton.textContent = 'Resetting Password...';
    });
});
</script>
@endpush

@section('content')
<div class="auth-flex-container">
    <div class="login-container">
        <x-auth-logo-container />
        <h2>Create New Password</h2>
        <p>Your new password must be different from previous used passwords.</p>

        @if (session('status'))
            <div class="auth-alert auth-alert-success">
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
                <label for="email" class="auth-label">Email Address</label>
                <input readonly id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                    name="email" value="{{ $email ?? old('email') }}" required autocomplete="email">
                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password" class="auth-label">New Password</label>
                <div class="password-field-wrapper">
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                        name="password" required autocomplete="new-password" placeholder="Enter new password">
                    <button type="button" class="password-toggle-btn" aria-label="Show password">
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
                <label for="password-confirm" class="auth-label">Confirm Password</label>
                <div class="password-field-wrapper">
                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required
                        autocomplete="new-password" placeholder="Confirm new password">
                    <button type="button" class="password-toggle-btn" aria-label="Show password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="primary-button">Reset Password</button>
        </form>
    </div>
</div>
@endsection
