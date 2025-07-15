<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Signup - Syntopia</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
         body {
            background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed;
            background-size: cover;
            height: 95vh;
            padding: 30px;
        }
        .heading-text{
            font-size: 50px;
            padding: 30px 0px 0px;

            font-weight: 700;
            width: 50%;
           color:#5b0dd5;
        }
        .heading-text span{
            color:black;
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
            align-items: center;
            display: flex;
            flex-direction: column;
        }
        .logo-container img {
            width: 160px;
        }
        .form-control {
            background: #E7E7E9;
            border: none !important;
            padding: 0.5em !important;
        }
        .create-account {
            border: none;
            padding: 7px 0px !important;
        }
        .back:hover {
            background-color: color-mix(in srgb, rgb(43, 46, 64) 6%, rgba(0, 0, 0, 0));
        }
        .d-flex{
            height: 55vh;
        }

        label {
            font-weight: 500;
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
            background: #5B0DD5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
        }
        @media (max-width: 768px){
            .heading-text{
            font-size: 40px;
            font-weight: 700;
            width: 100%;
           color:#5b0dd5;
        }
        .d-flex{
            height: 75vh;
        }
        }

    </style>
</head>
<body>

    <!-- Logo at the top -->
    <div class="logo-container">
        <img src="https://syntopia.ai/wp-content/uploads/2025/02/Syntopiaa-logo.webp" alt="Syntopia Logo">
     </div>

    <!-- Centered Signup Form -->
    <div class="d-flex justify-content-center align-items-center">
        <div class="container-box text-center">

        <div class="">
                    {{ __('Before proceeding, please check your email for a verification link.') }}
                    {{ __('If you did not receive the email') }},
                    <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 m-0 align-baseline">{{ __('click here to request another') }}</button>.
                    </form>
                </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
