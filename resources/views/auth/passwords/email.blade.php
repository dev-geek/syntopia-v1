@extends('auth.layouts.auth')

@section('title', 'Reset Password')

@section('content')
<div class="auth-flex-container">
    <div class="auth-container-box small">
        <x-auth-logo-container />
        <h2 class="auth-heading-text">Reset Your Password</h2>
        <p class="auth-email-text">Forgot Your Password? Please enter your email and we'll send you a reset link.</p>

        @if (session('status'))
            <div class="auth-alert auth-alert-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="auth-alert auth-alert-danger">
                <strong>Please fix the following errors:</strong><br>
                @foreach ($errors->all() as $error)
                    • {{ $error }}<br>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <input id="email" type="email" class="auth-form-control @error('email') auth-is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="Enter Your email..." autofocus>
            @error('email')
                <span class="auth-invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
            @enderror
            <button type="submit" class="auth-primary-button purple">Submit</button>
        </form>
    </div>
</div>
@endsection
