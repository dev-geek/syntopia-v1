@extends('admin.layouts.auth')

@section('title', 'Admin Login - Syntopia')

@section('body-class', 'login-page')

@push('password-toggle-css')
<link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
@endpush

@push('password-toggle-js')
<script src="{{ asset('js/password-toggle.js') }}"></script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@endpush

@section('content')
<div class="login-box">
    <div class="container-box">
        <div class="logo-container">
            <x-logo />
        </div>
        <h1 class="heading-text">Admin Login</h1>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login') }}">
            @csrf
            <div class="form-group">
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input
                        id="email"
                        type="email"
                        placeholder="Email"
                        class="form-control @error('email') is-invalid @enderror"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        autofocus
                    >
                </div>
                @error('email')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="form-group">
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input
                        id="password"
                        type="password"
                        placeholder="Password"
                        class="form-control @error('password') is-invalid @enderror"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </div>
                @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div class="forgot-password-wrapper">
                <a href="{{ route('admin.password.request') }}" class="forgot-password-link">
                    Forgot Password?
                </a>
            </div>

            <button type="submit" class="primary-button">
                Sign In
            </button>
        </form>
    </div>
</div>
@endsection
