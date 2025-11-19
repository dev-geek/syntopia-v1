@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        background-image: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
        min-height: 100vh;
        padding: 20px;
        margin: 0;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .heading-text {
        font-size: 28px;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
        text-align: center;
    }

    .container-box {
        max-width: 480px;
        width: 100%;
        padding: 40px;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        margin: 0 auto;
        position: relative;
        overflow: hidden;
    }

    .container-box::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3e57da, #667eea);
    }

    .email-text {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 32px;
        line-height: 1.5;
        text-align: center;
    }

    .logo-container {
        text-align: center;
        margin-bottom: 32px;
        display: flex;
        justify-content: center;
    }

    .logo-container img {
        width: 140px;
        height: auto;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }

    .form-control {
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        padding: 14px 16px;
        height: auto;
        transition: all 0.2s ease;
        font-weight: 500;
        width: 100%;
        box-sizing: border-box;
    }

    .form-control:focus {
        background: #ffffff;
        border-color: #3e57da;
        box-shadow: 0 0 0 3px rgba(62, 87, 218, 0.1);
        outline: none;
    }

    .form-control::placeholder {
        color: #9ca3af;
        font-weight: 400;
    }

    .form-group {
        margin-bottom: 24px;
    }

    label {
        font-weight: 600;
        font-size: 14px;
        color: #374151;
        margin-bottom: 8px;
        display: block;
    }

    .btn-light {
        background-color: #f8fafc;
        border: 2px solid #e2e8f0;
        font-size: 14px;
        font-weight: 500;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .btn-light:hover {
        background-color: #f1f5f9;
        border-color: #cbd5e1;
    }

    .footer-text {
        text-align: center;
        margin-top: 40px;
        color: #6b7280;
        font-size: 12px;
        line-height: 1.5;
    }

    .primary-button {
        width: 100%;
        padding: 14px 20px;
        font-size: 14px;
        font-weight: 600;
        background: linear-gradient(135deg, #3e57da 0%, #4f46e5 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        margin: 8px 0 24px;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .primary-button:hover {
        background: linear-gradient(135deg, #344ca8 0%, #4338ca 100%);
        transform: translateY(-1px);
        box-shadow: 0 8px 20px rgba(62, 87, 218, 0.3);
    }

    .primary-button:active {
        transform: translateY(0);
    }

    .text-primary {
        color: #3e57da !important;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease;
    }

    .text-primary:hover {
        color: #344ca8 !important;
        text-decoration: underline;
    }

    .btn-link {
        color: #3e57da !important;
        text-decoration: none;
        font-weight: 500;
        transition: color 0.2s ease;
        background: none;
        border: none;
        padding: 0;
        margin: 0;
    }

    .btn-link:hover {
        color: #344ca8 !important;
        text-decoration: underline;
    }

    .btn-outline-primary {
        color: #3e57da;
        border: 2px solid #3e57da;
        background: transparent;
        font-size: 14px;
        font-weight: 500;
        padding: 8px 16px;
        border-radius: 6px;
        transition: all 0.2s ease;
        text-decoration: none;
        width: 100%;
    }

    .btn-outline-primary:hover {
        background: #3e57da;
        color: white;
        border-color: #3e57da;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(62, 87, 218, 0.2);
    }

    .btn-outline-primary:focus {
        outline: 2px solid #3e57da;
        outline-offset: 2px;
    }

    .invalid-feedback {
        font-size: 12px;
        color: #dc2626;
        margin-top: 6px;
        font-weight: 500;
    }

    .text-muted {
        color: #6b7280 !important;
        line-height: 1.5;
    }

    /* Validation Error Styles */
    .alert {
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 20px;
        font-size: 14px;
        line-height: 1.5;
        border: 1px solid transparent;
    }

    .alert-danger {
        background-color: #fef2f2;
        border-color: #fecaca;
        color: #dc2626;
    }

    .alert-warning {
        background-color: #fff3cd;
        border-color: #ffeaa7;
        color: #856404;
    }

    .alert-info {
        background-color: #d1ecf1;
        border-color: #bee5eb;
        color: #0c5460;
    }

    .alert-success {
        background-color: #d1f2eb;
        border-color: #a8e6cf;
        color: #0f5132;
    }

    .form-control.is-invalid {
        border-color: #dc2626;
        background-color: #fef2f2;
    }

    .form-control.is-invalid:focus {
        border-color: #dc2626;
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
    }

    .validation-errors {
        margin-bottom: 20px;
    }

    .validation-errors ul {
        margin: 0;
        padding-left: 20px;
        list-style-type: disc;
    }

    .validation-errors li {
        margin-bottom: 4px;
        font-size: 13px;
    }

    /* Animation for container */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .container-box {
        animation: fadeInUp 0.6s ease-out;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        body {
            padding: 16px;
        }

        .container-box {
            padding: 32px 24px;
            margin: 0;
        }

        .heading-text {
            font-size: 24px;
        }

        .logo-container img {
            width: 120px;
        }
    }

    @media (max-width: 480px) {
        .container-box {
            padding: 24px 20px;
        }

        .heading-text {
            font-size: 22px;
        }

        .email-text {
            font-size: 13px;
        }
    }

    /* Loading state for button */
    .primary-button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    /* Focus styles for accessibility */
    .primary-button:focus,
    .form-control:focus,
    .btn-link:focus {
        outline: 2px solid #3e57da;
        outline-offset: 2px;
    }

    /* Verification Code Clear Button Styles */
    .verification-code-field-wrapper {
        position: relative;
        display: inline-block;
        width: 100%;
    }

    .verification-code-clear-btn {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 10;
        color: #6c757d;
    }

    .verification-code-clear-btn:hover {
        background: #e9ecef;
        border-color: #adb5bd;
        color: #495057;
        transform: translateY(-50%) scale(1.05);
    }

    .verification-code-clear-btn:active {
        transform: translateY(-50%) scale(0.95);
    }

    .verification-code-clear-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        border-color: #0d6efd;
    }

    .verification-code-clear-btn svg {
        width: 14px;
        height: 14px;
        transition: all 0.2s ease;
    }

    .verification-code-clear-btn:hover svg {
        transform: scale(1.1);
    }

    /* Ensure verification code input has right padding to accommodate the clear button */
    .verification-code-field-wrapper input[type="text"] {
        padding-right: 50px !important;
    }

    /* Focus state for accessibility */
    .verification-code-field-wrapper:focus-within .verification-code-clear-btn {
        border-color: #0d6efd;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
    }

    /* Responsive adjustments for verification code clear button */
    @media (max-width: 768px) {
        .verification-code-clear-btn {
            right: 8px;
            width: 28px;
            height: 28px;
        }

        .verification-code-clear-btn svg {
            width: 12px;
            height: 12px;
        }

        .verification-code-field-wrapper input[type="text"] {
            padding-right: 44px !important;
        }
    }
</style>

<div class="container">

<body>
    <div class="d-flex justify-content-center align-items-center">
        <div class="container-box text-center">
            <div class="logo-container">
                <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
            </div>
            <h1 class="heading-text">Check your Email</h1>
            <p class="email-text">Please enter the verification code was sent to {{ $email ?? Auth::user()->email }}</p>

            {{-- Display specific field errors --}}
            @if ($errors->has('email'))
                <div class="alert alert-danger">
                    <strong>Email Error:</strong> {{ $errors->first('email') }}
                </div>
            @endif

            @if ($errors->has('server_error'))
                <div class="alert alert-danger">
                    <strong>Server Error:</strong> {{ $errors->first('server_error') }}
                </div>
            @endif

            @if (session('mail_error'))
                <div class="alert alert-warning">
                    <strong>Email Service Notice:</strong> {{ session('mail_error') }}
                    <br><small>You can still proceed with verification if you received the email.</small>
                </div>
            @endif

            @if (session('verification_code'))
                <div class="alert alert-info">
                    <strong>Verification Code:</strong> {{ session('verification_code') }}
                    <br><small>Use this code to verify your email address.</small>
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ url('/verify-code') }}">
                @csrf
                <input type="hidden" name="email" value="{{ $email ?? Auth::user()->email }}">
                <div class="mb-3">
                    <label for="verification-code">Verification code</label>
                    <div class="verification-code-field-wrapper position-relative">
                        <input type="text"
                               name="verification_code"
                               id="verification-code"
                               class="form-control @error('verification_code') is-invalid @enderror"
                               placeholder="Paste verification code"
                               value="{{ old('verification_code') }}"
                               required>
                        <button type="button" class="verification-code-clear-btn" id="verificationCodeClearBtn" style="display: none;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
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
                    Can't find the email?
                </p>
                <form method="POST" action="{{ route('verification.resend') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">Resend code</button>
                </form>
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

    @if (session('verification_swal_error'))
        <form id="swal-delete-user-form" method="POST" action="{{ route('verification.deleteUserAndRedirect') }}" style="display:none;">
            @csrf
            <input type="hidden" name="email" value="{{ $email ?? Auth::user()->email }}">
        </form>
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
                Swal.fire({
                    icon: 'error',
                    title: 'Verification Error',
                    text: @json(session('verification_swal_error')),
                    confirmButtonText: 'OK'
                }).then(() => {
                    document.getElementById('swal-delete-user-form').submit();
                });
            </script>
        @endpush
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Verification code clear button functionality
    document.addEventListener('DOMContentLoaded', function() {
        const verificationCodeInput = document.getElementById('verification-code');
        const verificationCodeClearBtn = document.getElementById('verificationCodeClearBtn');

        // Show/hide clear button based on input value
        function toggleClearButton() {
            if (verificationCodeInput.value.trim() !== '') {
                verificationCodeClearBtn.style.display = 'flex';
            } else {
                verificationCodeClearBtn.style.display = 'none';
            }
        }

        // Clear verification code input
        verificationCodeClearBtn.addEventListener('click', function() {
            verificationCodeInput.value = '';
            verificationCodeInput.focus();
            toggleClearButton();
        });

        // Listen for input changes
        verificationCodeInput.addEventListener('input', toggleClearButton);

        // Initial check
        toggleClearButton();
    });
    </script>
</div>
@endsection
