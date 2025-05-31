<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verification code - Syntopia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .btn-light{
            background-color: #E7E7E9;
            font-size: 14px;
            font-weight: 500;
        }
        .footer-text{
            text-align: center;
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
        .text-primary, .footer-text {
            font-size: 11px;
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
    <div class="logo-container">
        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo">
    </div>

    <div class="d-flex justify-content-center align-items-center">
        <div class="container-box text-center">
            <h1 class="heading-text">Check your Email</h1>
            <p class="email-text">Please enter the verification code was sent to {{ $email ?? Auth::user()->email }}</p>

            <form method="POST" action="{{ url('/verify-code') }}">
    @csrf
    <div class="mb-3">
        <label for="verification-code">Verification code</label>
        <input type="text" name="verification_code" id="verification-code" class="form-control" placeholder="Paste verification code" required>
        @error('verification_code')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div>
    <button type="submit" class="primary-button">Verify Code</button>
</form>


            <div class="mb-3">
                <p class="email-text">
                    Can't find the email? <a href="{{ route('resend.code') }}" class="text-secondary">Resend code</a>
                </p>
            </div>



            <p class="text-muted mt-3" style="font-size: 11px;">
                By joining the workspace, you agree to our
                <a href="#" class="text-primary">User Terms of Service</a> and
                <a href="#" class="text-primary">Privacy Policy</a>.
            </p>
        </div>
    </div>

    <div class="footer-text">
        Having trouble? Contact us at
        <a href="mailto:info@syntopia.ai" class="text-primary">info@syntopia.ai</a>.
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
