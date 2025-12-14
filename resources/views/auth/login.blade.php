@extends('auth.layouts.auth')

@section('title', 'Login Page')

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
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
}

body {
    display: flex;
    justify-content: center;
    align-items: center;
}

.container {
    display: flex;
    width: 100%;
    height: 100vh;
    max-width: 100%;
    padding: 0;
    overflow: hidden;
    background: white;
}

.left-section, .right-section {
    height: 100%;
}

.left-section {
    width: 50%;
    position: relative;
    background: #e3f2fd;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
}

.video-container {
    position: relative;
    width: 100%;
    height: 100%;
}

.video-container video {
    position: absolute;
    border-radius: 14px;
    object-fit: cover;
}

.video1 { width: 40%; top: 1%; left: 1%; }
.video2 { width: 25%; top: 2%; right: 2%; }
.video3 { width: 40%; top: 57%; right: 3%; }
.video4 { width: 25%; top: 15%; right: 30%; }
.video5 { width: 24%; top: 60%; left: 28%; }
.video6 { width: 25%; bottom: 33%; left: 2%; }
.video7 { width: 40%; bottom: 2%; right: 2%; }

.right-section {
    width: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    text-align: center;
    padding: 40px;
    overflow: auto;
}

.right-section h2 {
    font-weight: 500;
    font-size: 24px;
}

.login-container {
    width: 100%;
    max-width: 360px;
    margin: 40px 0;
}

.logo {
    width: 150px;
    margin-bottom: 20px;
}

label {
    display: block;
    text-align: left;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 13px;
}

input {
    max-width: 100%;
    font-size: 13px;
    width: 100%;
    padding: 10px;
    margin: 10px 0 20px;
    border: 1px solid #ccc;
    border-radius: 5px;
    background: #E7E7E9;
}

.email-field-wrapper {
    position: relative;
}

.email-clear-btn {
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

.email-clear-btn:hover {
    background: #e9ecef;
    border-color: #adb5bd;
    color: #495057;
    transform: translateY(-50%) scale(1.05);
}

.email-field-wrapper input[type="email"] {
    padding-right: 50px !important;
}

.primary-button, .secondary-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 500;
}

.primary-button {
    width: 100%;
    padding: 10px;
    background: rgb(62, 87, 218);
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    margin-bottom: 20px;
}

.secondary-button {
    width: 100%;
    padding: 10px;
    background: white;
    border: 1px solid #ccc;
    border-radius: 5px;
    cursor: pointer;
    margin-bottom: 20px;
}

.login-container a {
    color: black;
    font-size: 13px;
}

.divider {
    margin: 10px 0;
    color: #aaa;
}

.terms, .support {
    font-size: 11px !important;
    color: #777;
}

.terms a, .support a {
    color: #777;
    font-size: 11px !important;
}

.login-container p {
    font-size: 13px;
}

.invalid-feedback {
    display: block !important;
    color: #dc3545;
    font-size: 12px;
    margin-top: -15px;
    margin-bottom: 10px;
}

.is-invalid {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

@media (max-width: 768px) {
    .left-section {
        display: none;
    }
    .right-section {
        width: 100%;
        padding: 50px 20px;
    }
    .right-section h2 {
        font-size: 32px;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        const params = new URLSearchParams(window.location.search);
        const adon = params.get('adon');
        if (adon) {
            sessionStorage.setItem('pendingAddon', adon);
        }
    } catch (e) {}

    const emailInput = document.getElementById('email');
    const emailClearBtn = document.getElementById('emailClearBtn');
    const passwordField = document.getElementById('password-field');
    const continueBtn = document.getElementById('continueBtn');
    const loginForm = document.getElementById('loginForm');

    if (passwordField.style.display === 'block') {
        const passwordInput = document.getElementById('password');
        if (passwordInput && window.PasswordToggle) {
            PasswordToggle.addToField(passwordInput);
        }
    }

    function toggleClearButton() {
        if (emailInput.value.trim() !== '') {
            emailClearBtn.style.display = 'flex';
        } else {
            emailClearBtn.style.display = 'none';
        }
    }

    emailClearBtn.addEventListener('click', function() {
        emailInput.value = '';
        emailInput.focus();
        toggleClearButton();

        if (passwordField.style.display === 'block') {
            passwordField.style.display = 'none';
            continueBtn.textContent = 'Continue with email';
            continueBtn.onclick = checkEmail;
            emailInput.classList.remove('is-invalid');
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                passwordInput.classList.remove('is-invalid');
            }
        }
    });

    emailInput.addEventListener('input', toggleClearButton);
    toggleClearButton();
});

async function checkEmail() {
    const email = document.getElementById('email').value;
    const passwordField = document.getElementById('password-field');
    const continueBtn = document.getElementById('continueBtn');
    const loginForm = document.getElementById('loginForm');

    try {
        const response = await fetch('/check-email', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: JSON.stringify({ email: email })
        });

        const data = await response.json();

        if (data.exists) {
            passwordField.style.display = 'block';
            continueBtn.textContent = 'Login';
            continueBtn.onclick = () => loginForm.submit();

            const passwordInput = document.getElementById('password');
            if (passwordInput && window.PasswordToggle) {
                PasswordToggle.addToField(passwordInput);
            }
        } else {
            window.location.href = '/register?email=' + encodeURIComponent(email);
        }
    } catch (error) {
        console.error('Error:', error);
    }
}
</script>
@endpush

@section('content')
<div class="container">
    <div class="left-section">
        <div class="video-container">
            <video src="https://syntopia.ai/wp-content/uploads/2025/11/Video-1-Adrian-Concepcion-not-finished.mp4" autoplay loop muted playsinline class="video1"></video>
            <video src="https://syntopia.ai/wp-content/uploads/2025/11/特斯拉京东直播.mp4" autoplay loop muted playsinline class="video2"></video>
            <video src="https://syntopia.ai/wp-content/uploads/2025/11/Login-page-video-1.mov" autoplay loop muted playsinline class="video3"></video>
            <video src="https://syntopia.ai/wp-content/uploads/2025/11/black-cloth-can-go-on-login-page.mp4" autoplay loop muted playsinline class="video4"></video>
            <video src="https://syntopia.ai/wp-content/uploads/2025/11/泰语直播.mp4" autoplay loop muted playsinline class="video5"></video>
            <video src="https://syntopia.ai/wp-content/uploads/2025/11/avatar-video-demo-can-go-on-login-page.mp4" autoplay loop muted playsinline class="video6"></video>
            <video src="https://syntopia.ai/wp-content/uploads/2025/11/Login-page-video-2.mov" autoplay loop muted playsinline class="video7"></video>
        </div>
    </div>

    <div class="right-section">
        <div class="login-container">
            <x-logo alt="Logo" />
            <h2>Welcome to Syntopia</h2>
            <form method="POST" action="{{ route('login.post') }}" id="loginForm">
                @csrf
                <div class="input-field">
                    <label for="email">Email</label>
                    <div class="email-field-wrapper position-relative">
                        <input type="email" placeholder="Type your email..." name="email" value="{{ old('email') }}" required autocomplete="email" autofocus id="email" class="form-control @error('email') is-invalid @enderror">
                        <button type="button" class="email-clear-btn" id="emailClearBtn" style="display: none;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    @error('email')
                        <span class="invalid-feedback" role="alert" style="text-align: left;padding-bottom: 10px;">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="input-field" id="password-field" style="display: {{ $errors->has('password') || $errors->has('email') ? 'block' : 'none' }};">
                    <label for="password">Password</label>
                    <div class="password-field-wrapper position-relative">
                        <input id="password" type="password" placeholder="Enter your password..." class="@error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                    </div>
                    @error('password')
                        <span class="invalid-feedback" role="alert" style="text-align: left;padding-bottom: 10px;">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <button type="button" class="primary-button" id="continueBtn" onclick="{{ $errors->has('password') || $errors->has('email') ? 'loginForm.submit()' : 'checkEmail()' }}">
                    {{ $errors->has('password') || $errors->has('email') ? 'Login' : 'Continue with email' }}
                </button>
            </form>
            <a href="{{ route('password.request') }}">Forgot password?</a>
            <div class="divider">or</div>
            <a href="{{ route('auth.google') }}">
                <button class="secondary-button google">
                    <img src="https://syntopia.ai/wp-content/uploads/2025/02/google-icon.png" alt="Google Logo">
                    Continue with Google
                </button>
            </a>
            <p class="terms">By signing up to the Syntopia platform, you understand and agree with our <a href="#">Customer Terms of Service</a> and <a href="#">Privacy Policy</a>.</p>
        </div>
        <p class="support">Having trouble? Contact us at <a href="mailto:info@syntopia.ai">info@syntopia.ai</a></p>
    </div>
</div>
@endsection
