@extends('auth.layouts.auth')

@section('title', 'Update Password')

@section('content')
<div class="auth-flex-container">
    <div class="auth-container-box small">
        <x-auth-logo-container />
        <h2 class="auth-heading-text">Create New Password</h2>
        <p class="auth-email-text">Your new password must be different from previous used passwords.</p>

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            <input type="password" id="new-password" name="password" class="auth-form-control" placeholder="Enter new password" required>
            <input type="password" id="confirm-new-password" name="password_confirmation" class="auth-form-control" placeholder="Confirm new password" required>
            <button type="submit" class="auth-primary-button">Reset Password</button>
        </form>
    </div>
</div>
@endsection
