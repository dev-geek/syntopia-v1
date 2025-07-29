<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Toggle Demo - Syntopia</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Password Toggle CSS -->
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .demo-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2rem;
        }

        .demo-title {
            text-align: center;
            color: #333;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        .demo-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            background: #f8f9fa;
        }

        .demo-section h4 {
            color: #495057;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list i {
            color: #28a745;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="demo-container">
            <h1 class="demo-title">Password Toggle Component Demo</h1>

            <div class="demo-section">
                <h4>✨ Features</h4>
                <ul class="feature-list">
                    <li><i class="fas fa-check"></i> Beautiful glassmorphism design</li>
                    <li><i class="fas fa-check"></i> Smooth animations and transitions</li>
                    <li><i class="fas fa-check"></i> Fully responsive</li>
                    <li><i class="fas fa-check"></i> Keyboard accessible</li>
                    <li><i class="fas fa-check"></i> Screen reader friendly</li>
                    <li><i class="fas fa-check"></i> Auto-initialization</li>
                    <li><i class="fas fa-check"></i> Dark theme support</li>
                </ul>
            </div>

            <div class="demo-section">
                <h4>🔧 Basic Usage</h4>
                <div class="mb-3">
                    <label for="password1" class="form-label">Password Field</label>
                    <input type="password" id="password1" class="form-control" placeholder="Enter your password">
                </div>
            </div>

            <div class="demo-section">
                <h4>🎨 With Validation</h4>
                <div class="mb-3">
                    <label for="password2" class="form-label">Password with Error</label>
                    <input type="password" id="password2" class="form-control is-invalid" placeholder="Enter your password">
                    <div class="invalid-feedback">Please enter a valid password.</div>
                </div>
            </div>

            <div class="demo-section">
                <h4>🌙 Dark Theme</h4>
                <div class="mb-3">
                    <label for="password3" class="form-label">Dark Theme Password</label>
                    <div class="password-field-wrapper dark">
                        <input type="password" id="password3" class="form-control" placeholder="Enter your password">
                    </div>
                </div>
            </div>

            <div class="demo-section">
                <h4>📱 Responsive Test</h4>
                <p class="text-muted">Try resizing your browser window to see the responsive behavior.</p>
                <div class="mb-3">
                    <label for="password4" class="form-label">Responsive Password Field</label>
                    <input type="password" id="password4" class="form-control" placeholder="Enter your password">
                </div>
            </div>

            <div class="demo-section">
                <h4>⌨️ Keyboard Navigation</h4>
                <p class="text-muted">Tab to the password field, then use Tab to focus the toggle button and press Enter or Space to toggle.</p>
                <div class="mb-3">
                    <label for="password5" class="form-label">Keyboard Accessible</label>
                    <input type="password" id="password5" class="form-control" placeholder="Enter your password">
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="{{ route('login') }}" class="btn btn-primary">Back to Login</a>
                <a href="{{ route('register') }}" class="btn btn-outline-primary ms-2">Back to Register</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Password Toggle Script -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>
</body>
</html>
