<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Reset Password</title>
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
            max-width: 400px;
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
            margin: 10px  0 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #E7E7E9;
        }

        .primary-button {
            width: 100%;
            padding: 10px;
            background: #5B0DD5;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;

        }
    </style>
</head>
<body>

    <div class="login-container">
        <img src="https://syntopia.ai/wp-content/uploads/2025/02/Syntopiaa-logo.webp" alt="Logo" class="logo">
        <h2>Reset Your Password</h2>
        <p>Forgot Your Password? Please enter your email and we'll send you a reset link.</p>

        @if (session('status'))
            <div class="alert alert-success" style="text-align: left; margin-bottom: 20px;">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" style="text-align: left; margin-bottom: 20px;">
                <strong>Please fix the following errors:</strong><br>
                @foreach ($errors->all() as $error)
                    • {{ $error }}<br>
                @endforeach
            </div>
        @endif
        <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="Enter Your email..." autofocus>
        @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
        <button type="submit" class="primary-button">Submit</button>
        </form>


    </div>
</body>
</html>
