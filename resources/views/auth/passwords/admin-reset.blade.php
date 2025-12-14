@extends('auth.layouts.auth')

@section('title', 'Admin Create New Password')

@push('password-toggle-css')
<link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
@endpush

@push('password-toggle-js')
<script src="{{ asset('js/password-toggle.js') }}"></script>
@endpush

@push('fontawesome')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endpush

@push('styles')
<style>
.login-container {
    width: 100%;
    max-width: 480px;
    text-align: center;
    background: white;
    border-radius: 10px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    padding: 30px;
}
.form-group {
    text-align: left;
    margin-bottom: 1rem;
}
.form-control {
    border-radius: 6px;
    padding: 12px 14px;
}
.primary-button {
    width: 100%;
    padding: 12px;
    background: #000;
    color: #fff;
    border: 0;
    border-radius: 6px;
    font-weight: 600;
}
.primary-button:hover {
    background: #333;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password toggle functionality is now handled by password-toggle.js
    // The script automatically initializes password toggle buttons
});
</script>
@endpush

@section('content')
<div class="auth-flex-container">
    <div class="login-container">
        <x-auth-logo-container />
        <h2 class="auth-heading-text">Set a New Password</h2>
        <p class="auth-email-text">Your new password must meet security requirements.</p>

        @if ($errors->any())
            <div class="auth-alert auth-alert-danger" role="alert">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email" class="auth-label">Email Address</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" required readonly>
                @error('email')
                    <div class="auth-invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password" class="auth-label">New Password</label>
                <div class="password-field-wrapper">
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" placeholder="Enter new password">
                    <button type="button" class="password-toggle-btn" aria-label="Show password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                @error('password')
                    <div class="auth-invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password-confirm" class="auth-label">Confirm Password</label>
                <div class="password-field-wrapper">
                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm new password">
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
