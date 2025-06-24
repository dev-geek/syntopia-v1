@extends('layouts.app')

@section('content')
<style>
    body {
        background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
        background-size: cover;
        min-height: 100vh;
        padding: 30px;
        margin: 0;
    }

    .heading-text {
        font-size: 24px;
        padding-bottom: 20px;
        font-weight: 500;
        color: #000;
    }

    .container-box {
        max-width: 100%;
        width: 520px;
        padding: 30px;
        background: white;
        border-radius: 10px;
        box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        margin: 0 auto;
    }

    .email-text {
        font-size: 13px;
        margin-bottom: 20px;
    }

    .logo-container {
        text-align: center;
        margin: 20px 0 40px;
        display: flex;
        justify-content: center;
    }

    .logo-container img {
        width: 160px;
        height: auto;
    }

    .form-control {
        background: #E7E7E9;
        font-size: 13px;
        border: none !important;
        padding: 12px 15px !important;
        height: auto;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    label {
        font-weight: 500;
        font-size: 13px;
        text-align: left;
        display: block;
        margin-bottom: 8px;
    }

    .btn-light {
        background-color: #E7E7E9;
        font-size: 14px;
        font-weight: 500;
    }

    .footer-text {
        text-align: center;
        margin-top: 30px;
        color: #6c757d;
        font-size: 11px;
    }

    .primary-button {
        width: 100%;
        padding: 12px;
        font-size: 13px;
        font-weight: 500;
        background: #3e57da;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        margin: 10px 0 20px;
        transition: background-color 0.3s;
    }

    .primary-button:hover {
        background: #344ca8;
    }

    .text-primary {
        color: #3e57da !important;
    }

    @media (max-width: 768px) {
        body {
            padding: 15px;
        }
        
        .container-box {
            padding: 20px;
        }
    }
</style>

<div class="container">

<body>
    <div class="logo-container">
        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
    </div>

    <div class="d-flex justify-content-center align-items-center">
        <div class="container-box text-center">
            <h1 class="heading-text">Check your Email</h1>
            <p class="email-text">Please enter the verification code was sent to {{ $email ?? Auth::user()->email }}</p>

            <form method="POST" action="{{ url('/verify-code') }}">
                @csrf
                <div class="mb-3">
                    <label for="verification-code">Verification code</label>
                    <input type="text" name="verification_code" id="verification-code" class="form-control @error('verification_code') is-invalid @enderror" placeholder="Paste verification code" required>
                    @error('verification_code')
                    <div class="invalid-feedback d-block text-start">
                        {{ $message }}
                    </div>
                    @enderror
                </div>
                <button type="submit" class="primary-button">Verify Code</button>
            </form>


            <div class="mb-3">
                <p class="email-text">
                    Can't find the email? <a href="{{ route('resend.code') }}" class="text-secondary">Resend code</a>
                </p>
            </div>



            <p class="text-muted mt-3" style="font-size: 11px;">
                By joining the workspace, you agree to our
                <a href="#" class="text-primary">User Terms of Service</a> and
                <a href="#" class="text-primary">Privacy Policy</a>.
            </p>
        </div>
    </div>

    <div class="footer-text">
        Having trouble? Contact us at
        <a href="mailto:info@syntopia.ai" class="text-primary">info@syntopia.ai</a>.
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</div>
@endsection