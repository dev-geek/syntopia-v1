<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Admin Create New Password</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        body { background: url('https://syntopia.ai/wp-content/uploads/2025/01/Clip-path-group.webp') no-repeat center center fixed; background-size: cover; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-container { width: 100%; max-width: 480px; text-align: center; background: white; border-radius: 10px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1); padding: 30px; }
        .logo { width: 150px; margin-bottom: 20px; }
        .form-group { text-align: left; margin-bottom: 1rem; }
        .form-control { border-radius: 6px; padding: 12px 14px; }
        .primary-button { width: 100%; padding: 12px; background: #000; color: #fff; border: 0; border-radius: 6px; font-weight: 600; }
        .primary-button:hover { background: #333; }
        .alert { border-radius: 6px; }
        .password-field-wrapper { position: relative; }
        .password-toggle-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: #fff; border: 1px solid #ddd; color: #6c757d; cursor: pointer; padding: 6px; border-radius: 6px; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.password-toggle-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const input = this.previousElementSibling;
                    if (input.type === 'password') { input.type = 'text'; this.textContent = 'Hide'; } else { input.type = 'password'; this.textContent = 'Show'; }
                });
            });
        });
    </script>
    <meta name="robots" content="noindex">
    <meta name="googlebot" content="noindex">
</head>
<body>
    <div class="login-container">
        <img src="https://syntopia.ai/wp-content/uploads/2025/01/logo-syntopia-black-scaled.webp" alt="Syntopia Logo" class="logo">
        <h2>Set a New Password</h2>
        <p>Your new password must meet security requirements.</p>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" required readonly>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">New Password</label>
                <div class="password-field-wrapper">
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" placeholder="Enter new password">
                    <button type="button" class="password-toggle-btn">Show</button>
                </div>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password-confirm">Confirm Password</label>
                <div class="password-field-wrapper">
                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm new password">
                    <button type="button" class="password-toggle-btn">Show</button>
                </div>
            </div>

            <button type="submit" class="primary-button">Reset Password</button>
        </form>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recover Password (v2)</title>

  <!-- Favicon -->
  <link rel="icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">
  <link rel="shortcut icon" type="image/webp" href="{{ asset('syntopia-logo.webp') }}">

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">

  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="{{ asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">

  <!-- Theme style -->
  <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <a href="#" class="h1"><b>Admin</b>LTE</a>
    </div>
    <div class="card-body">
      <p class="login-box-msg">You are only one step away from your new password, recover your password now.</p>

      <form method="POST" action="{{ route('admin.password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <input type="hidden" name="email" value="{{ request('email') }}">

    <div class="form-group">
        <label for="password">New Password</label>
        <input id="password" type="password" name="password" class="form-control" required>
    </div>

    <div class="form-group">
        <label for="password-confirm">Confirm Password</label>
        <input id="password-confirm" type="password" name="password_confirmation" class="form-control" required>
    </div>

    <button type="submit" class="btn btn-primary">Reset Password</button>
</form>

      <p class="mt-3 mb-1">
        <a href="{{ route('login') }}">Login</a>
      </p>
    </div>
  </div>
</div>

<!-- jQuery -->
<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>

<!-- Bootstrap 4 -->
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

<!-- AdminLTE App -->
<script src="{{ asset('dist/js/adminlte.min.js') }}"></script>

</body>
</html>
