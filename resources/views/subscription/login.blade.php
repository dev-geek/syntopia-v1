<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-firstpromoter-tracking />
    <x-facebook-pixel />
    <x-tiktok-pixel />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/password-toggle.css') }}">
    <title>Login Page</title>
    <style>
        body {

            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;

            margin: 0;
            background-color: #f0f4f8;
        }
        .container {
            display: flex;
            width: 100%;
            max-width: 100%;
            padding: 0px;
            height: 100%;
            background: white;
            overflow: hidden;
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
        .video1, .video2{
            position: absolute;
            width: 40%;
            border-radius: 10px;
        }

        .video3,  .video6{
            position: absolute;
            width: 20%;
            border-radius: 10px;
        }
        .video4{
            position: absolute;
            width: 22%;
            border-radius: 10px;
        }
        .video5, .video7{
            position: absolute;
            width: 38%;
            border-radius: 10px;
        }

        .floating-1 { top: -1%; left: -1%; }
        .floating-2 { top: 8%; right: 10%; }
        .floating-3 { top: 40%; left: -6%; }
        .floating-4 { top: 35%; left: 30%; }
        .floating-5 { top: 40%; right: -8%; }
        .floating-6 { bottom: 0%; right: -2%; }
        .floating-7 { top: 78%; left: 20%; }
        .right-section {
            width: 50%;
            flex-direction: column;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-align: center;
            padding: 40px;
        }
        .right-section h2{
            font-weight: 700;
            font-size:35px;
        }
        .login-container {
            width: 100%;
            max-width: 360px;
            margin: 40px 0px;

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
        }
        input {
           max-width: 100%;
           width: 100%;
            padding: 10px;
            margin: 10px  0 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .primary-button, .secondary-button{
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;

        }
        .primary-button {
            width: 100%;
            padding: 10px;
            background: #5B0DD5;
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
        .login-container a{

            color:black;
        }
        .divider {
            margin: 10px 0;
            color: #aaa;
        }
        .terms, .support {
            font-size: 12px;
            color: #777;
        }
        .terms a, .support a {
            color: #5B0DD5;
            text-decoration: none;
        }

        @media (max-width: 768px){
            .left-section{
                display: none;
            }
            .right-section{
                width: 100%;
            }
            .right-section{
                padding: 50px 20px;
            }
            .right-section h2{
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Left Side - Floating Videos -->
        <div class="left-section">
            <div class="video-container">
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/Alex_LP_fin.mp4" autoplay loop muted class="video1 floating-1"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/Video_Examples_for_Website_-_02_-_Value_selling_fundamentals.mp4" autoplay loop muted class="video2 floating-2"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/welcome_(2).mp4" autoplay loop muted class="video3 floating-3"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/hey.mp4" autoplay loop muted class="video4 floating-4"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/Sign_in_7.mp4" autoplay loop muted class="video5 floating-5"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/Sign_in_6.mp4" autoplay loop muted class="video6 floating-6"></video>
                <video src="https://cdn.synthesia.io/assets-public/welcome-page/Video_Examples_for_Website_-_04_-_Understanding_Your_Bill.mp4" autoplay loop muted class="video7 floating-7"></video>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="right-section">
            <div class="login-container">
                <x-logo alt="Logo" />
                <h2>Welcome to Syntopia</h2>
                <br>
                <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="input-field">
                    <label for="work-email">Email</label>
                    <input type="email" placeholder="Enter your email here ..." name="email" value="{{ old('email') }}" required autocomplete="email" autofocus id="email" class="form-control @error('email') is-invalid @enderror@">
                </div>
                <div class="input-field">
                    <label for="work-email">Password</label>
                <input id="password" type="password" placeholder="Enter your password here ..." class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
                </div>
                @error('password')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                                @enderror
                <button class="primary-button">Login</button>
                </form>
                <a href="{{ route('password.request') }}">Forgot password?</a>
                <br>
                <br>
                <a href="{{ route('register') }}">Register Here!</a>
                <div class="divider">or</div>
                <a href="{{ route('auth.google') }}"><button  class="secondary-button google"><img src="https://syntopia.ai/wp-content/uploads/2025/02/google-icon.png" alt="Google Logo"> Continue with Google</button></a>
                <a href="{{ route('login.facebook') }}"><button class="secondary-button sso"><img src="https://syntopia.ai/wp-content/uploads/2025/02/facebook-icon.png" alt="facebook Logo"> Continue with Facebook</button></a>

                <p class="terms">By signing up to the Syntopia platform, you understand and agree
                    with our <a href="#">Customer Terms of Service</a> and
                    <a href="#">Privacy Policy</a>.</p>

            </div>
            <p class="support">Having trouble? Contact us at <a href="mailto:info@syntopia.ai">info@syntopia.ai</a></p>
        </div>

    </div>

    <!-- Password Toggle Script -->
    <script src="{{ asset('js/password-toggle.js') }}"></script>
</body>

<script>
    // Persist add-on request for post-login flow
    (function() {
        try {
            const params = new URLSearchParams(window.location.search);
            const adon = params.get('adon');
            if (adon) {
                sessionStorage.setItem('pendingAddon', adon);
            }
        } catch (e) {}
    })();
    </script>
</html>
