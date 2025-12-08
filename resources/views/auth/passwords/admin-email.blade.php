<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Admin Reset Password</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}">

    <style>
        body {
            background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            width: 100%;
            max-width: 500px;
            text-align: center;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        .logo {
            width: 150px;
            margin-bottom: 20px;
        }
        input {
            max-width: 100%;
            width: 100%;
            padding: 10px;
            margin: 10px 0 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #E7E7E9;
        }
        input[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        .primary-button {
            width: 100%;
            padding: 10px;
            background-color: #000;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        .primary-button:hover {
            background-color: #333;
        }
        .error-message {
            color: #dc3545;
            margin-top: 5px;
            font-size: 0.875em;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-control {
            border-radius: 5px;
            padding: 12px 15px;
            font-size: 16px;
        }
        .alert {
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        .primary-button {
            transition: all 0.3s ease;
        }
        .primary-button:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }

        /* Loading Spinner */
        .spinner-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .button-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .button-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    </style>
    <!-- Loading Spinner Overlay -->
    <div id="loadingSpinner" class="spinner-container">
        <div class="spinner"></div>
    </div>

    <script>
        function showLoading(show) {
            const spinner = document.getElementById('loadingSpinner');
            const buttonSpinner = document.querySelector('.button-spinner');
            const buttonText = document.querySelector('.button-text');
            const submitButton = document.getElementById('submitButton');

            if (show) {
                spinner.style.display = 'flex';
                buttonSpinner.style.display = 'block';
                buttonText.textContent = 'Processing...';
                submitButton.disabled = true;
            } else {
                spinner.style.display = 'none';
                buttonSpinner.style.display = 'none';
                const currentLabel = buttonText.textContent || '';
                buttonText.textContent = currentLabel.includes('Reset') ? 'Reset Password' : 'Continue';
                submitButton.disabled = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const securityQuestions = document.getElementById('securityQuestions');
            const form = document.getElementById('passwordResetForm');
            const submitButton = document.getElementById('submitButton');
            let emailVerified = false;

            // Check if we're showing security questions
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('email') && urlParams.has('show_questions')) {
                emailInput.value = urlParams.get('email');
                emailInput.readOnly = true;
                securityQuestions.style.display = 'block';
                const buttonTextEl = document.querySelector('.button-text');
                if (buttonTextEl) buttonTextEl.textContent = 'Reset Password';
                emailVerified = true;
            }

            form.addEventListener('submit', async function(e) {
                if (!emailVerified) {
                    e.preventDefault();
                    showLoading(true);

                    try {
                        const response = await fetch('{{ route("admin.password.check-email") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({
                                email: emailInput.value
                            })
                        });

                        const data = await response.json();

                        if (data.requires_security_questions) {
                            emailInput.readOnly = true;
                            securityQuestions.style.display = 'block';
                            const buttonTextEl2 = document.querySelector('.button-text');
                            if (buttonTextEl2) buttonTextEl2.textContent = 'Reset Password';
                            emailVerified = true;

                            // Add email to form
                            const emailField = document.createElement('input');
                            emailField.type = 'hidden';
                            emailField.name = 'email';
                            emailField.value = emailInput.value;
                            form.appendChild(emailField);
                        } else {
                            form.submit();
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    } finally {
                        showLoading(false);
                    }
                }
            });
        });
    </script>
</head>
<body>
    <div class="login-container">
        <x-logo />
        <h2>Admin Password Reset</h2>

        @if (session('status'))
            <div class="alert alert-success" role="alert">
                @if (is_array(session('status')))
                    {{ session('status.message') }}
                @else
                    {{ session('status') }}
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('admin.password.email') }}" id="passwordResetForm">
            @csrf

            <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                       name="email" value="{{ old('email', request('email', '')) }}"
                       required autocomplete="email" autofocus
                       {{ request()->has('email') ? 'readonly' : '' }}>

                @error('email')
                    <span class="error-message" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>

            <div id="securityQuestions" style="display: none;">
                <div class="form-group">
                    <label for="city">What city were you born in?</label>
                    <input id="city" type="text" class="form-control @error('city') is-invalid @enderror"
                           name="city" value="{{ old('city') }}">
                    @error('city')
                        <span class="error-message" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="pet">What was your first pet's name?</label>
                    <input id="pet" type="text" class="form-control @error('pet') is-invalid @enderror"
                           name="pet" value="{{ old('pet') }}">
                    @error('pet')
                        <span class="error-message" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>

            <button type="submit" class="primary-button" id="submitButton">
                <span class="button-content">
                    <span class="button-text">Continue</span>
                    <span class="button-spinner"></span>
                </span>
            </button>
        </form>

        <div style="margin-top: 20px;">
            <a href="{{ route('admin.login') }}">Back to Login</a>
        </div>
    </div>
</body>
</html>
