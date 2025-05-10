<!DOCTYPE html>
<html>
<head>
    <style>
        .verification-code {
            font-size: 32px;
            letter-spacing: 5px;
            color: #3E57DA;
            font-weight: bold;
            text-align: center;
            padding: 20px;
            background: #f5f5f5;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h2>Hello {{ $name }},</h2>
    <p>Welcome to Syntopia! Please use the following verification code to complete your registration:</p>
    
    <div class="verification-code">
        {{ $code }}
    </div>

    <p>This code will expire in 30 minutes.</p>
    <p>If you didn't create an account, please ignore this email.</p>

    <p>Best regards,<br>Syntopia Team</p>
</body>
</html> 