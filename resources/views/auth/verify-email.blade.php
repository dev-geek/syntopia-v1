@extends('auth.layouts.auth')

@section('title', 'Verify Email - Syntopia')

@push('bootstrap-js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@endpush

@section('content')
<x-auth-logo-container />

<div class="auth-flex-container">
    <div class="auth-container-box">
        <h1 class="auth-heading-text">Check your email</h1>
        <p class="auth-email-text">We sent a verification link to<br><strong>{{ session('email') }}</strong></p>

        <form method="POST" action="{{ route('verification.resend') }}">
            @csrf
            <button type="submit" class="auth-primary-button">Resend email</button>
        </form>
    </div>
</div>

<x-auth-footer-text />
@endsection
