<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .message {
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <div class="message">{{ $message ?? 'Payment successful! Redirecting...' }}</div>
    </div>

    <script>
        // Close the popup and redirect parent window to subscription details page immediately
        const redirectUrl = '{{ $redirectUrl ?? route('user.subscription.details') }}';
        
        // Redirect immediately without any delay
        if (window.opener && !window.opener.closed) {
            try {
                window.opener.location.href = redirectUrl;
            } catch (e) {
                // If opener redirect fails, redirect current window
                window.location.href = redirectUrl;
            }
            // Close popup immediately
            window.close();
        } else {
            // If no opener, redirect current window immediately
            window.location.href = redirectUrl;
        }
        
        // Fallback: if redirect doesn't happen within 1 second, force it
        setTimeout(() => {
            if (window.opener && !window.opener.closed) {
                try {
                    window.opener.location.replace(redirectUrl);
                    window.close();
                } catch (e) {
                    window.location.replace(redirectUrl);
                }
            } else {
                window.location.replace(redirectUrl);
            }
        }, 1000);
    </script>
</body>
</html>

