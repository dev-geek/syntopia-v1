<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .email-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #3E57DA;
            margin-bottom: 10px;
        }
        .welcome-text {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
        }
        .verification-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            margin: 20px 0;
        }
        .verification-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .footer-text {
            color: #888;
            font-size: 14px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .highlight {
            color: #3E57DA;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <div class="logo">Syntopia</div>
            <div class="welcome-text">Welcome to Syntopia!</div>
        </div>

        <p>Thank you for joining us! To complete your registration and start using Syntopia, please verify your email address by clicking the button below:</p>

        <div class="button-container">
            <a href="{{ $verificationUrl }}" class="verification-button">
                ✉️ Verify Email Address
            </a>
        </div>

        <p>This verification link will expire in <span class="highlight">24 hours</span> for security purposes.</p>

        <div class="footer-text">
            <p>If you did not create an account with Syntopia, you can safely ignore this email.</p>
            <p>Best regards,<br><strong>The Syntopia Team</strong></p>
        </div>
    </div>
</body>
</html>
