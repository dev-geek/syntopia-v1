<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signup - Syntopia</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
         body {
            background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
            background-size: cover;
            height: 95vh;
            padding: 30px;
        }
        .heading-text{
            font-size: 24px;
            padding-bottom: 20px;
            font-weight: 500;
           color:#000;
        }

        .container-box {
            max-width: 100%;
            width: 520px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }
        .email-text{
            font-size: 13px;
        }
        .logo-container {
            text-align: center;
            margin-top: 20px;
            justify-content: center;
            align-items: left;
            display: flex;
            flex-direction: column;
        }
        .logo-container img {
            width: 160px;
        }
        .form-control {
            background: #E7E7E9;
            font-size: 13px;
            border: none !important;
            padding: 0.5em !important;
        }
        .create-account {
            border: none;
            padding: 7px 0px !important;
        }
        .back a {
            color: black !important;
            text-decoration: none;
        }
        .back:hover {
            background-color: color-mix(in srgb, rgb(43, 46, 64) 6%, rgba(0, 0, 0, 0));
        }
        .d-flex{
            height: 80vh;
        }

        label {
            font-weight: 500;
            font-size: 13px;
            text-align: left;
            display: block;
            margin-bottom: 5px;
        }

        .footer-text{
            text-align: center;
        }
        .logo{
            width: 50px;
        }
        .primary-button {
            width: 100%;
            padding: 10px;
            font-size: 13px;
            font-weight: 500;
            background:rgb(62, 87, 218);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .text-secondar{
            font-size: 13px;
            font-weight: 500;
            color: black !important;
        }
         .text-primary, .footer-text {
            font-size: 11px;
        }

        /* Password Toggle Styles */
        .password-field-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 8px;
            border-radius: 4px;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .password-toggle-btn:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }

        .password-toggle-btn:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
        }

        .password-toggle-btn i {
            font-size: 16px;
            line-height: 1;
        }

        /* Ensure password input has right padding to accommodate the toggle button */
        .password-field-wrapper input[type="password"],
        .password-field-wrapper input[type="text"] {
            padding-right: 45px !important;
        }

        /* Responsive adjustments for password toggle */
        @media (max-width: 768px) {
            .password-toggle-btn {
                padding: 6px;
            }

            .password-toggle-btn i {
                font-size: 14px;
            }

            .password-field-wrapper input[type="password"],
            .password-field-wrapper input[type="text"] {
                padding-right: 40px !important;
            }
        }
            color: #6c757d !important;
        }
        @media (max-width: 768px){

        .d-flex{
            height: 75vh;
        }
        }

    </style>
</head>
<body>

    <!-- Logo at the top -->
    <div class="logo-container">
        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">

    </div>

    <!-- Centered Signup Form -->
    <div class="d-flex justify-content-center align-items-center">
        <div class="container-box text-center">
            <h1 class="heading-text">Welcome To Syntopia</h1>
            <p class="email-text">You're setting up an account for {{ request()->get('email') }}</p>
            @include('components.alert-messages')
            <!-- Signup Form -->
            <form method="POST" action="{{ route('register') }}">
            @csrf
                <input type="hidden" name="email" value="{{ request()->get('email') }}">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror"
                            placeholder="Enter your first name" required value="{{ old('first_name') }}">
                        @error('first_name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror"
                            placeholder="Enter your last name" required value="{{ old('last_name') }}">
                        @error('last_name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                </div>
                <div class="mb-3">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                        placeholder="Enter an email address" required>
                    @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                        placeholder="Enter a strong password" required>
                    @error('password')
                        <span class="invalid-feedback d-block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <button type="submit" class="primary-button">Create account</button>
            </form>

            <!-- Back Button -->
            <div class="back">
                <a href="{{ route('login') }}" class="text-secondary">Back</a>
            </div>

            <!-- Terms & Privacy -->
            <p class="text-muted mt-3" style="font-size: 11px;">
                By joining the workspace, you agree to our
                <a href="#" class="text-primary">User Terms of Service</a> and
                <a href="#" class="text-primary">Privacy Policy</a>.

            </p>

        </div>
    </div>
    <div class="footer-text ">
         Having trouble? Contact us at
                <a href="mailto:info@syntopia.ai" class="text-primary">info@syntopia.ai</a>.
    </div>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const passwordFields = document.querySelectorAll('input[type="password"]');

            passwordFields.forEach(function(passwordField) {
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
        });
    </script>
</body>
</html>
