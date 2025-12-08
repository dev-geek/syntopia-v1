<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Update Password</title>
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
        .login-container h2{
            font-size: 24px;
            font-weight: 500;
        }
        .login-container p{
            font-size: 13px;
            font-weight: 400;
        }
        .logo {
            width: 150px;
            margin-bottom: 20px;
        }


        input {
           max-width: 100%;
           width: 100%;
            padding: 10px;
            font-size: 13px;
            font-weight: 400;
            margin: 10px  0 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #E7E7E9;
        }

        .primary-button {
            width: 100%;
            font-size: 13px;
            font-weight: 500;
            padding: 10px;
            background: rgb(62, 87, 218);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;

        }
    </style>
</head>
<body>

    <div class="login-container">
        <x-logo alt="Logo" />
        <h2>Create New Password</h2>
        <p>Your new password must be different from previous used passwords.</p>

        {{-- Alerts removed: now handled by SWAL --}}

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            <input type="password" id="new-password" name="password" class="new-pass" placeholder="Enter new password" required>
            <input type="password" id="confirm-new-password" name="password_confirmation" class="confirm-new" placeholder="Confirm new password" required>
            <button type="submit" class="primary-button">Reset Password</button>
        </form>
    </div>
</body>
</html>
