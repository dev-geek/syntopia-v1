<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Syntopia Software - Login Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo img {
            height: 60px;
            width: auto;
        }

        .login-title {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }

        .password-field-wrapper {
            position: relative;
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
            color: #667eea;
        }

        .password-field-wrapper input[type="password"],
        .password-field-wrapper input[type="text"] {
            padding-right: 45px !important;
        }

        .demo-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #1976d2;
        }

        .demo-info strong {
            color: #1565c0;
        }

        .token-status {
            background: #f3e5f5;
            border: 1px solid #9c27b0;
            border-radius: 10px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: #7b1fa2;
        }

        .token-status.success {
            background: #e8f5e8;
            border-color: #4caf50;
            color: #2e7d32;
        }

        .token-status.error {
            background: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }

        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #f0f0f0;
            transform: translateX(-5px);
        }
    </style>
</head>
<body>
    <a href="{{ route('home') }}" class="back-link">
        <i class="bi bi-arrow-left"></i>
        Back to Dashboard
    </a>

    <div class="login-container">
        <div class="logo">
            <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
        </div>

        <h1 class="login-title">Welcome Back</h1>

        <div class="demo-info">
            <strong>Demo Mode:</strong> This is a demonstration of the auto-login functionality.
            In a real scenario, this page would be at <code>https://live.syntopia.ai/login</code>
        </div>

        <div id="tokenStatus" class="token-status" style="display: none;">
            <!-- Token status will be displayed here -->
        </div>

        <form id="loginForm">
            @csrf
            <div class="form-floating">
                <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                <label for="email">Email address</label>
            </div>

            <div class="form-floating password-field-wrapper">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password">Password</label>
                <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                    <i class="bi bi-eye" id="passwordToggleIcon"></i>
                </button>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Sign In
            </button>
        </form>

        <div class="text-center mt-3">
            <small class="text-muted">
                Don't have an account?
                <a href="#" class="text-decoration-none">Contact your administrator</a>
            </small>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Auto-login script -->
    <script src="{{ asset('js/software-auto-login.js') }}"></script>
    <!-- Password Toggle Script -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>

    <script>
        // Password toggle functionality
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            // In a real scenario, this would submit to the actual login endpoint
            console.log('Login attempt:', { email, password });

            // Show success message
            showAutoFillMessage('Login form submitted! (This is a demo)', 'success');
        });

        // Demo function to show token status
        function showTokenStatus(message, type = 'info') {
            const statusDiv = document.getElementById('tokenStatus');
            statusDiv.style.display = 'block';
            statusDiv.className = `token-status ${type}`;
            statusDiv.innerHTML = message;
        }

        // Check for token in URL on page load
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');

            if (token) {
                showTokenStatus(`
                    <strong>Auto-login Token Detected!</strong><br>
                    Token: ${token.substring(0, 20)}...<br>
                    <small>In a real implementation, this would decrypt and auto-fill the form.</small>
                `, 'success');

                // Simulate auto-fill for demo purposes
                setTimeout(() => {
                    document.getElementById('email').value = 'demo@syntopia.ai';
                    document.getElementById('password').value = 'demo_password_123';

                    // Trigger events
                    document.getElementById('email').dispatchEvent(new Event('input', { bubbles: true }));
                    document.getElementById('password').dispatchEvent(new Event('input', { bubbles: true }));

                    showAutoFillMessage('Demo: Login credentials have been pre-filled!', 'success');
                }, 1000);
            } else {
                showTokenStatus(`
                    <strong>No Auto-login Token</strong><br>
                    <small>This page would normally auto-fill credentials when accessed with a valid token.</small>
                `, 'info');
            }
        });
    </script>
</body>
</html>
