<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Email - Syntopia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
            background-size: cover;
            height: 95vh;
            padding: 30px;
        }
        .container-box {
            max-width: 100%;
            width: 520px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
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
        .heading-text {
            font-size: 24px;
            padding-bottom: 20px;
            font-weight: 500;
            color: #000;
        }
        .email-text {
            font-size: 13px;
            margin-bottom: 20px;
        }
        .d-flex {
            height: 80vh;
        }
        .primary-button {
            width: 100%;
            padding: 10px;
            font-size: 13px;
            font-weight: 500;
            background: rgb(62, 87, 218);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .footer-text {
            text-align: center;
            font-size: 11px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Logo at the top -->
    <div class="logo-container">
        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
    </div>

    <!-- Centered Content -->
    <div class="d-flex justify-content-center align-items-center">
        <div class="container-box text-center">
            <h1 class="heading-text">Check your email</h1>
            <p class="email-text">We sent a verification link to<br><strong>{{ session('email') }}</strong></p>
            
            <form method="POST" action="{{ route('verification.resend') }}">
                @csrf
                <button type="submit" class="primary-button">Resend email</button>
            </form>

            @if (session('resent'))
                <div class="alert alert-success" role="alert">
                    A fresh verification link has been sent to your email address.
                </div>
            @endif
        </div>
    </div>

    <div class="footer-text">
        Having trouble? Contact us at 
        <a href="mailto:info@syntopia.ai" class="text-primary">info@syntopia.ai</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 