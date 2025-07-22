<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Login Page</title>
    <style>
    html,
    body {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }

    /* Password Toggle Styles */
    .password-field-wrapper {
        position: relative;
        display: inline-block;
        width: 100%;
    }

    .password-toggle-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #777;
        cursor: pointer;
        padding: 6px 4px;
        border-radius: 3px;
        transition: all 0.2s ease;
        z-index: 10;
        font-size: 13px;
        height: 24px;
        width: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .password-toggle-btn:hover {
        background-color: #E7E7E9;
        color: #333;
    }

    .password-toggle-btn:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(62, 87, 218, 0.25);
    }

    .password-toggle-btn i {
        font-size: 14px;
        line-height: 1;
    }

    /* Ensure password input has right padding to accommodate the toggle button */
    .password-field-wrapper input[type="password"],
    .password-field-wrapper input[type="text"] {
        padding-right: 40px !important;
    }

    /* Responsive adjustments for password toggle */
    @media (max-width: 768px) {
        .password-toggle-btn {
            padding: 4px 3px;
            right: 6px;
            height: 22px;
            width: 22px;
        }

        .password-toggle-btn i {
            font-size: 12px;
        }

        .password-field-wrapper input[type="password"],
        .password-field-wrapper input[type="text"] {
            padding-right: 32px !important;
        }
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

    .video1,
    .video2 {
        position: absolute;
        width: 40%;
        border-radius: 10px;
    }

    .video3,
    .video6 {
        position: absolute;
        width: 20%;
        border-radius: 10px;
    }

    .video4 {
        position: absolute;
        width: 22%;
        border-radius: 10px;
    }

    .video5,
    .video7 {
        position: absolute;
        width: 38%;
        border-radius: 10px;
    }

    .floating-1 {
        top: -1%;
        left: -1%;
    }

    .floating-2 {
        top: 8%;
        right: 10%;
    }

    .floating-3 {
        top: 40%;
        left: -6%;
    }

    .floating-4 {
        top: 35%;
        left: 30%;
    }

    .floating-5 {
        top: 40%;
        right: -8%;
    }

    .floating-6 {
        bottom: 0%;
        right: -2%;
    }

    .floating-7 {
        top: 78%;
        left: 20%;
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
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/Alex_LP_fin.mp4" autoplay loop muted
                    class="video1 floating-1"></video>
                <video
                    src="https://cdn.synthesia.io/assets-public/welcome-page/Video_Examples_for_Website_-_02_-_Value_selling_fundamentals.mp4"
                    autoplay loop muted class="video2 floating-2"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/welcome_(2).mp4" autoplay loop muted
                    class="video3 floating-3"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/hey.mp4" autoplay loop muted
                    class="video4 floating-4"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/Sign_in_7.mp4" autoplay loop muted
                    class="video5 floating-5"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/Sign_in_6.mp4" autoplay loop muted
                    class="video6 floating-6"></video>
                <video
                    src="https://cdn.synthesia.io/assets-public/welcome-page/Video_Examples_for_Website_-_04_-_Understanding_Your_Bill.mp4"
                    autoplay loop muted class="video7 floating-7"></video>
            </div>
        </div>

        <!-- Right Side -->
        <div class="right-section">
            <div class="login-container">
                <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Logo"
                    class="logo">
                <h2>Welcome to Syntopia</h2>
                <p>Use your <strong>work email</strong> for a better experience</p>
                <form method="POST" action="{{ route('login') }}" id="loginForm">
                    @csrf
                    <div class="input-field">
                        <label for="work-email">Work email</label>
                        <input type="email" placeholder="Type your work email..." name="email" value="{{ old('email') }}" required autocomplete="email" autofocus id="email" class="form-control @error('email') is-invalid @enderror@">
                        @error('email')
                            <span class="invalid-feedback" role="alert" style="text-align: left;padding-bottom: 10px;">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="input-field" id="password-field" style="display: none;">
                        <label for="password">Password</label>
                        <input id="password" type="password" placeholder="Enter your password..." class="@error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                        @error('password')
                            <span class="invalid-feedback" role="alert" style="text-align: left;padding-bottom: 10px;">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <button type="button" class="primary-button" id="continueBtn" onclick="checkEmail()">Continue with email</button>
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
                <p class="terms">By signing up to the Synthesia platform, you understand and agree with our <a
                        href="#">Customer Terms of Service</a> and <a href="#">Privacy Policy</a>.</p>
            </div>
            <p class="support">Having trouble? Contact us at <a href="mailto:info@syntopia.ai">info@syntopia.ai</a></p>
        </div>
    </div>

    <script>
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
                initializePasswordToggle();
            } else {
                // Redirect to register page if user doesn't exist
                window.location.href = '/register?email=' + encodeURIComponent(email);
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    // Password Toggle Functionality
    function initializePasswordToggle() {
        const passwordFields = document.querySelectorAll('input[type="password"]');

        passwordFields.forEach(function(passwordField) {
            // Skip if already has toggle button
            if (passwordField.parentElement.querySelector('.password-toggle-btn')) {
                return;
            }

            // Create wrapper div if it doesn't exist
            let wrapper = passwordField.parentElement;
            if (!wrapper.classList.contains('password-field-wrapper')) {
                wrapper = document.createElement('div');
                wrapper.className = 'password-field-wrapper position-relative';
                passwordField.parentNode.insertBefore(wrapper, passwordField);
                wrapper.appendChild(passwordField);
            }

            // Create toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'password-toggle-btn';
            toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
            toggleBtn.setAttribute('aria-label', 'Toggle password visibility');

            // Add toggle button to wrapper
            wrapper.appendChild(toggleBtn);

            // Add click event
            toggleBtn.addEventListener('click', function() {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);

                // Update icon
                const icon = toggleBtn.querySelector('i');
                if (type === 'text') {
                    icon.className = 'fas fa-eye-slash';
                    toggleBtn.setAttribute('aria-label', 'Hide password');
                } else {
                    icon.className = 'fas fa-eye';
                    toggleBtn.setAttribute('aria-label', 'Show password');
                }
            });
        });
    }

    // Initialize password toggle on page load
    document.addEventListener('DOMContentLoaded', function() {
        initializePasswordToggle();
    });
    </script>
</body>

</html>
