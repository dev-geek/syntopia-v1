<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <title>Login Page</title>

    <!-- Favicon -->
    <link rel="icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">
    <link rel="shortcut icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">

    <!-- SweetAlert2 -->
    <script defer src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="{{ asset('js/swal-utils.js') }}"></script>

    <!-- TikTok Pixel Code Start -->

<script>

!function (w, d, t) {

  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(

var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script")

;n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};





  ttq.load('D4I1AK3C77U1KRQJK2Q0');

  ttq.page();

}(window, document, 'ttq');

</script>

<!-- TikTok Pixel Code End -->

    <style>
    html,
    body {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }

    body {
        display: flex;
        justify-content: center;
        align-items: center;
        background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
        background-size: cover;
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

    .left-section,
    .right-section {
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

    /* Nicely balanced collage layout */
    .video1 {      /* top-left wide banner   */
        width: 40%;
        top: 1%;
        left: 1%;
    }

    .video2 {      /* top-right card         */
        width: 25%;
        top: 2%;
        right: 2%;
    }

    .video3 {      /* mid-left portrait      */
        width: 40%;
        top: 57%;
        right: 3%;
    }

    .video4 {      /* mid-center medium      */
        width: 25%;
        top: 15%;
        right: 30%;
    }

    .video5 {      /* mid-right tall         */
        width: 24%;
        top: 60%;
        left: 28%;
    }

    .video6 {      /* bottom-left wide       */
        width: 25%;
        bottom: 33%;
        left: 2%;
    }

    .video7 {      /* bottom-mid/right card  */
        width: 40%;
        bottom: 2%;
        right: 2%;
    }

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

    /* Password toggle styles - identical to main CSS */
    .password-field-wrapper {
        position: relative;
        display: inline-block;
        width: 100%;
    }

    .password-toggle-btn {
        position: absolute;
        right: 12px;
        top: 43%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid #e0e0e0;
        color: #6c757d;
        cursor: pointer;
        padding: 8px;
        border-radius: 6px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
        color: #0d6efd;
        border-color: #0d6efd;
        box-shadow: 0 4px 8px rgba(13, 110, 253, 0.15);
        transform: translateY(-50%) scale(1.05);
    }

    .password-toggle-btn:active {
        transform: translateY(-50%) scale(0.95);
    }

    .password-toggle-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        border-color: #0d6efd;
    }

    .password-toggle-btn i {
        font-size: 16px;
        line-height: 1;
        transition: all 0.2s ease;
    }

    .password-toggle-btn:hover i {
        transform: scale(1.1);
    }

    /* Ensure password input has right padding to accommodate the toggle button */
    .password-field-wrapper input[type="password"],
    .password-field-wrapper input[type="text"] {
        padding-right: 50px !important;
    }

    /* Animation for icon change */
    .password-toggle-btn i.fa-eye,
    .password-toggle-btn i.fa-eye-slash {
        transition: all 0.3s ease;
    }

    .password-toggle-btn:hover i.fa-eye {
        animation: eyeWink 0.6s ease;
    }

    .password-toggle-btn:hover i.fa-eye-slash {
        animation: eyeWink 0.6s ease;
    }

    @keyframes eyeWink {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(0.8); }
    }

    /* Focus state for accessibility */
    .password-field-wrapper:focus-within .password-toggle-btn {
        border-color: #0d6efd;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
    }

    /* Email Clear Button Styles */
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

    .email-clear-btn:active {
        transform: translateY(-50%) scale(0.95);
    }

    .email-clear-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        border-color: #0d6efd;
    }

    .email-clear-btn i {
        font-size: 14px;
        line-height: 1;
        transition: all 0.2s ease;
    }

    .email-clear-btn:hover i {
        transform: scale(1.1);
    }

    /* Ensure email input has right padding to accommodate the clear button */
    .email-field-wrapper input[type="email"] {
        padding-right: 50px !important;
    }

    /* Focus state for accessibility */
    .email-field-wrapper:focus-within .email-clear-btn {
        border-color: #0d6efd;
        box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
    }

    /* Responsive adjustments for password toggle */
    @media (max-width: 768px) {
        .password-toggle-btn {
            padding: 6px;
            right: 8px;
            height: 28px;
            width: 28px;
        }

        .password-toggle-btn i {
            font-size: 14px;
        }

        .password-field-wrapper input[type="password"],
        .password-field-wrapper input[type="text"] {
            padding-right: 44px !important;
        }

        .email-clear-btn {
            right: 8px;
            width: 28px;
            height: 28px;
        }

        .email-clear-btn i {
            font-size: 12px;
        }

        .email-field-wrapper input[type="email"] {
            padding-right: 44px !important;
        }
    }

    @media (max-width: 480px) {
        .password-toggle-btn {
            padding: 5px;
            right: 6px;
            height: 26px;
            width: 26px;
        }

        .password-toggle-btn i {
            font-size: 13px;
        }

        .password-field-wrapper input[type="password"],
        .password-field-wrapper input[type="text"] {
            padding-right: 40px !important;
        }
    }

    .primary-button,
    .secondary-button {
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

    .terms,
    .support {
        font-size: 11px !important;
        color: #777;
    }

    .terms a,
    .support a {
        color: #777;
        font-size: 11px !important;
    }

    .login-container p {
        font-size: 13px;
    }

    /* Validation error styling */
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

    .is-invalid:focus {
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
</head>

<body>
    <div class="container">
        <!-- Left Side -->
        <div class="left-section">
            <div class="video-container">
                <video src="https://syntopia.ai/wp-content/uploads/2025/11/Video-1-Adrian-Concepcion-not-finished.mp4"
                       autoplay loop muted playsinline class="video1"></video>

                <video src="https://syntopia.ai/wp-content/uploads/2025/11/特斯拉京东直播.mp4"
                       autoplay loop muted playsinline class="video2"></video>

                <video src="https://syntopia.ai/wp-content/uploads/2025/11/Login-page-video-1.mov"
                       autoplay loop muted playsinline class="video3"></video>

                <video src="https://syntopia.ai/wp-content/uploads/2025/11/black-cloth-can-go-on-login-page.mp4"
                       autoplay loop muted playsinline class="video4"></video>

                <video src="https://syntopia.ai/wp-content/uploads/2025/11/泰语直播.mp4"
                       autoplay loop muted playsinline class="video5"></video>

                <video src="https://syntopia.ai/wp-content/uploads/2025/11/avatar-video-demo-can-go-on-login-page.mp4"
                       autoplay loop muted playsinline class="video6"></video>

                <video src="https://syntopia.ai/wp-content/uploads/2025/11/Login-page-video-2.mov"
                       autoplay loop muted playsinline class="video7"></video>
            </div>
        </div>

        <!-- Right Side -->
        <div class="right-section">
            <div class="login-container">
                <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Logo"
                    class="logo">
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
                <!-- <a href="{{ route('login.facebook') }}">
                    <button class="secondary-button sso"><img
                            src="https://syntopia.ai/wp-content/uploads/2025/02/facebook-icon.png"
                            alt="Facebook Logo"> Continue with Facebook</button>
                </a> -->
                <p class="terms">By signing up to the Syntopia platform, you understand and agree with our <a
                        href="#">Customer Terms of Service</a> and <a href="#">Privacy Policy</a>.</p>
            </div>
            <p class="support">Having trouble? Contact us at <a href="mailto:info@syntopia.ai">info@syntopia.ai</a></p>
        </div>
    </div>

    <script>
    // Email clear button functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Persist add-on request for post-login flow
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

        // Initialize password toggle if password field is visible (due to validation errors)
        if (passwordField.style.display === 'block') {
            const passwordInput = document.getElementById('password');
            if (passwordInput && window.PasswordToggle) {
                PasswordToggle.addToField(passwordInput);
            }
        }

        // Show/hide clear button based on input value
        function toggleClearButton() {
            if (emailInput.value.trim() !== '') {
                emailClearBtn.style.display = 'flex';
            } else {
                emailClearBtn.style.display = 'none';
            }
        }

        // Clear email input
        emailClearBtn.addEventListener('click', function() {
            emailInput.value = '';
            emailInput.focus();
            toggleClearButton();

            // Reset form state if password field is visible
            if (passwordField.style.display === 'block') {
                passwordField.style.display = 'none';
                continueBtn.textContent = 'Continue with email';
                continueBtn.onclick = checkEmail;

                // Clear any validation error styling
                emailInput.classList.remove('is-invalid');
                const passwordInput = document.getElementById('password');
                if (passwordInput) {
                    passwordInput.classList.remove('is-invalid');
                }
            }
        });

        // Listen for input changes
        emailInput.addEventListener('input', toggleClearButton);

        // Initial check
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
                // Show password field if user exists
                passwordField.style.display = 'block';
                continueBtn.textContent = 'Login';
                continueBtn.onclick = () => loginForm.submit();

                // Initialize password toggle for the newly shown password field
                const passwordInput = document.getElementById('password');
                if (passwordInput && window.PasswordToggle) {
                    PasswordToggle.addToField(passwordInput);
                }
            } else {
                // Redirect to register page if user doesn't exist
                window.location.href = '/register?email=' + encodeURIComponent(email);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    </script>

    <!-- Password Toggle Script -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>

    <!-- SWAL Error Handling -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle SWAL errors from session
            @if(session('swal_error'))
                SwalUtils.showError(@json(session('swal_error')));
            @endif

            // Handle regular errors from session
            @if(session('error'))
                SwalUtils.showError(@json(session('error')));
            @endif

            // Handle success messages from session
            @if(session('success'))
                SwalUtils.showSuccess(@json(session('success')));
            @endif

            // Handle login success messages
            @if(session('login_success'))
                SwalUtils.showSuccess(@json(session('login_success')));
            @endif
        });
    </script>
</body>

</html>
